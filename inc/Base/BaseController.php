<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Base;

class BaseController {

	public $plugin_path;

	public $plugin_url;

	public $plugin;

	public function __construct() {
		$this->plugin_path          = plugin_dir_path( dirname( __FILE__, 2 ) );
		$this->plugin_relative_path = plugin_basename( dirname( __FILE__, 3 ) );
		$this->plugin_url           = plugin_dir_url( dirname( __FILE__, 2 ) );
		$this->plugin               = plugin_basename( dirname( __FILE__, 3 ) ) . '/sementes.php';
		$this->upload_base_dir		= wp_upload_dir()['basedir'];
		$this->token                = 'sementes-cas-gcrs';
		$this->assets_url           = 'assets/';
		$this->redux_opt_name       = 'eitagcr';
	}

	public function get_sementes_option( $option ) {
		$options = get_option( $this->redux_opt_name );
		if ( ! isset( $options[ $option ] ) ) {
			return null;
		}
		return $options[ $option ];
	}

	public function update_sementes_option( $option, $value ) {
		$options            = get_option( $this->redux_opt_name );
		$options[ $option ] = $value;
		update_option( $this->redux_opt_name, $options );
	}
}
