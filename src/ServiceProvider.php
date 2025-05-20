<?php

declare(strict_types=1);

namespace FrostyMedia\WpRestCop;

use FrostyMedia\WpRestCop\RestApi\MeterMaid;
use FrostyMedia\WpRestCop\RestApi\Rules\IpRules;
use Pimple\Container as PimpleContainer;
use Pimple\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ServiceProvider
 * @package FrostyMedia\WpRestCop
 */
class ServiceProvider implements ServiceProviderInterface
{

	public const string IP_RULES = 'ip_rules';
	public const string METER_MAID = 'meter_maid';
	public const string REQUEST = 'request';

	/**
	 * Register services.
	 * @param PimpleContainer $pimple Container instance.
	 */
	public function register(PimpleContainer $pimple): void
	{
		$pimple[self::IP_RULES] = static fn(): IpRules => new IpRules();

		$pimple[self::METER_MAID] = static fn(): MeterMaid => new MeterMaid();

		$pimple[self::REQUEST] = static fn(): Request => Request::createFromGlobals();
	}
}
