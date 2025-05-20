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

The rate limit functionality uses [transients](https://developer.wordpress.org/apis/transients/)
which will use [persistent object cache](https://codex.wordpress.org/Class_Reference/WP_Object_Cache) if present.

### Headers

A few headers are sent with every request so clients can keep track of their current limit:

| Header                   | Description                                                  |
|:-------------------------|:-------------------------------------------------------------|
| `X-Rate-Limit-Limit`     | Requests allowed per interval.                               |
| `X-Rate-Limit-Rules`     | Default rule set to client "Ip".                             | 
| `X-Rate-Limit-Remaining` | Remaining requests that are allowed in the current interval. | 
| `X-Rate-Limit-Reset`     | Seconds until the limit is reset.                            | 

If a client has reached their limit, an additional header will be sent:

| Header        | Description                       |
|:--------------|:----------------------------------|
| `Retry-After` | Seconds until the limit is reset. |

Clients may send a `HEAD` request to view their current limit without ticking the meter.

### Configuring Settings

Configure the default `interval` & `limit` settings using the simple API from the main plugin instance:

```php
<?php

use FrostyMedia\WpRestCop\RestApi\Officer;

/**
 * Set the rate limit to 1000 requests every hour.
 */
add_action( 'wp_rest_cop_plugin_loaded', static function(Officer $officer): void {
	$officer
		->setInterval(HOUR_IN_SECONDS)
		->setLimit(1000);
});
```

Settings can also be configured with the built-in [WP CLI commands](#wp-cli-commands).

### Disable Rate Limiting

If you just want the IP rules functionality and want to disable the rate limits, set the interval to `-1`. Or, filter
the current request:

```php
<?php

use FrostyMedia\WpRestCop\RestApi\Officer;

/**
 * Skip the rate limit throttle on current route or other conditions.
 */
add_filter( 'wp_rest_cop_skip_throttle', static function(
    bool $skip,
    WP_REST_Request $request,
    WP_REST_Server $server): bool {
	
	if ($request->get_route() === 'some/route') {
	    $skip = true;
	}
	return $skip;
}, 10, 3);
```

## IP Rules

IP rules can be configured globally, or at the route level as a simple allowlist or blocklist.

### Global Configuration

```php
<?php

use FrostyMedia\WpRestCop\RestApi\Rules\IpRulesInterface;
use FrostyMedia\WpRestCop\RestApi\Officer;

/**
 * Global IP rules configuration.
 */
add_action( 'wp_rest_cop_plugin_loaded', static function(Officer $officer, IpRulesInterface $ipRules): void {
	$ipRules->allow('192.168.50.4'); // Also accepts an array of IP addresses.

	// Or...
	$ipRules->deny('66.249.66.1'); // Also accepts an array of IP addresses.
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
        'methods' => 'GET',
        'callback' => 'my_awesome_expensive_func',
        'ips' => [
            IpRulesInterface::ALLOW => ['192.168.50.4'],
            IpRulesInterface::DENY => ['66.249.66.1'],
        ],
    ]);
});
```

## WP CLI Commands

A few [WP CLI](http://wp-cli.org/) commands are included to configure the plugin without requiring code.

| Command                        | Description                     |
|:-------------------------------|:--------------------------------|
| `wp restcop allow <ip>`        | Allow one or more IPs.          |
| `wp restcop check <ip>`        | Check whether an IP has access. |
| `wp restcop deny <ip>`         | Deny one or more IPs            |
| `wp restcop set <key> <value>` | Update a setting value.         |
| `wp restcop status`            | View global IP rules.           |

## Potential Roadmap

* Support for logging various events.
* Additional rate limit strategies.
* More route-level capabilities.
* Advanced access rules.
* Administration UI.
