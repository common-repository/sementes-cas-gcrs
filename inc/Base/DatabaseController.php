<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Base;

use SementesCasGcrs\Base\BaseController;

/**
 *
 */
class DatabaseController extends BaseController {

	public function register() {
		$this->update_db();
	}

	public function update_db() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$current_version = get_plugins()['sementes-cas-gcrs/sementes.php']['Version'];
		$stored_version = get_option( 'sementes_version' );

		if ( !isset($stored_version) || $current_version != $stored_version ) {
			$this->_create_db();
			update_option( 'sementes_version', $current_version );
		}
	}

	/**
	 * Create the necessary database tables
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	function _create_db() { //phpcs:ignore
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'eitagcr_report';

		$sql = "CREATE TABLE `$table_name` (
			`order_item_id` int(11) NOT NULL,
			`order_id` int(11) NOT NULL,
			`cycle_id` int(11) NOT NULL,
			`cycle_name` text,
			`cycle_start_date` datetime DEFAULT NULL,
			`order_datetime` datetime NOT NULL,
			`order_first_name` text,
			`order_last_name` text,
			`order_delivery_place_id` int(11) DEFAULT NULL,
			`order_delivery_place` text,
			`order_payment_method_title` text,
			`order_phone` text DEFAULT NULL,
			`order_shipping_postcode` text DEFAULT NULL,
			`order_address_1` text DEFAULT NULL,
			`order_address_2` text DEFAULT NULL,
			`order_zone` text DEFAULT NULL,
			`order_shipping_method_id` text DEFAULT NULL,
			`order_shipping_method_type` text DEFAULT NULL,
			`order_shipping_method_name` text DEFAULT NULL,
			`order_shipping_city` text DEFAULT NULL,
			`order_shipping_state` text DEFAULT NULL,
			`order_item_product_id` int(11) NOT NULL,
			`order_item_product` text NOT NULL,
			`order_item_category_id` int(11) NOT NULL,
			`order_item_category` text NOT NULL,
			`order_item_quantity` float NOT NULL,
			`order_item_unit_price` float NOT NULL,
			`order_item_unit_tax` float DEFAULT NULL,
			`order_item_price` float NOT NULL,
			`order_item_tax` float DEFAULT NULL,
			`order_item_stock` float DEFAULT NULL

		) $charset_collate;

		ALTER TABLE `wp_eitagcr_report`
		  ADD PRIMARY KEY (`order_item_id`),
		  ADD KEY `order_id` (`order_id`),
			ADD KEY `cycle_id` (`cycle_id`),
			ADD KEY `product_id` (`product_id`),
			ADD KEY `category_id` (`category_id`);
		COMMIT;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
