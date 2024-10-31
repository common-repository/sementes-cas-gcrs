<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Base;

use SementesCasGcrs\Base\BaseController;
use SementesCasGcrs\Api\Callbacks\ReportCallbacks;
use Redux;

/**
 *
 */
class ReportController extends BaseController {

	public $settings;

	public $callbacks;

	public $subpages = array();

	public function register() {
		$this->callbacks = new ReportCallbacks();

		add_action( 'plugins_loaded', array( $this, 'set_sub_page_redux' ), 10, 1 );

		add_action( 'init', array( $this, 'process_generate_report_requisition' ), 10, 1 );

		add_action( 'init', array( $this, 'process_download_report_requisition' ), 10, 1 );

		// $this->set_sub_page_redux();

		// $this->process_generate_report_requisition();

		add_action( 'wp_ajax_get_cycle_report', array( $this, 'ajax_get_report' ) );

		// Add cycle data to order
		add_action( 'woocommerce_new_order', array( $this->callbacks, 'eitagcr_add_cycle_to_order' ) );

		// After an order is saved, feed eitagcr table
		add_action( 'woocommerce_after_order_object_save', array( $this->callbacks, 'sementes_proccess_order_object_save' ), 10, 2 );

	}

	public function process_download_report_requisition() {
		// Download report
		if ( isset( $_POST['report_data'] ) && $_POST['report_data'] ) {
			$report_data = array_map( 'sanitize_text_field', json_decode( stripslashes( $_POST['report_data'] ), true ) );
			$form_data = array_map( 'sanitize_text_field', $_POST['eitagcr'] );
			$report_data_id = $report_data['id'];
			$report_data_format = $report_data['format'];
			$active_cycle = get_option( 'sementes_last_active_cycle' );
			if ( $report_data_id == 'report_shipping_tag' ) {
				$current_form_cycle_id = $form_data[ 'cycle_report_7' ];
				$orderby    = $this->get_sementes_option( 'orderby_report_7');
				$includetax = $this->get_sementes_option( 'includetax_report_7' );
			} else {
				$current_form_cycle_id = $form_data[ 'cycle_' . $report_data_id ];
				$orderby    = $this->get_sementes_option( 'orderby_' . $report_data_id );
				$includetax = $this->get_sementes_option( 'includetax_' . $report_data_id );
			}

			$cycle_id        = $this->callbacks->get_cycle_id_for_report( $current_form_cycle_id, $active_cycle->ID, true );

			$extra_configs['include_client_info_report_1'] =  $this->get_sementes_option( 'include_client_info_report_1' );
			$extra_configs['include_client_info_report_4'] =  $this->get_sementes_option( 'include_client_info_report_4' );
			$extra_configs['remove_local_pickup_report_7'] =  $this->get_sementes_option( 'remove_local_pickup_report_7' );

			$this->callbacks->download_report( $report_data_id, $cycle_id, $report_data_format, $orderby, $includetax, $extra_configs );

		}
	}

