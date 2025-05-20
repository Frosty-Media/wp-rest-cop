<?php

declare(strict_types=1);

namespace FrostyMedia\WpRestCop\RestApi;

use Dwnload\WpSettingsApi\Api\Options;
use FrostyMedia\WpRestCop\RestApi\Rules\IpRulesInterface;
use FrostyMedia\WpRestCop\ServiceProvider;
use FrostyMedia\WpRestCop\Settings\Settings;
use Psr\Container\ContainerInterface;
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
use function is_user_logged_in;
use function rest_authorization_required_code;
use function rest_convert_error_to_response;
use function sprintf;
use function update_option;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * RateLimit class.
 * @package FrostyMedia\WpRestCop\RestApi
 */
class Officer extends AbstractContainerProvider implements HttpFoundationRequestInterface
{

    use HttpFoundationRequestTrait;

    /**
     * Officer constructor.
     * @param ContainerInterface|null $container
     * @param int $interval
     * @param int $limit
     */
    public function __construct(
        ?ContainerInterface $container = null,
        protected int $interval = Settings::DEFAULT_INTERVAL,
        protected int $limit = Settings::DEFAULT_LIMIT
    ) {
        parent::__construct($container);
    }

    /**
     * Add class hooks.
     */
    public function addHooks(): void
    {
        $this->addAction('wp_rest_cop_plugin_loaded', [$this, 'initialize'], 0);
        $this->addAction('rest_api_init', [$this, 'restApiInit']);
        $this->addFilter('rest_authentication_errors', [$this, 'checkIpRules']);
        $this->addFilter('rest_pre_dispatch', [$this, 'maybeThrottleRequest'], 10, 3);
        $this->addFilter('rest_dispatch_request', [$this, 'checkRouteIpRules'], 10, 2);

        do_action('wp_rest_cop_plugin_loaded', $this);
    }

    /**
     * Retrieve an identifier for the current client.
     * If a user is logged in, their user ID will be used, otherwise, defaults
     * to the current client's IP address.
     * @return string
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
     * Retrieve the global rate limit interval.
     * @return int
     */
    public function getInterval(): int
    {
        return $this->interval;
    }

    /**
     * Set the number of seconds per interval.
     * @param int|string $interval Seconds per interval.
     * @return $this
     */
    public function setInterval(int|string $interval): static
    {
        $this->interval = (int)$interval;
        return $this;
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
     * @param int|string $limit Number of requests.
     * @return $this
     */
    public function setLimit(int|string $limit): static
    {
        $this->limit = (int)$limit;
        return $this;
    }

    /**
     * Trigger our initialization methods.
     */
    protected function initialize(): void
    {
        $this->initializeSettings();
        $this->initializeIpRules();
    }

    /**
     * Rest API Initiation.
     */
    protected function restApiInit(): void
    {
        do_action('wp_rest_cop_rest_api_init', $this, $this->getContainer()->get(ServiceProvider::IP_RULES));
    }

    /**
     * Initialize the settings options.
     */
    protected function initializeSettings(): void
    {
        $settings = Options::getOptions(Settings::SETTINGS);

        if (empty($settings)) {
            $settings = [
                Settings::SETTING_INTERVAL => Settings::DEFAULT_INTERVAL,
                Settings::SETTING_LIMIT => Settings::DEFAULT_LIMIT,
                Settings::SETTING_ALLOW_RULES => ['127.0.0.1', '::1'],
                Settings::SETTING_DENY_RULES => [],
            ];
            update_option(Settings::SETTINGS, $settings);
        }

        $this->setInterval($settings[Settings::SETTING_INTERVAL])->setLimit($settings[Settings::SETTING_LIMIT]);
    }

    /**
     * Initialize IP rules from options.
     * These will usually be set with WP CLI.
     */
    protected function initializeIpRules(): void
    {
        /** @var \FrostyMedia\WpRestCop\RestApi\Rules\IpRules $rules */
        $rules = $this->getContainer()->get(ServiceProvider::IP_RULES);
        $rules
            ->allow(Options::getOption(Settings::SETTING_ALLOW_RULES, Settings::SETTINGS))
            ->deny(Options::getOption(Settings::SETTING_DENY_RULES, Settings::SETTINGS));
    }

    /**
     * Check global IP address settings.
     * @param WP_Error|bool|null $error WP_Error if authentication error,
     *  null if authentication method wasn't used, true if authentication succeeded.
     * @return WP_Error|bool|null
     */
    protected function checkIpRules(WP_Error|bool|null $error): WP_Error|bool|null
    {
        /** @var \FrostyMedia\WpRestCop\RestApi\Rules\IpRules $rules */
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
        /**
         * Allow skipping throttle for certain requests or if the interval is -1.
         */
        if (
            $this->getInterval() === -1 ||
            filter_var(apply_filters('wp_rest_cop_skip_throttle', false, $request, $server), FILTER_VALIDATE_BOOLEAN)
        ) {
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
        if (!empty($request->get_attributes()[IpRulesInterface::IPS])) {
            $ips = $request->get_attributes()[IpRulesInterface::IPS];
        }

        /** @var \FrostyMedia\WpRestCop\RestApi\Rules\IpRules $rules */
        $rules = $this->getContainer()->get(ServiceProvider::IP_RULES);
        if (!$ips instanceof IpRulesInterface) {
            $rules->allow($ips[IpRulesInterface::ALLOW] ?? '')->deny($ips[IpRulesInterface::DENY] ?? '');
        }

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
            esc_html__('Sorry, you are not allowed to do that.', 'wp-rest-cop'),
            ['status' => rest_authorization_required_code()]
        );
    }
}
