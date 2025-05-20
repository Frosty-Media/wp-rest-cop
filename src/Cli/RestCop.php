<?php

declare(strict_types=1);

namespace FrostyMedia\WpRestCop\Cli;

use Dwnload\WpSettingsApi\Api\Options;
use FrostyMedia\WpRestCop\RestApi\Rules\IpRules;
use FrostyMedia\WpRestCop\RestApi\Rules\IpRulesInterface;
use FrostyMedia\WpRestCop\ServiceProvider;
use FrostyMedia\WpRestCop\Settings\Settings;
use TheFrosty\WpUtilities\Plugin\ContainerAwareTrait;
use WP_CLI;
use function array_diff;
use function array_filter;
use function array_merge;
use function array_unique;
use function esc_html__;
use function sprintf;
use function update_option;

/**
 * Manage access to the REST API.
 * @package FrostyMedia\WpRestCop\Cli
 */
class RestCop
{

    use ContainerAwareTrait;

    /**
     * Grant IP addresses access to the REST API.
     * @param array $args
     * @param array $assoc_args
     *
     * ## OPTIONS
     *
     * <ip>...
     * : One or more IP addresses.
     *
     * [--delete]
     * : Delete rules for the IP addresses.
     *
     * @synopsis <ip>... [--delete]
     */
    public function allow(array $args, array $assoc_args): void
    {
        $this->updateOption(IpRulesInterface::ALLOW, $args, isset($assoc_args['delete']));
    }

    /**
     * Deny IP addresses access to the REST API.
     * @param array $args
     * @param array $assoc_args
     *
     * ## OPTIONS
     *
     * <ip>...
     * : One or more IP addresses.
     *
     * [--delete]
     * : Delete rules for the IP addresses.
     *
     * @synopsis <ip>... [--delete]
     */
    public function deny(array $args, array $assoc_args): void
    {
        $this->updateOption(IpRulesInterface::DENY, $args, isset($assoc_args['delete']));
    }

    /**
     * Check the status of an IP address.
     * @param array $args
     * ## OPTIONS
     *
     * <ip>
     * : An IP address.
     *
     * @synopsis <ip>
     */
    public function check(array $args): void
    {
        if ($this->getRules()->check($args[0])) {
            WP_CLI::success(sprintf(esc_html__('%s is allowed to access the REST API.', 'wp-rest-cop'), $args[0]));
        } else {
            WP_CLI::warning(sprintf(esc_html__('%s is blocked from accessing the REST API.', 'wp-rest-cop'), $args[0]));
        }
    }

    /**
     * View IP address rules.
     */
    public function status(): void
    {
        $items = [];
        $labels = [
            esc_html__('IP Address', 'wp-rest-cop'),
            esc_html__('Action', 'wp-rest-cop'),
            esc_html__('Source', 'wp-rest-cop'),
        ];

        $settings = Options::getOption(Settings::SETTINGS);
        $action_l10n = esc_html__('ALLOW', 'wp-rest-cop');
        foreach ($this->getRules()->getAllowed() as $ip) {
            $source = 'code';
            if (in_array($ip, $settings[Settings::SETTING_ALLOW_RULES], true)) {
                $source = 'option';
            }

            $items[] = array_combine($labels, [$ip, $action_l10n, $source]);
        }

        $action_l10n = esc_html__('DENY', 'wp-rest-cop');
        foreach ($this->getRules()->getDenied() as $ip) {
            $source = 'code';
            if (in_array($ip, $settings[Settings::SETTING_DENY_RULES], true)) {
                $source = 'option';
            }

            $items[] = array_combine($labels, [$ip, $action_l10n, $source]);
        }

        $format_args = [];
        $formatter = new WP_CLI\Formatter($format_args, $labels);
        $formatter->display_items($items);
    }

    /**
     * Set a plugin setting.
     * @param array $args
     * ## OPTIONS
     *
     * <key>
     * : The name of the setting to update (limit or interval).
     *
     * <value>
     * : The new value.
     *
     * @synopsis <key> <value>
     */
    public function set(array $args): void
    {
        $settings = Options::getOption(Settings::SETTINGS);

        if (!in_array($args[0], [Settings::SETTING_INTERVAL, Settings::SETTING_LIMIT], true)) {
            WP_CLI::error(sprintf(esc_html__('%s is not a valid setting.', 'wp-rest-cop'), $args[0]));
        }

        $settings[$args[0]] = (int)$args[1];
        update_option(Settings::SETTINGS, $settings);
        WP_CLI::success(sprintf(esc_html__('Updated %1$s setting to %2$s.', 'wp-rest-cop'), $args[0], $args[1]));
    }

    /**
     * Helper function to update an array option.
     *
     * @param string $key Option name.
     * @param array $args Option values.
     * @param boolean $delete Optional. Whether the passed values should be deleted from existing values.
     *  Default is to merge them.
     */
    protected function updateOption(string $key, array $args, bool $delete = false): void
    {
        $update = static function(string $key) use ($args, $delete): void {
            $settings = Options::getOption(Settings::SETTINGS);
            $ips = $settings[$key] ?? [];
            $ips = $delete ? array_diff($ips, $args) : array_merge($ips, $args);
            $settings[$key] = array_unique(array_filter($ips));
            update_option(Settings::SETTINGS, $settings);
        };

        switch ($key) {
            case IpRulesInterface::ALLOW:
                $update(Settings::SETTING_ALLOW_RULES);
                break;
            case IpRulesInterface::DENY:
                $update(Settings::SETTING_DENY_RULES);
                break;
        }
    }

    /**
     * Get our IpRules object from the container.
     * @return IpRules
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function getRules(): IpRules
    {
        return $this->getContainer()->get(ServiceProvider::IP_RULES);
    }
}
