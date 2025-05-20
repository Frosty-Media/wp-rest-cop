<?php
/**
 * Plugin Name: WP REST Cop
 * Plugin URI: https://github.com/rosty-Media/wp-rest-cop
 * Description: Manage access to the WP REST API.
 * Version: 2.0.0
 * Author: Austin Passy
 * Author URI: https://austin.passy.co
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.8
 * Tested up to: 6.8.1
 * Requires PHP: 8.3
 * Plugin URI: https://github.com/Frosty-Media/wp-rest-cop
 * GitHub Plugin URI: https://github.com/Frosty-Media/wp-rest-cop
 * Primary Branch: develop
 * Release Asset: true
 */

namespace FrostyMedia\WpRestCop;

defined('ABSPATH') || exit;

use FrostyMedia\WpRestCop\RestApi\Officer;
use ReflectionMethod;
use TheFrosty\WpUtilities\Plugin\PluginFactory;
use TheFrosty\WpUtilities\WpAdmin\DisablePluginUpdateCheck;
use WP_CLI;
use function defined;

if (is_readable(__DIR__ . '/vendor/autoload.php')) {
    include_once __DIR__ . '/vendor/autoload.php';
}

$plugin = PluginFactory::create('wp-rest-cop');
$container = $plugin->getContainer();
$container->register(new ServiceProvider());

$plugin
    ->add(new DisablePluginUpdateCheck())
    ->addOnHook(Officer::class, 'rest_api_init', priority: 5, args: [$container])
    ->initialize();

// Make sure we set up our default settings.
register_activation_hook(__FILE__, static function () use ($container): void {
    $cop = new Officer();
    $cop->setContainer($container);
    $initializeSettings = new ReflectionMethod(Officer::class, 'initializeSettings');
    $initializeSettings->invoke($cop);
});

/**
 * Register our CLI command.
 */
if (defined('WP_CLI') && WP_CLI) {
    $cli = (new Cli\RestCop())->setContainer($container);
    WP_CLI::add_command('restcop', $cli);
}
