<?php

declare(strict_types=1);

namespace FrostyMedia\WpRestCop\RestApi\Rules;

use function array_filter;
use function array_merge;
use function in_array;
use function is_string;

/**
 * IP address rules class.
 * @package Cedaro\WPRESTCop
 */
class IpRules implements IpRulesInterface
{
    /**
     * Allowlisted IP addresses.
     * @var array
     */
    protected array $allow = [];

    /**
     * Blocklisted IP addresses.
     * @var array
     */
    protected array $deny = [];

    /**
     * Constructor method.
     * @param array $rules Array of rules.
     */
    public function __construct(array $rules = [])
    {
        if (isset($rules['allow'])) {
            $this->allow($rules['allow']);
        }

        if (isset($rules['deny'])) {
            $this->deny($rules['deny']);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function check(string $ip): bool
    {
        if (!empty($this->allow) && !$this->isAllowed($ip)) {
            return false;
        }

        if (!empty($this->deny) && $this->isDenied($ip)) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function allow(array|string $ip): static
    {
        $allowed = is_string($ip) ? [$ip] : $ip;
        $this->allow = array_filter(array_merge($this->allow, $allowed));
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function deny(array|string $ip): static
    {
        $denied = is_string($ip) ? [$ip] : $ip;
        $this->deny = array_filter(array_merge($this->deny, $denied));
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getAllowed(): array
    {
        return $this->allow;
    }

    /**
     * {@inheritDoc}
     */
    public function getDenied(): array
    {
        return $this->deny;
    }

    /**
     * {@inheritDoc}
     */
    public function isAllowed(string $ip): bool
    {
        return in_array($ip, $this->allow, true);
    }

    /**
     * {@inheritDoc}
     */
    public function isDenied(string $ip): bool
    {
        return in_array($ip, $this->deny, true);
    }
}