	public function ajax_get_report() {
		check_ajax_referer( 'get_cycle_report', 'security' );

		$html   = '';
		$values = array();
		if ( isset( $_POST['form_data'] ) ) {
			parse_str( $_POST['form_data'], $values );
			$values = array_map( 'sanitize_text_field', $values['eitagcr'] );
		}

		check_ajax_referer( 'get_cycle_report', 'security' );

		$report_id       = intval( sanitize_text_field( $values['redux-section'] ) ) - 2;
		$current_form_cycle_id = intval( sanitize_text_field( $values[ 'cycle_report_' . $report_id ] ) );
		$includetax = false;
		if ( array_key_exists( 'includetax_report_' . $report_id, $values ) ) {
			$includetax      = sanitize_text_field( $values[ 'includetax_report_' . $report_id ] );
		}
		$orderby = 'DateTime';
		if ( array_key_exists( 'orderby_report_' . $report_id, $values ) ) {
			$orderby = sanitize_text_field( $values[ 'orderby_report_' . $report_id ] );
		}
		$extra_configs = array();
		if ( array_key_exists( 'include_client_info_report_1', $values ) ) {
			$extra_configs['include_client_info_report_1'] = sanitize_text_field( $values[ 'include_client_info_report_1' ] );
		}
		if ( array_key_exists( 'include_client_info_report_4', $values ) ) {
			$extra_configs['include_client_info_report_4'] = sanitize_text_field( $values[ 'include_client_info_report_4' ] );
		}
		if ( array_key_exists( 'remove_local_pickup_report_7', $values ) ) {
			$extra_configs['remove_local_pickup_report_7'] = sanitize_text_field( $values[ 'remove_local_pickup_report_7' ] );
		}

		$active_cycle = get_option( 'sementes_last_active_cycle' );
		$cycle_id     = $this->callbacks->get_cycle_id_for_report( $current_form_cycle_id, $active_cycle->ID, true );
		$this->update_sementes_option( 'cycle_report_' . $report_id, $cycle_id );

		$html = $this->callbacks->get_report( 'report_' . $report_id, $cycle_id, $orderby, $includetax, $extra_configs );

		if ( ! $html ) {
			$html = __( 'Could not generate report', 'sementes-cas-gcrs' );
		}

		$response = array(
			'html_content' => $html,
			'html_element' => '#eitagcr-report_' . esc_attr( $report_id ) . '_raw_field',
		);
		echo json_encode( $response );
	}


	public function process_generate_report_requisition() {
		if ( isset( $_POST['generate_report'] ) && $_POST['generate_report'] ) {
			$generate_report = sanitize_text_field( $_POST['generate_report'] );
			if ( $generate_report === 'true' ) {
				$this->callbacks->generate_report();
			}
			add_action( 'admin_notices', array( $this->callbacks, 'generate_report_notice' ) );
		}
	}

