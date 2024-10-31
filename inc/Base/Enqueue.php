<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Base;

use SementesCasGcrs\Base\BaseController;

/**
 *
 */
class Enqueue extends BaseController {

	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'front_enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );

		
		require_once plugin_dir_path( dirname( __FILE__, 2 )  ) . 'inc/Api/class-tgm-plugin-activation.php';
		
		add_action( 'tgmpa_register', array( $this, 'activate_tgm' )); 

		if ( !class_exists( 'ReduxFramework' ) && file_exists( $this->plugin_path . '/vendor/redux-framework/redux-core/framework.php' ) ) {
			require_once $this->plugin_path . '/vendor/redux-framework/redux-core/framework.php';
		}
		

	}

	public function front_enqueue() {
		// enqueue all our scripts
		wp_register_style( $this->token . '-frontend', $this->plugin_url . 'assets/frontend.css' );
		wp_enqueue_style( $this->token . '-frontend' );

		wp_register_script( $this->token . '-frontend', $this->plugin_url . 'assets/frontend.js', array( 'jquery' ) );
		wp_enqueue_script( $this->token . '-frontend' );
	}

	public function admin_enqueue() {   

		wp_register_script( 'buttons-html5', $this->plugin_url . 'assets/buttons.html5.min.js' );
		wp_enqueue_script( 'buttons-html5' );
		wp_register_script( 'jszip', $this->plugin_url . 'assets/jszip.min.js' );
		wp_enqueue_script( 'jszip' );
		wp_register_script( 'pdfmake', $this->plugin_url . 'assets/pdfmake.min.js' );
		wp_enqueue_script( 'pdfmake' );
		wp_register_script( 'vfs-fonts', $this->plugin_url . 'assets/vfs_fonts.js' );
		wp_enqueue_script( 'vfs-fonts' );
		wp_register_script( 'dataTables-buttons', $this->plugin_url . 'assets/dataTables.buttons.min.js' );
		wp_enqueue_script( 'dataTables-buttons' );
		wp_register_script( 'jquery-dataTables-js', $this->plugin_url . 'assets/jquery.dataTables.min.js', array('jquery') );
		wp_enqueue_script( 'jquery-dataTables-js' );

		wp_register_style( 'jquery-ui', $this->plugin_url . 'assets/jquery-ui.css' );
		wp_enqueue_style( 'jquery-ui' );

		wp_register_style( 'jquery-ui-addon', $this->plugin_url . 'assets/jquery-ui-timepicker-addon.min.css' );
		wp_enqueue_style( 'jquery-ui-addon' );

		wp_register_style( 'jquery-dataTables-css', $this->plugin_url . 'assets/jquery.dataTables.min.css' );
		wp_enqueue_style( 'jquery-dataTables-css' );

		wp_register_style( 'buttons-dataTables', $this->plugin_url . 'assets/buttons.dataTables.min.css' );
		wp_enqueue_style( 'buttons-dataTables' );

		wp_enqueue_script( 'jquery-ui-datepicker' );

		wp_register_script( 'jquery-ui-timepicker-addon', $this->plugin_url . 'assets/jquery-ui-timepicker-addon.min.js', array( 'jquery' ), null, true );
		wp_enqueue_script( 'jquery-ui-timepicker-addon' );

		wp_register_style( $this->token . '-admin', $this->plugin_url . 'assets/admin.css' );
		wp_enqueue_style( $this->token . '-admin' );

		wp_register_script( $this->token . '-admin', $this->plugin_url . 'assets/admin.js', array( 'jquery' ) );
		wp_enqueue_script( $this->token . '-admin' );
		wp_add_inline_script(
			$this->token . '-admin',
			'const CYCLE_AJAX_VARS = ' . json_encode(
				array(
					'security_pre_cycle_publish_validation' => wp_create_nonce( 'pre_cycle_publish_validation' ),
					'security_get_cycle_report' => wp_create_nonce( 'get_cycle_report' ),
				)
			),
			'before'
		);
	}


	public function activate_tgm() {
		/*
		* Array of plugin arrays. Required keys are name and slug.
		* If the source is NOT from the .org repo, then source is also required.
		*/
		$plugins = array(  
			// This is an example of how to include a plugin from the WordPress Plugin Repository.
			array(
				'name'      => 'Redux Framework',
				'slug'      => 'redux-framework',
				'required'  => true,
				'activate_tgm' => true
			),   
		);
	
		/*
			* Array of configuration settings. Amend each line as needed.
			*
			* TGMPA will start providing localized text strings soon. If you already have translations of our standard
			* strings available, please help us make TGMPA even better by giving us access to these translations or by
			* sending in a pull-request with .po file(s) with the translations.
			*
			* Only uncomment the strings in the config array if you want to customize the strings.
			*/
		$config = array(
			'id'           => 'sementes-cas-gcrs',                 // Unique ID for hashing notices for multiple instances of TGMPA.
			'default_path' => '',                      // Default absolute path to bundled plugins.
			'menu'         => 'tgmpa-install-plugins', // Menu slug.
			'parent_slug'  => 'plugins.php',            // Parent menu slug.
			'capability'   => 'manage_options',    // Capability needed to view plugin install page, should be a capability associated with the parent menu used.
			'has_notices'  => true,                    // Show admin notices or not.
			'dismissable'  => true,                    // If false, a user cannot dismiss the nag message.
			'dismiss_msg'  => '',                      // If 'dismissable' is false, this message will be output at top of nag.
			'is_automatic' => true,                   // Automatically activate plugins after installation or not.
			'message'      => '',                      // Message to output right before the plugins table.
	
			
			'strings'      => array(
				'page_title'                      => __( 'Install Required Plugins', 'sementes-cas-gcrs' ),
				'menu_title'                      => __( 'Instalar Plugins', 'sementes-cas-gcrs' ),
				'installing'                      => __( 'Installing Plugin: %s', 'sementes-cas-gcrs' ),
				'updating'                        => __( 'Updating Plugin: %s', 'sementes-cas-gcrs' ),
				'oops'                            => __( 'Something went wrong with the plugin API.', 'sementes-cas-gcrs' ),
				'notice_can_install_required'     => _n_noop(
					'This theme requires the following plugin: %1$s.',
					'This theme requires the following plugins: %1$s.',
					'sementes-cas-gcrs'
				),
				'notice_can_install_recommended'  => _n_noop(
					'This theme recommends the following plugin: %1$s.',
					'This theme recommends the following plugins: %1$s.',
					'sementes-cas-gcrs'
				),
				'notice_ask_to_update'            => _n_noop(
					'The following plugin needs to be updated to its latest version to ensure maximum compatibility with this theme: %1$s.',
					'The following plugins need to be updated to their latest version to ensure maximum compatibility with this theme: %1$s.',
					'sementes-cas-gcrs'
				),
				'notice_ask_to_update_maybe'      => _n_noop(
					'There is an update available for: %1$s.',
					'There are updates available for the following plugins: %1$s.',
					'sementes-cas-gcrs'
				),
				'notice_can_activate_required'    => _n_noop(
					'The following required plugin is currently inactive: %1$s.',
					'The following required plugins are currently inactive: %1$s.',
					'sementes-cas-gcrs'
				),
				'notice_can_activate_recommended' => _n_noop(
					'The following recommended plugin is currently inactive: %1$s.',
					'The following recommended plugins are currently inactive: %1$s.',
					'sementes-cas-gcrs'
				),
				'install_link'                    => _n_noop(
					'Begin installing plugin',
					'Begin installing plugins',
					'sementes-cas-gcrs'
				),
				'update_link' 					  => _n_noop(
					'Begin updating plugin',
					'Begin updating plugins',
					'sementes-cas-gcrs'
				),
				'activate_link'                   => _n_noop(
					'Begin activating plugin',
					'Begin activating plugins',
					'sementes-cas-gcrs'
				),
				'return'                          => __( 'Return to Required Plugins Installer', 'sementes-cas-gcrs' ),
				'plugin_activated'                => __( 'Plugin activated successfully.', 'sementes-cas-gcrs' ),
				'activated_successfully'          => __( 'The following plugin was activated successfully:', 'sementes-cas-gcrs' ),
				'plugin_already_active'           => __( 'No action taken. Plugin %1$s was already active.', 'sementes-cas-gcrs' ),
				'plugin_needs_higher_version'     => __( 'Plugin not activated. A higher version of %s is needed for this theme. Please update the plugin.', 'sementes-cas-gcrs' ),
				'complete'                        => __( 'All plugins installed and activated successfully. %1$s', 'sementes-cas-gcrs' ),
				'dismiss'                         => __( 'Dismiss this notice', 'sementes-cas-gcrs' ),
				'notice_cannot_install_activate'  => __( 'There are one or more required or recommended plugins to install, update or activate.', 'sementes-cas-gcrs' ),
				'contact_admin'                   => __( 'Please contact the administrator of this site for help.', 'sementes-cas-gcrs' ),
	
				'nag_type'                        => '', // Determines admin notice type - can only be one of the typical WP notice classes, such as 'updated', 'update-nag', 'notice-warning', 'notice-info' or 'error'. Some of which may not work as expected in older WP versions.
			),
			
		);
	
		tgmpa( $plugins, $config );
	}

	
}
