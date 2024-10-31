<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Base;

use SementesCasGcrs\Base\DatabaseController;


class Activate {

	public static function activate() {
		

		if ( class_exists( 'Woocommerce' ) ) {
			self::install();
		} else {
			// Stop activation redirect and show error
			wp_die( __( 'Please install and activate WooCommerce.', 'woocommerce-addon-slug' ), 'Plugin Activation Error', array( 'back_link' => true ) );
		}


		
	}

	public static function install() {
		flush_rewrite_rules();

		$default = array();

		if ( ! get_option( 'eitagcr_plugin' ) ) {
			update_option( 'eitagcr_plugin', $default );
		}

		if ( ! get_option( 'eitagcr_plugin_cpt' ) ) {
			update_option( 'eitagcr_plugin_cpt', $default );
		}
	}

	 /**
	* Register the required plugins for this theme.
	*
	* In this example, we register five plugins:
	* - one included with the TGMPA library
	* - two from an external source, one from an arbitrary source, one from a GitHub repository
	* - two from the .org repo, where one demonstrates the use of the `is_callable` argument
	*
	* The variables passed to the `tgmpa()` function should be:
	* - an array of plugin arrays;
	* - optionally a configuration array.
	* If you are not changing anything in the configuration array, you can remove the array and remove the
	* variable from the function call: `tgmpa( $plugins );`.
	* In that case, the TGMPA default settings will be used.
	*
	* This function is hooked into `tgmpa_register`, which is fired on the WP `init` action on priority 10.
	*/
	
   
}
