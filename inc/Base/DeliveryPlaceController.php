<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Base;

use SementesCasGcrs\Base\BaseController;
use SementesCasGcrs\Api\CustomPostTypeApi;
use Redux;

/**
 *
 */
class DeliveryPlaceController extends BaseController {


	public function register() {
		if ( $this->get_sementes_option( 'enable_delivery_place' ) ) {
			$this->register_delivey_place();

			add_filter( 'woocommerce_checkout_fields', array( $this, 'eitagrc_add_delivery_place_to_checkout' ) );

			add_action( 'save_post', array( $this, 'eitagrc_init_cycle' ), 10, 3 );

			// Move the "example_cpt" Custom-Post-Type to be a submenu of the "example_parent_page_id" admin page.
			add_action( 'admin_menu', array( $this, 'fix_delivey_place_admin_menu_submenu' ), 11 );
			add_filter( 'parent_file', array( $this, 'fix_delivey_place_admin_parent_file' ) );

		}

	}

	public function fix_delivey_place_admin_menu_submenu() {
		// Add "Example CPT" Custom-Post-Type as submenu of the "Example Parent Page" page
		add_submenu_page( 'eitagcr_panel', __( 'Pickup places', 'sementes-cas-gcrs' ), __( 'Pickup places', 'sementes-cas-gcrs' ), 'edit_pages', 'edit.php?post_type=deliveryplace' );
	}

	public function fix_delivey_place_admin_parent_file( $parent_file ) {
		global $submenu_file, $current_screen;

		// Set correct active/current menu and submenu in the WordPress Admin menu for the "example_cpt" Add-New/Edit/List
		if ( $current_screen->post_type == 'deliveryplace' ) {
			$submenu_file = 'edit.php?post_type=deliveryplace';
			$parent_file  = 'eitagcr_panel';
		}
		return $parent_file;
	}


	public function register_delivey_place() {
		$cpt_api = new CustomPostTypeApi(
			'deliveryplace',
			__( 'Pickup places', 'sementes-cas-gcrs' ),
			__( 'Pickup place', 'sementes-cas-gcrs' ),
			'',
			array()
		);
	}
	public function eitagrc_add_delivery_place_to_checkout( $fields ) {

		$args = array(
			'post_type'      => 'deliveryplace',
			'posts_per_page' => -1,
		);

		$deliveryplaces = array();
		$places         = get_posts( $args );

		$cycle          = get_option( 'sementes_active_cycle' );
		$places_enabled = get_post_meta( $cycle, 'cycle_delivery_places', true );

		// backwards compatibility
		if ( ! $places_enabled ) {
			$places_enabled = array();
			foreach ( $places as $place ) {
				$places_enabled[] = $place->ID;
			}
		}

		foreach ( $places as $place ) {
			if ( in_array( $place->ID, $places_enabled ) ) {
				$deliveryplaces[ $place->ID ] = $place->post_title;
			}
		}

		$fields['order']['shipping_deliveryplace'] = array(
			'type'     => 'radio',
			'label'    => __( 'Select the pickup place', 'sementes-cas-gcrs' ),
			'required' => true,
			'options'  => $deliveryplaces,
		);
		return $fields;
	}

	public function eitagrc_init_cycle( $cycle_ID, $post, $update ) {
		// Acts only for newly created delivery places
		if ( ! $update ) {
			$args = array(
				'post_type'      => 'deliveryplace',
				'posts_per_page' => -1,
			);

			$deliveryplaces = array();
			$places         = get_posts( $args );
			foreach ( $places as $place ) {
				$deliveryplaces[] = $place->ID;
			}
			update_post_meta( $cycle_ID, 'cycle_delivery_places', $deliveryplaces );
		}
	}



}
