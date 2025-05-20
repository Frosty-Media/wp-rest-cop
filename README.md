# WP REST Cop

![WP Rest Cop](.github/wp-rest-cop.jpg?raw=true "WP Rest Cop")

[![PHP from Packagist](https://img.shields.io/packagist/php-v/Frosty-Media/wp-rest-cop.svg)]()
[![Latest Stable Version](https://img.shields.io/packagist/v/Frosty-Media/wp-rest-cop.svg)](https://packagist.org/packages/Frosty-Media/wp-rest-cop)
[![Total Downloads](https://img.shields.io/packagist/dt/Frosty-Media/wp-rest-cop.svg)](https://packagist.org/packages/Frosty-Media/wp-rest-cop)
[![License](https://img.shields.io/packagist/l/Frosty-Media/wp-rest-cop.svg)](https://packagist.org/Frosty-Media/wp-rest-cop)
![Build Status](https://github.com/Frosty-Media/wp-rest-cop/actions/workflows/main.yml/badge.svg)

Manage access to the WP REST API with rate limits and IP-based rules.

## Rate Limits

Rate limits allow for configuring the number of requests a client can make within a certain interval. 
The default in _WP Rest Cop_ is 60 requests per minute.

The rate limit functionality requires
a [persistent object cache](https://codex.wordpress.org/Class_Reference/WP_Object_Cache).

### Headers

A few headers are sent with every request so clients can keep track of their current limit:

<table width="100%">
    <thead>
        <th width="40%">Header</th>
        <th>Description</th>
    </thead>
    <tbody>
        <tr>
            <td><code>X-Rate-Limit-Limit</code></td>
            <td>Requests allowed per interval.</td>
        </tr>
        <tr>
            <td><code>X-Rate-Limit-Rules</code></td>
            <td>Rule set to "Ip".</td>
        </tr>
        <tr>
            <td><code>X-Rate-Limit-Remaining</code></td>
            <td>Remaining requests that are allowed in the current interval.</td>
        </tr>
        <tr>
            <td><code>X-Rate-Limit-Reset</code></td>
            <td>Seconds until the limit is reset.</td>
        </tr>
    </tbody>
</table>

If client has reached their limit, an additional header will be sent.

<table width="100%">
    <thead>
        <th width="40%">Header</th>
        <th>Description</th>
    </thead>
    <tbody>
        <tr>
            <td><code>Retry-After</code></td>
            <td>Seconds until the limit is reset</td>
        </tr>
    </tbody>
</table>

Clients may send a `HEAD` request to view their current limit without ticking the meter.

### Configuring Settings

Configure the default `limit` and `interval` settings using the simple API from the main plugin instance:

```php
<?php

use FrostyMedia\WpRestCop\RestApi\Officer;

/**
 * Set the rate limit to 10 requests every 5 minutes.
 */
add_action( 'wp_rest_cop_plugin_loaded', static function(Officer $officer): void {
	$officer
		->setLimit(10)
		->setInterval(5 * MINUTE_IN_SECOND );
});
```

Settings can also be configured with the built-in [WP CLI commands](#wp-cli-commands).

### Disable Rate Limiting

If you just want the IP rules functionality and want to disable the rate limits, set the interval to `-1`.

## IP Rules

IP rules can be configured globally, or at the route level as a simple whitelist or blacklist.

### Global Configuration

```php
<?php

use FrostyMedia\WpRestCop\RestApi\Rules\IpRulesInterface;
use FrostyMedia\WpRestCop\RestApi\Officer;

/**
 * Global IP rules configuration.
 */
add_action( 'wp_rest_cop_plugin_loaded', static function(Officer $officer, IpRulesInterface $ipRules): void {
	$ipRules->allow( '192.168.50.4' ); // Also accepts an array of IP addresses.

	// Or...
	$ipRules->deny( '66.249.66.1' ); // Also accepts an array of IP addresses.
}, 10, 2);
```

When allowing an IP address, the policy is to deny any requests from IPs not in the allowlist.

The opposite is true when denying IP addresses. All IPs not in the blocklist will have access.

Global IP rules can also be configured with the built-in [WP CLI commands](#wp-cli-commands).

### Route Configuration

Routes may also be configured with their own IP rules:

```php
<?php

use FrostyMedia\WpRestCop\RestApi\Rules\IpRulesInterface;

/**
 * Register routes.
 */
add_action( 'rest_api_init', static function (): void {
    register_rest_route( 'myplugin/v1', '/internal/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'my_awesome_expensive_func',
        'ips'      => [
            IpRulesInterface::ALLOW => [ '192.168.50.4' ],
            IpRulesInterface::DENY  => [ '66.249.66.1' ],
        ]
    ] );
} );
```

## WP CLI Commands

A few [WP CLI](http://wp-cli.org/) commands are included to configure the plugin without requiring code.

<table width="100%">
    <thead>
        <th>Command</th>
        <th>Description</th>
    </thead>
    <tbody>
        <tr>
            <td><code>wp restcop allow &lt;ip&gt;...</code></td>
            <td>Whitelist one or more IPs.</td>
        </tr>
        <tr>
            <td><code>wp restcop check &lt;ip&gt;</code></td>
            <td>Check whether an IP has access.</td>
        </tr>
        <tr>
            <td><code>wp restcop deny &lt;ip&gt;...</code></td>
            <td>Blacklist one or more IPs.</td>
        </tr>
        <tr>
            <td><code>wp restcop set &lt;key&gt; &lt;value&gt;</code></td>
            <td>Update a setting value.</td>
        </tr>
        <tr>
            <td><code>wp restcop status</code></td>
            <td>View global IP rules.</td>
        </tr>
    </tbody>
</table>

## Potential Roadmap

* Support for logging various events.
* Additional rate limit strategies.
* More route-level capabilities.
* Advanced access rules.
* Administration UI.
