<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Base;

use SementesCasGcrs\Base\BaseController;
use SementesCasGcrs\Api\Callbacks\ReportCallbacks;
use SementesCasGcrs\Api\Callbacks\AnalyticsCallbacks;
use Redux;

/**
 *
 */
class AnalyticsController extends BaseController {

	public $settings;

	public $callbacks;

	public $subpages = array();

	public function register() {

		$this->analytics_callbacks = new AnalyticsCallbacks();

		$this->report_callbacks = new ReportCallbacks();

		add_action( 'plugins_loaded', array( $this, 'set_sub_page_redux' ), 10, 1 );

		add_action( 'wp_ajax_get_cycle_analytics', array( $this, 'ajax_get_analytics' ) );

		add_action( 'init', array( $this, 'process_download_analytics_requisition' ), 10, 1 );

		add_action( 'woocommerce_reduce_order_stock', array( $this, 'save_order_item_stock' ), 10, 1 );
	}

	public function process_download_analytics_requisition() {
		// Download report
		if ( isset( $_POST['sementes_analytics_data'] ) && $_POST['sementes_analytics_data'] ) {
			$analytics_data = array_map( 'sanitize_text_field', json_decode( stripslashes( $_POST['sementes_analytics_data'] ), true ) );
			$form_data = array_map( 'sanitize_text_field', $_POST['eitagcr'] );
			$analytics_id = $analytics_data['id'];
			$analytics_data_format = $analytics_data['format'];
			$active_cycle = get_option( 'sementes_last_active_cycle' );
			
			$cycle_ids = null;
			if ( isset( $_POST['eitagcr']['cycle_' . $analytics_id] ) ) {
				$cycle_ids = array_map( 'sanitize_text_field', $_POST['eitagcr']['cycle_' . $analytics_id] );
			} 
			
			$this->analytics_callbacks->download_analytics( $analytics_id, $cycle_ids, $analytics_data_format );
		}
	}

	public function save_order_item_stock( $order ) {
		foreach ( $order->get_items() as $item ) {
			if ( ! $item->is_type( 'line_item' ) ) {
				continue;
			}
	
			$product   = $item->get_product();
			$new_stock = $product->get_stock_quantity();
	
			if ( isset($new_stock) ) {
				$item->add_meta_data( '_new_stock', $new_stock, true );
				$item->save();
			}
		}
	}

	public function ajax_get_analytics() {
		check_ajax_referer( 'get_cycle_report', 'security' );

		$html   = '';
		$raw_values = array();
		if ( isset( $_POST['form_data'] ) ) {
			parse_str( $_POST['form_data'], $raw_values );
			$analytics_id = sanitize_text_field($raw_values['form_id']);
			$values = array_map( 'sanitize_text_field', $raw_values['eitagcr'] );
		}

		$cycle_ids = array();
		if (isset($raw_values['eitagcr'][ 'cycle_analytics_' . $analytics_id ])) {
			$cycle_ids =  array_map( 'sanitize_text_field', $raw_values['eitagcr'][ 'cycle_analytics_' . $analytics_id ] );
		} 

		$html = $this->analytics_callbacks->get_analytics( 'analytics_' . $analytics_id, $cycle_ids );

		if ( ! $html ) {
			$html = __( 'Could not generate analytics', 'sementes-cas-gcrs' );
		}

		$response = array(
			'html_content' => $html,
			'html_element' => '#eitagcr-analytics_' . esc_attr( $analytics_id ) . '_raw_field',
		);
		echo json_encode( $response );
	}

