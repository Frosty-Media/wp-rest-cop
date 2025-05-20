<?php

declare(strict_types=1);

namespace FrostyMedia\WpRestCop\RestApi;

use JsonSerializable;
use function time;

/**
 * Meter class.
 * @package FrostyMedia\WpRestCop\RestApi
 */
class Meter implements JsonSerializable
{

    /**
     * Number of remaining ticks allowed in the interval.
     * @var int $remaining
     */
    protected int $remaining;

    /**
     * The interval start time.
     * @var int $start
     */
    protected int $start;

    /**
     * Meter constructor.
     * @param int|string $id Unique key to identify the current client.
     * @param int $limit Number of ticks allowed per interval.
     * @param int $interval Seconds per interval.
     */
    public function __construct(protected string|int $id, protected int $limit, protected int $interval)
    {
        $this->remaining = $this->limit;
        $this->start = time();
    }

    /**
     * Retrieve the meter identifier.
     * @return int|string
     */
    public function getId(): int|string
    {
        return $this->id;
    }

    /**
     * Retrieve rate limit headers.
     * @ref https://developer.okta.com/docs/reference/rl-best-practices/
     * @return string[]
     */
    public function getHeaders(): array
    {
        $headers = [
            'X-Rate-Limit-Limit' => $this->getLimit(),
            'X-Rate-Limit-Rules' => 'Ip',
            'X-Rate-Limit-Remaining' => $this->getRemaining(),
            'X-Rate-Limit-Reset' => $this->getReset(),
        ];

        if ($this->isLimitExceeded()) {
            $headers['Retry-After'] = $this->getReset();
            $headers['X-RateLimit-Remaining'] = 0;
        }

        return $headers;
    }

    /**
     * Retrieve the number of ticks allowed in the interval.
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Whether the limit has been exceeded.
     * @return bool
     */
    public function isLimitExceeded(): bool
    {
        return apply_filters('wp_rest_cop_is_limit_exceeded', (0 > $this->getRemaining()), $this->getId(), $this);
    }

    /**
     * Retrieve the number of ticks allowed before the limit is reached.
     * @return int
     */
    public function getRemaining(): int
    {
        return apply_filters('wp_rest_cop_remaining', $this->remaining, $this->getId(), $this);
    }

    /**
     * Retrieve the number of seconds until the meter resets.
     * @return int
     */
    public function getReset(): int
    {
        return $this->start + $this->interval - time();
    }

    /**
     * Update the remaining counter.
     * @param int $tick The number of hits.
     */
    public function tick(int $tick = 1): void
    {
        $this->remaining -= $tick;
    }

    /**
     * Convert the meter to an array.
     * @return string[]
     */
    public function toArray(): array
    {
        return [
            'limit' => $this->getLimit(),
            'remaining' => $this->isLimitExceeded() ? 0 : $this->getRemaining(),
            'reset' => $this->getReset(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
