<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Base;

use SementesCasGcrs\Base\BaseController;

/**
 *
 */
class LocalizationController extends BaseController {

	public function register() {
		$this->load_plugin_textdomain();

		// Handle localization.
		add_action( 'init', array( $this, 'load_localization' ), 0 );
	}

	/**
	 * Load plugin localization
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_localization() {
		load_plugin_textdomain( 'sementes-cas-gcrs', false, $this->plugin_relative_path . '/lang/' );
	}

	/**
	 * Load plugin textdomain
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_plugin_textdomain() {
		$domain = 'sementes-cas-gcrs';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		// load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		$var = load_plugin_textdomain( 'sementes-cas-gcrs', false, $this->plugin_relative_path . '/lang/' );
	}
}