	public function set_sub_page_redux() {
		
		$opt_name = $this->redux_opt_name;

		$args      = array(
			'post_type'      => 'cycle',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);
		$allcycles = get_posts( $args );

		$cycles   = array();
		$cycle_ids = array();
		if ( count( $allcycles ) > 0 ) {
			foreach ( $allcycles as $cycle ) {
				$cycles[ $cycle->ID ] = $cycle->post_title;
			}
		} else {
			$cycles   = array( 0 => __( 'No cycle', 'sementes-cas-gcrs' ) );
		}

		$analytics_configs = array(
			array(
				'id'       => 'analytics_1',
				'name'     => sprintf( __( 'Product Analytics', 'sementes-cas-gcrs') )
			),
			array(
				'id'       => 'analytics_2',
				'name'     => sprintf( __( 'Client Analytics', 'sementes-cas-gcrs' ) )
			),
		);

		Redux::set_section(
			$opt_name,
			array(
				'title'            => esc_html__( 'Analytics', 'sementes-cas-gcrs' ),
				'id'               => 'analytics',
				'customizer_width' => '450px',
				'subsection'       => false,
			)
		);

		$page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : "";
		$html         = '';
		$index = 0;
		
		foreach ( $analytics_configs as $analytics_config ) {
			++$index;
			$cycle_ids = $this->get_sementes_option( 'cycle_analytics_' . $index );
			
			/* avoid unecessary db calls*/
			if ( $page == 'eitagcr_panel') {
				$html = $this->analytics_callbacks->get_analytics( $analytics_config['id'], $cycle_ids, false, false );
			}

			$pdf_button  = '{"id":"' . esc_html( $analytics_config['id'] ) . '", "format":"PDF"}';
			$xlsx_button = '{"id":"' . esc_html( $analytics_config['id'] ) . '", "format":"XLSX"}';

			$download_buttons = '
				<button class="button-secondary" name="sementes_analytics_data" value="' . htmlspecialchars( $xlsx_button, ENT_QUOTES, 'UTF-8' ) . '" type="submit">XLSX</button>
				<button class="button-secondary" name="sementes_analytics_data" value="' . htmlspecialchars( $pdf_button, ENT_QUOTES, 'UTF-8' ) . '" type="submit">PDF</button>
			';

			$fields = array(
				array(
					'id'          => 'cycle_analytics_' . $index,
					'title'       => __( 'Cycle', 'sementes-cas-gcrs' ),
					'desc'        => __( 'Select the cycle to see the analytics.', 'sementes-cas-gcrs' ),
					'type'        => 'select',
					'multi'   	  => true,
					'data'        => $cycles
				),
				array(
					'id'      => 'download_analytics_btns_' . $index,
					'title'   => __( 'Export', 'sementes-cas-gcrs' ),
					'type'    => 'raw',
					'content' => $download_buttons,
				),
				array(
					'id'      => 'analytics_' . $index . '_raw_field',
					'title'   => __( 'analytics', 'sementes-cas-gcrs' ),
					'type'    => 'raw',
					'content' => $html,
				)
			);
				
			Redux::set_section(
				$opt_name,
				array(
					'title'            => $analytics_config['name'],
					'id'               => 'analtics-' . $index,
					'subsection'       => true,
					'customizer_width' => '450px',
					'fields'           => $fields,
				)
			);
		}

		$this->add_analytics_raw_subsection();
	}

	private function add_analytics_raw_subsection() {

		$xlsx_button = '{"id":"analytics_raw", "format":"XLSX"}';
		$csv_button = '{"id":"analytics_raw", "format":"CSV"}';

		$download_buttons = '
			<button class="button-secondary" name="sementes_analytics_data" value="' . htmlspecialchars( $xlsx_button, ENT_QUOTES, 'UTF-8' ) . '" type="submit">XLSX</button>
			<button class="button-secondary" name="sementes_analytics_data" value="' . htmlspecialchars( $csv_button, ENT_QUOTES, 'UTF-8' ) . '" type="submit">CSV</button>
		';

		$fields = array(
			array(
				'id'      => 'download_analytics_btns_raw',
				'title'   => __( 'Export', 'sementes-cas-gcrs' ),
				'type'    => 'raw',
				'content' => $download_buttons,
			)
		);
			
		Redux::set_section(
			$this->redux_opt_name,
			array(
				'title'            => __( 'Raw Analytics', 'sementes-cas-gcrs' ),
				'desc'			   => 'Exporte todos os dados utilizados pelo plugin sem filtros para análise personalizada.<br/>Cada linha da planilha representa um Item de Pedido, ou seja, contém os dados de um produto que foi comprado em um determinado pedido.<br/>Com isso, é possível obter informações tanto dos produtos (nome, estoque, quantidade comprada, ...) quanto dos pedidos (telefone, endereço, nome do cliente, ...).',
				'id'               => 'analytics_raw',
				'subsection'       => true,
				'fields'           => $fields,
			)
		);
	}
}
