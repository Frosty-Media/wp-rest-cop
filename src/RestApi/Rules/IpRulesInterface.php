<?php

declare(strict_types=1);

namespace FrostyMedia\WpRestCop\RestApi\Rules;

/**
 * Access rules interface.
 * @package Cedaro\WPRESTCop
 */
interface IpRulesInterface
{
    public const string ALLOW = 'allow';
    public const string DENY = 'deny';

    /**
     * Whether client ID passes allowed and denied checks.
     * @param string $ip Client identifier to test.
     * @return bool
     */
    public function check(string $ip): bool;

    /**
     * Allowlist; one or more clients.
     * @param array|string $ip Client identifier(s).
     * @return $this
     */
    public function allow(array|string $ip): static;

    /**
     * Blocklist; one or more clients.
     * @param string $ip Client identifier(s).
     * @return $this
     */
    public function deny(string $ip): static;

    /**
     * Retrieve allowed clients.
     * @return string[]
     */
    public function getAllowed(): array;

    /**
     * Retrieve denied clients.
     * @return string[]
     */
    public function getDenied(): array;

    /**
     * Whether a client is allowed.
     * @param string $ip Client identifier string.
     * @return bool
     */
    public function isAllowed(string $ip): bool;

    /**
     * Whether a client is denied.
     * @param string $ip Client identifier string.
     * @return bool
     */
    public function isDenied(string $ip): bool;
}