	public function set_sub_page_redux() {

		$opt_name = $this->redux_opt_name;

		$args      = array(
			'post_type'      => 'cycle',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);
		$allcycles = get_posts( $args );

		$cycles   = null;
		$cycle_id = null;
		if ( count( $allcycles ) > 0 ) {
			foreach ( $allcycles as $cycle ) {
				$cycles[ $cycle->ID ] = $cycle->post_title;
			}
		} else {
			$cycle_id = 0;
			$cycles   = array( 0 => __( 'No cycle', 'sementes-cas-gcrs' ) );
		}

		$supplier_taxonomy_name = $this->get_sementes_option( 'supplier_taxonomy_name' );
		if(isset($supplier_taxonomy_name) && empty($supplier_taxonomy_name)){
			$supplier_taxonomy_name = __( 'Suppliers', 'sementes-cas-gcrs' );
		}

		$report_configs = array(
			array(
				'id'       => 'report_1',
				'name'     => __( 'Orders', 'sementes-cas-gcrs' ),
				'order_by' => true,
				'enabled'  => true,
				'extra_fields' => array(
					array(
						'id'          => 'include_client_info_report_1',
						'title'       => __( 'Include client information', 'sementes-cas-gcrs' ),
						'desc'        => __( 'Check if you want to see client information.', 'sementes-cas-gcrs' ),
						'type'        => 'checkbox',
						'default'        => false
					),
				)
			),
			array(
				'id'       => 'report_2',
				'name'     => sprintf( __( 'Report Orders/%s', 'sementes-cas-gcrs' ), strtolower(esc_html($supplier_taxonomy_name)) ),
				'order_by' => false,
				'enabled'  => true,
			),
			array(
				'id'       => 'report_3',
				'name'     => sprintf( __( 'Report Orders/%s (Summary)', 'sementes-cas-gcrs' ), strtolower(esc_html($supplier_taxonomy_name)) ),
				'order_by' => false,
				'enabled'  => true,
			),
			array(
				'id'       => 'report_4',
				'name'     => __( 'Report Orders/Pickup Place', 'sementes-cas-gcrs' ),
				'order_by' => false,
				'enabled'  => true,
				'extra_fields' => array(
					array(
						'id'          => 'include_client_info_report_4',
						'title'       => __( 'Include client information', 'sementes-cas-gcrs' ),
						'desc'        => __( 'Check if you want to see client information.', 'sementes-cas-gcrs' ),
						'type'        => 'checkbox',
						'default'        => false
					),
				)
			),
			array(
				'id'       => 'report_5',
				'name'     => sprintf( __( 'Report %s (Summary)/Pickup place', 'sementes-cas-gcrs' ), strtolower(esc_html($supplier_taxonomy_name)) ),
				'order_by' => false,
				'enabled'  => true,
			),
			array(
				'id'       => 'report_6',
				'name'     => sprintf( __( 'Report %s (Summary)/Payment Method', 'sementes-cas-gcrs' ), strtolower(esc_html($supplier_taxonomy_name)) ),
				'order_by' => false,
				'enabled'  => true,
			),
			array(
				'id'       => 'report_7',
				'name'     => __( 'Report Order/Shipping Zone', 'sementes-cas-gcrs' ),
				'order_by' => false,
				'enabled'  => true,
				'extra_fields' => array(
					array(
						'id'          => 'remove_local_pickup_report_7',
						'title'       => __( 'Remove local pickup orders', 'sementes-cas-gcrs' ),
						'desc'        => __( 'Check if you want to remove the orders that have local pickup as shipping type.', 'sementes-cas-gcrs' ),
						'type'        => 'checkbox',
						'default'        => false
					),
				)
			),
		);

		Redux::set_section(
			$opt_name,
			array(
				'title'            => esc_html__( 'Reports', 'sementes-cas-gcrs' ),
				'id'               => 'reports',
				'customizer_width' => '450px',
				'subsection'       => false,
			)
		);

		$html         = '';
		$index        = 0;
		$active_cycle_id = null;
		$active_cycle = get_option( 'sementes_last_active_cycle' );
		if ( isset($active_cycle) && $active_cycle ) {
			$active_cycle_id = $active_cycle->ID;
		}
		if ( isset($_GET['cycle_id']) && $_GET['cycle_id'] ) {
			$query_string_cycle_id = sanitize_text_field($_GET['cycle_id']);
			if ( in_array( $query_string_cycle_id, array_keys($cycles))) {
				$active_cycle_id = $query_string_cycle_id;
			}
		}

		$include_tax_report = esc_html($this->get_sementes_option('enable_tax'));
		
		$page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : "";


		foreach ( $report_configs as $report_config ) {
			++$index;

			$current_form_cycle_id = $this->get_sementes_option( 'cycle_report_' . $index );

			$cycle_id = isset( $cycle_id ) ? $cycle_id : $this->callbacks->get_cycle_id_for_report( $current_form_cycle_id, $active_cycle_id, false );
			$this->update_sementes_option( 'cycle_report_' . $index, $cycle_id );
			$this->update_sementes_option( 'report_' . $index . '_default_cycle_id', $cycle_id );

			$orderby    = $this->get_sementes_option( 'orderby_report_' . $index );
			$includetax = $this->get_sementes_option( 'includetax_report_' . $index );
			$extra_configs['include_client_info_report_1'] =  $this->get_sementes_option( 'include_client_info_report_1' );
			$extra_configs['include_client_info_report_4'] =  $this->get_sementes_option( 'include_client_info_report_4' );
			$extra_configs['remove_local_pickup_report_7'] =  $this->get_sementes_option( 'remove_local_pickup_report_7' );
			
			/* avoid unecessary db calls*/
			if ( $page == 'eitagcr_panel') {
				$html = $this->callbacks->get_report( $report_config['id'], $cycle_id, $orderby, $includetax, $extra_configs );
			}

			$pdf_button  = '{"id":"' . esc_html( $report_config['id'] ) . '", "format":"PDF"}';
			$xlsx_button = '{"id":"' . esc_html( $report_config['id'] ) . '", "format":"XLSX"}';

			$download_buttons = '
				<button class="button-secondary" name="report_data" value="' . htmlspecialchars( $xlsx_button, ENT_QUOTES, 'UTF-8' ) . '" type="submit">XLSX</button>
				<button class="button-secondary" name="report_data" value="' . htmlspecialchars( $pdf_button, ENT_QUOTES, 'UTF-8' ) . '" type="submit">PDF</button>
			';

			if ( $report_config['id'] == 'report_7' ) {
				$tag_button  = '{"id":"report_shipping_tag", "format":"PDF"}';

				$download_buttons .= '
					<button class="button-primary" name="report_data" value="' . htmlspecialchars( $tag_button, ENT_QUOTES, 'UTF-8' ) . '" type="submit">'. __('ETIQUETAS', 'sementes-cas-gcrs') .'</button>
				';
			}


			$fields = array(
				array(
					'id'          => 'cycle_report_' . $index,
					'title'       => __( 'Cycle', 'sementes-cas-gcrs' ),
					'desc'        => __( 'Select the cycle to see the report.', 'sementes-cas-gcrs' ),
					'type'        => 'select',
					'data'        => $cycles,
					'placeholder' => $cycles[ $cycle_id ],
					'attributes'  => array(
						'is_cycle_report_select' => 'true',
						'report_id'              => $index,
					),
				),
			);

			if ( $include_tax_report ) {
				array_push(
					$fields,
					array(
						'id'    => 'includetax_report_' . $index,
						'type'  => 'checkbox',
						'title' => __( 'Include taxes', 'sementes-cas-gcrs' ),
						'desc'  => __( 'Includes taxes in this report', 'sementes-cas-gcrs' ),
						'default' => false,
					)
				);
			}

			if ( $report_config['order_by'] ) {
				array_push(
					$fields,
					array(
						'id'          => 'orderby_report_' . $index,
						'type'        => 'select',
						'title'       => __( 'Order by', 'sementes-cas-gcrs' ),
						'desc'        => __( 'Choose how to order your report.', 'sementes-cas-gcrs' ),
						'data'        => array(
							'DateTime'  => __( 'Date', 'sementes-cas-gcrs' ),
							'FirstName' => __( 'Name', 'sementes-cas-gcrs' ),
						),
						'placeholder' => __( 'Date', 'sementes-cas-gcrs' ),
					)
				);
			}

			if ( isset($report_config['extra_fields']) ) {
				$fields = array_merge( $fields, $report_config['extra_fields']);
			}

			array_push(
				$fields,
				array(
					'id'      => 'download_report_btns_' . $index,
					'title'   => __( 'Export', 'sementes-cas-gcrs' ),
					'type'    => 'raw',
					'content' => $download_buttons,
				),
				array(
					'id'      => 'report_' . $index . '_raw_field',
					'title'   => __( 'Report', 'sementes-cas-gcrs' ),
					'type'    => 'raw',
					'content' => $html,
				),
				array(
					'id'         => 'report_' . $index . '_default_cycle_id',
					'type'       => 'text',
					'attributes' => array(
						'type'                => 'hidden',
						'is_default_cycle_id' => 'true',
						'report_id'           => $index,
					),
				)
			);

			Redux::set_section(
				$opt_name,
				array(
					'title'            => $report_config['name'],
					'id'               => 'report-' . $index,
					'subsection'       => true,
					'customizer_width' => '450px',
					'fields'           => $fields,
					'disabled'         => ! $report_config['enabled'],
				)
			);
		}
	}

	private function print_result() {
		$redux  = get_option( 'sementes-cas-gcrs' );
		$option = $redux['opt-checkbox-sidebar'];
	}
}
