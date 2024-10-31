<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Base;

use SementesCasGcrs\Base\BaseController;
use Redux;

/**
 *
 */
class GeneralSettingsController extends BaseController {


	public $callbacks;

	public $cycle_callbacks;

	public $subpages = array();

	public function register() {
		// add_action( 'plugins_loaded', array( $this, 'set_page'), 10, 1 );
		// $this->set_page();

		// hook used to control submenu order
		add_action( 'plugins_loaded', array( $this, 'set_page' ), 12, 1 );

	}

	public function set_page() {

		$html = '<button class="button-secondary" name="generate_report" value="true" type="submit">' . __( 'Generate Report', 'sementes-cas-gcrs' ) . '</button>';

		$opt_name = $this->redux_opt_name;
		Redux::set_section(
			$opt_name,
			array(
				'title'            => esc_html__( 'Settings', 'sementes-cas-gcrs' ),
				'id'               => 'sementes-settings',
				'class'            => 'sementes-settings',
				'subsection'       => false,
				'customizer_width' => '450px',
				'fields'           => array(
					array(
						'id'      => 'is_manual_preference_mode',
						'title'   => __( 'Preference for cycle', 'sementes-cas-gcrs' ),
						'desc'    => __( 'Define which type of cycle will have priority if a manual cycle and an automatic cycle are open at the same time.', 'sementes-cas-gcrs' ),
						'type'    => 'switch',
						'default' => false,
						'off'     => __( 'Automatic (date/time)', 'sementes-cas-gcrs' ),
						'on'      => __( 'Manual', 'sementes-cas-gcrs' ),
					),
					array(
						'id'    => 'page_open',
						'title' => __( 'Home page when cycle is open', 'sementes-cas-gcrs' ),
						'desc'  => __( 'Choose the page used for home when cycle is open.', 'sementes-cas-gcrs' ),
						'type'  => 'select',
						'data'  => 'pages',
					),
					array(
						'id'    => 'page_closed',
						'title' => __( 'Home page when cycle is closed', 'sementes-cas-gcrs' ),
						'desc'  => __( 'Choose the page used for home when cycle is closed.', 'sementes-cas-gcrs' ),
						'type'  => 'select',
						'data'  => 'pages',
					),
					array(
						'id'      => 'warning_time',
						'title'   => __( 'Warning time threshold (minutes)', 'sementes-cas-gcrs' ),
						'desc'    => __( 'Set the amount of time to show a warning before the cycle closes.', 'sementes-cas-gcrs' ),
						'type'    => 'slider',
						'max'     => '120',
						'default' => 0,
					),
					array(
						'id'    => 'enable_delivery_place',
						'title' => __( 'Enable pickup place feature', 'sementes-cas-gcrs' ),
						'desc'  => __( 'Mark here to be able to set different pickup places for each order.', 'sementes-cas-gcrs' ),
						'type'  => 'checkbox',
					),
					array(
						'id'       => 'cycle_delivery_places',
						'title'    => __( 'Cycle pickup places', 'sementes-cas-gcrs' ),
						'desc'     => __( 'Uncheck pickup place to disable it for this cycle', 'sementes-cas-gcrs' ),
						'type'     => 'checkbox',
						'data'     => 'callback',
						'args'     => array( $this, 'get_delivery_places' ),
						'required' => array( 'enable_delivery_place', '=', true ),
					),
					array(
						'id' => 'section_start',
						'type' => 'section',
						'title' => __('Admin Section', 'sementes-cas-gcrs'),
						'subtitle' => __('This section should only be used by developers and admins.', 'sementes-cas-gcrs'),
						'indent' => true 
					),
					array(
						'id'    => 'enable_simplified_dashboard',
						'title' => __( 'Enable simplified dashboard', 'sementes-cas-gcrs' ),
						'desc'  => __( 'Mark here to activate a simplified dashboard.', 'sementes-cas-gcrs' ),
						'type'  => 'checkbox',
						'default' => false,
					),
					array(
						'id'    => 'enable_tax',
						'title' => __( 'Enable tax feature', 'sementes-cas-gcrs' ),
						'desc'  => __( 'Mark here to be able to visualize tax info on reports.', 'sementes-cas-gcrs' ),
						'type'  => 'checkbox',
						'default' => false,
					),
					array(
						'id'      => 'supplier_taxonomy',
						'title'   => __( 'Which taxonomy represents suppliers?', 'sementes-cas-gcrs' ),
						'desc'    => __( 'Choose the among your product taxonomies which one represents the suppliers. This is used to generate supplier related reports.', 'sementes-cas-gcrs' ),
						'type'    => 'select',
						'data'    => 'callback',
						'args'    => array( $this, 'get_product_taxonomies' ),
						'default' => 'product_cat',
					),
					array(
						'id'       => 'supplier_taxonomy_name',
						'title'    => __( 'Which name represents suppliers?', 'sementes-cas-gcrs' ),
						'desc'     => __( 'e.g.: suppliers, enterprises, settlements ...', 'sementes-cas-gcrs' ),
						'type'     => 'text',
						'validate' => 'not_empty',
						'default'  => __( 'Suppliers', 'sementes-cas-gcrs' ),
					),
					array(
						'id'      => 'generate_report',
						'title'       => __( 'Update report', 'sementes-cas-gcrs' ),
						'type'    => 'raw',
						'content' => $html,
					),
				),
			)
		);

	}

	public function get_product_taxonomies() {
		$product_taxonomies     = array();
		$product_taxonomies_obj = get_object_taxonomies( 'product', 'objects' );
		foreach ( $product_taxonomies_obj as $product_taxonomy ) {
			$product_taxonomies[ $product_taxonomy->name ] = $product_taxonomy->label;
		}

		return $product_taxonomies;
	}

	public function get_delivery_places() {

		$delivery_places = array();
		if ( $this->get_sementes_option( 'enable_delivery_place' ) == true ) {
			$args                = array(
				'post_type'      => 'deliveryplace',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			);
			$all_delivery_places = get_posts( $args );
			if ( count( $all_delivery_places ) > 0 ) {
				foreach ( $all_delivery_places as $dp ) {
					$delivery_places[ $dp->ID ] = $dp->post_title;
				}
			}
		}

		return $delivery_places;
	}


}
