<?php

declare(strict_types=1);

namespace FrostyMedia\WpRestCop\RestApi;

use FrostyMedia\WpRestCop\RestApi\Rules\IpRules;
use FrostyMedia\WpRestCop\RestApi\Rules\IpRulesInterface;
use FrostyMedia\WpRestCop\ServiceProvider;
use TheFrosty\WpUtilities\Plugin\AbstractContainerProvider;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestInterface;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestTrait;
use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function apply_filters;
use function array_merge;
use function do_action;
use function esc_html__;
use function filter_var;
use function get_current_user_id;
use function get_option;
use function is_user_logged_in;
use function rest_convert_error_to_response;
use function sprintf;
use function update_option;
use const FILTER_VALIDATE_BOOLEAN;
use const MINUTE_IN_SECONDS;

/**
 * RateLimit class.
 * @package FrostyMedia\WpRestCop\RestApi
 */
class Officer extends AbstractContainerProvider implements HttpFoundationRequestInterface
{

    use HttpFoundationRequestTrait;

    public const string OPTION = 'wp_rest_cop_settings';

    /**
     * Number of requests allowed per interval.
     * @var integer $limit
     */
    protected int $limit;

    /**
     * Seconds per interval.
     * @var integer $interval
     */
    protected int $interval;

    public static function getSettings(): array
    {
        return get_option(self::OPTION, []);
    }

    /**
     * Add class hooks.
     */
    public function addHooks(): void
    {
        $this->addAction('rest_api_init', [$this, 'initializeSettings']);
        $this->addAction('rest_api_init', [$this, 'initializeIpRules']);
        $this->addFilter('rest_authentication_errors', [$this, 'checkIpRules']);
        $this->addFilter('rest_pre_dispatch', [$this, 'maybeThrottleRequest'], 10, 3);
        $this->addFilter('rest_dispatch_request', [$this, 'checkRouteIpRules'], 10, 2);

        do_action('wp_rest_cop_plugin_loaded', $this);
    }

    /**
     * Retrieve an identifier for the current client.
     * If a user is logged in, their user ID will be used, otherwise, defaults
     * to the current client's IP address.
     * @return int|string
     */
    public function getClientId(): string
    {
        $key = $this->getIpAddress();

        if (is_user_logged_in()) {
            $key = sprintf('%s:%d', $key, get_current_user_id());
        }

        return apply_filters('wp_rest_cop_current_client_id', $key);
    }

    /**
     * Retrieve the current client's IP address.
     * @return string
     */
    public function getIpAddress(): string
    {
        $request = $this->getRequest();
        $ip = $request->server->get('REMOTE_ADDR');
        if ($request->server->has('HTTP_X_FORWARDED_FOR')) {
            $ip = $request->server->get('HTTP_X_FORWARDED_FOR');
        }

        return $ip;
    }

    /**
     * Retrieve the rate limit.
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Set the number of requests allowed per interval.
     * @param int $limit Number of requests.
     * @return $this
     */
    public function setLimit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Retrieve the global rate limit interval.
     * @return int
     */
    public function getInterval(): int
    {
        return $this->interval;
    }

    /**
     * Set the number of seconds per interval.
     * @param int $interval Seconds per interval.
     * @return $this
     */
    public function setInterval(int $interval): static
    {
        $this->interval = $interval;
        return $this;
    }

    /**
     * Initialize the settings options.
     */
    protected function initializeSettings(): void
    {
        $settings = self::getSettings();

        if (empty($settings)) {
            $settings = [
                'interval' => MINUTE_IN_SECONDS,
                'limit' => 60,
                'rules' => [
                    IpRulesInterface::ALLOW => [
                        '127.0.0.1',
                        '::1',
                    ],
                    IpRulesInterface::DENY => [],
                ],
            ];
            update_option(self::OPTION, $settings);
        }

        $this->setLimit($settings['limit'])->setInterval($settings['interval']);
    }

    /**
     * Initialize IP rules from options.
     * These will usually be set with WP CLI.
     */
    protected function initializeIpRules(): void
    {
        $settings = self::getSettings();
        /** @var IpRules $rules */
        $rules = $this->getContainer()->get(ServiceProvider::IP_RULES);
        $rules
            ->allow($settings['rules'][IpRulesInterface::ALLOW] ?? [])
            ->deny($settings['rules'][IpRulesInterface::DENY] ?? []);
    }

    /**
     * Check global IP address settings.
     * @param WP_Error|true|null $error WP_Error if authentication error,
     *  null if authentication method wasn't used, true if authentication succeeded.
     * @return WP_Error|true|null
     */
    protected function checkIpRules(WP_Error|bool|null $error): WP_Error|bool|null
    {
        /** @var IpRules $rules */
        $rules = $this->getContainer()->get(ServiceProvider::IP_RULES);
        if (!$rules->check($this->getIpAddress())) {
            return $this->getForbiddenError();
        }

        return $error;
    }

    /**
     * Maybe throttle the current request.
     * @param mixed $response Response.
     * @param WP_REST_Server $server Server instance.
     * @param WP_REST_Request $request Request used to generate the response.
     * @return WP_REST_Response|null
     */
    protected function maybeThrottleRequest(
        mixed $response,
        WP_REST_Server $server,
        WP_REST_Request $request
    ): ?WP_REST_Response {
        // Bail if the interval is -1.
        if ($this->getInterval() <= -1) {
            return $response;
        }

        /**
         * Allow skipping throttle for certain requests
         */
        if (filter_var(apply_filters('wp_rest_cop_skip_throttle', false, $request, $server), FILTER_VALIDATE_BOOLEAN)) {
            return $response;
        }

        /** @var MeterMaid $maid */
        $maid = $this->getContainer()->get(ServiceProvider::METER_MAID);
        $meter = $maid->make($this->getClientId(), $this->getLimit(), $this->getInterval());

        // Don't throttle HEAD requests to let clients check details.
        if ($request->get_method() !== 'HEAD') {
            $meter->tick();
        }

        $server->send_headers($meter->getHeaders());

        if ($meter->isLimitExceeded()) {
            $data = [
                'code' => 'rate_limit_exceeded',
                'message' => esc_html__('Too many requests.', 'wp-rest-cop'),
                'data' => array_merge(
                    $meter->toArray(),
                    ['status' => WP_Http::TOO_MANY_REQUESTS]
                ),
            ];

            $response = rest_convert_error_to_response(new WP_Error(...$data));
        }

        $maid->save($meter);

        return $response;
    }

    /**
     * Check route-level settings for IP addresses.
     * @param mixed $response Existing response.
     * @param WP_REST_Request $request Request used to generate the response.
     * @return mixed
     */
    protected function checkRouteIpRules(mixed $response, WP_REST_Request $request): mixed
    {
        $ips = [];
        if (!empty($request->get_attributes()['ips'])) {
            $ips = $request->get_attributes()['ips'];
        }

        $rules = $ips instanceof IpRulesInterface ? $ips : new IpRules($ips);

        if (!$rules->check($this->getIpAddress())) {
            $response = $this->getForbiddenError();
        }

        return $response;
    }

    /**
     * Retrieve the default forbidden error.
     * @return WP_Error
     */
    private function getForbiddenError(): WP_Error
    {
        return new WP_Error(
            'rest_forbidden',
            esc_html__('You don\'t have permission to do this.', 'wp-rest-cop'),
            ['status' => WP_Http::FORBIDDEN]
        );
    }
}
