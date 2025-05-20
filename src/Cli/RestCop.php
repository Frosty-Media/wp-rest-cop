<?php

declare(strict_types=1);

namespace FrostyMedia\WpRestCop\Cli;

use FrostyMedia\WpRestCop\RestApi\Officer;
use FrostyMedia\WpRestCop\RestApi\Rules\IpRules;
use FrostyMedia\WpRestCop\RestApi\Rules\IpRulesInterface;
use FrostyMedia\WpRestCop\ServiceProvider;
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
	public function allow($args, $assoc_args): void
	{
		$this->updateOption(IpRulesInterface::ALLOW, $args, isset($assoc_args['delete']));
	}

	/**
	 * Deny IP addresses access to the REST API.
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
	public function deny($args, $assoc_args): void
	{
		$this->updateOption(IpRulesInterface::DENY, $args, isset($assoc_args['delete']));
	}

	/**
	 * Check the status of an IP address.
	 *
	 * ## OPTIONS
	 *
	 * <ip>
	 * : An IP address.
	 *
	 * @synopsis <ip>
	 */
	public function check($args, $assoc_args): void
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
	public function status($args, $assoc_args): void
	{
		$items = [];
		$labels = [
			esc_html__('IP Address', 'wp-rest-cop'),
			esc_html__('Action', 'wp-rest-cop'),
			esc_html__('Source', 'wp-rest-cop'),
		];

        $settings = Officer::getSettings();
		$action_l10n = esc_html__('ALLOW', 'wp-rest-cop');
		foreach ($this->getRules()->getAllowed() as $ip) {
			$source = 'code';
			if (in_array($ip, $settings['rules'][IpRulesInterface::ALLOW], true)) {
				$source = 'option';
			}

			$items[] = array_combine($labels, [$ip, $action_l10n, $source]);
		}

		$action_l10n = esc_html__('DENY', 'wp-rest-cop');
		foreach ($this->getRules()->getDenied() as $ip) {
			$source = 'code';
			if (in_array($ip, $settings['rules'][IpRulesInterface::DENY], true)) {
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
	 *
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
	public function set($args, $assoc_args): void
	{
        $settings = Officer::getSettings();

		if (!in_array($args[0], ['interval', 'limit'], true)) {
			WP_CLI::error(sprintf(esc_html__('%s is not a valid setting.', 'wp-rest-cop'), $args[0]));
		}

		$settings[$args[0]] = (int)$args[1];
		update_option(Officer::OPTION, $settings);
		WP_CLI::success(sprintf(esc_html__('Updated %s setting to %s.', 'wp-rest-cop'), $args[0], $args[1]));
	}

	/**
	 * Helper function to update an array option.
	 *
	 * @param string $key Option name.
	 * @param array $args Option values.
	 * @param boolean $delete Optional. Whether the passed values should be deleted from existing values. Default is to merge them.
	 */
	protected function updateOption(string $key, array $args, bool $delete = false): void
	{
        $settings = Officer::getSettings();
		$ips = $settings['rules'][$key] ?? [];
		$ips = $delete ? array_diff($ips, $args) : array_merge($ips, $args);
        $settings['rules'][$key] = array_unique(array_filter($ips));

		update_option(Officer::OPTION, $settings);
	}

	private function getRules(): IpRules
	{
		return $this->getContainer()->get(ServiceProvider::IP_RULES);
	}
}
