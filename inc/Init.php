<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs;

final class Init
{
	/**
	 * Store all the classes inside an array
	 * @return array Full list of classes
	 */
	public static function get_services()
	{
		return [
			Base\DatabaseController::class,
			Base\LocalizationController::class,
			Base\Enqueue::class,
			Base\SettingsLinks::class,
			Base\DashboardController::class,
			Base\GeneralSettingsController::class,
			Base\CycleController::class,
			Base\DeliveryPlaceController::class,
			Base\ReportController::class,
			Base\AnalyticsController::class,
			Base\WpAdminDashboardController::class,
		];
	}

	/**
	 * Loop through the classes, initialize them,
	 * and call the register() method if it exists
	 * @return
	 */
	public static function register_services()
	{
		foreach (self::get_services() as $class) {
			$service = self::instantiate($class);
			if (method_exists($service, 'register')) {
				$service->register();
			}
		}
	}

	/**
	 * Initialize the class
	 * @param  class $class    class from the services array
	 * @return class instance  new instance of the class
	 */
	private static function instantiate($class)
	{
		$service = new $class();

		return $service;
	}
}
