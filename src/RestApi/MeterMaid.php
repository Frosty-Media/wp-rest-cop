<?php

declare(strict_types=1);

namespace FrostyMedia\WpRestCop\RestApi;

use TheFrosty\WpUtilities\Api\TransientsTrait;
use function delete_transient;

/**
 * MeterMaid class.
 * @package FrostyMedia\WpRestCop\RestApi
 */
class MeterMaid
{

	use TransientsTrait;

	/**
	 * Make or retrieve an existing meter from cache by id.
	 * @param int|string $id Unique key to identify the current client.
	 * @param int $limit Number of requests allowed per interval.
	 * @param int $interval Seconds per interval.
	 * @return Meter
	 */
	public function make(int|string $id, int $limit, int $interval): Meter
	{
		$meter = $this->getTransient($this->getKey($id));

		if (!$meter instanceof Meter) {
			$meter = new Meter($id, $limit, $interval);
		}

		if ($meter->getReset() <= 0) {
			delete_transient($this->getKey($meter->getId()));
			return (new self())->make($id, $limit, $interval);
		}

		return $meter;
	}

	/**
	 * Save a meter.
	 * @param Meter $meter Meter instance.
	 */
	public function save(Meter $meter): void
	{
		$this->setTransient($this->getKey($meter->getId()), $meter, $meter->getReset());
	}

	/**
	 * Retrieve a key to use for storage.
	 * @param int|string $id Meter identifier.
	 * @return string
	 */
	protected function getKey(int|string $id): string
	{
		return $this->getTransientKey((string)$id, 'wp_rest_cop_meter_');
	}
}
