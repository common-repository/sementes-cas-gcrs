<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Api\Callbacks;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SementesCasGcrs\Base\BaseController;
use WC_Shipping_Zones;
use DateTime;
use DateTimeZone;

class ReportCallbacks extends BaseController
{
	public function get_cycle_id_for_report($current_form_cycle_id, $active_cycle_id, $is_ajax) {
		if($is_ajax && $current_form_cycle_id) {
			$cycle_id = $current_form_cycle_id;
		}
		elseif ( isset($active_cycle_id) &&  $active_cycle_id ) {
			$cycle_id = $active_cycle_id;
		}
		elseif( $current_form_cycle_id ){
			$cycle_id = $current_form_cycle_id;
		} else {
			$cycle = get_posts( array( 'post_type' => 'cycle', 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC',))[0];
			$cycle_id = $cycle->ID;
		}

		return $cycle_id;
	}

	public function download_report($report_id, $cycle_id, $report_format, $orderby, $includetax, $extra_configs) {
		// Download report
		$html = $this->get_report( $report_id, $cycle_id, $orderby, $includetax, $extra_configs);
		$html = apply_filters( 'eitagcr_get_report', $html, $report_id );

		$html = str_replace( "<bdi>", "", str_replace( "</bdi>", "", $html ));

		if ( $report_format  === 'XLSX'){
			$html = str_replace( get_woocommerce_currency_symbol(), "", $html );
			$html = str_replace( ".", "", $html );
			$html = str_replace( ",", ".", $html );
		}

		$cycle_title = get_the_title( $cycle_id );

		$file_name_cycle_title = str_replace(' ', '_', $cycle_title);
		$file_name_cycle_title = str_replace(',', '_', $file_name_cycle_title);
		$file_name_cycle_title = preg_replace('/[^a-z0-9\_\-\.]/i', '', $file_name_cycle_title);

		$date = new DateTime( 'NOW', new DateTimeZone( 'America/Sao_Paulo' ) );
		$date_time = $date->format( 'd/m/Y h:i:s a' );

		$header = "<tr><td colspan='4'>Relatório gerado em {$date_time}, referente ao ciclo {$cycle_title}</td></tr><tr><td style='height: 30px' colspan='4'>&nbsp;</td></tr>";

		$fid = fopen( $this->upload_base_dir . '/report.html', 'w' );
		fwrite( $fid, $header . $html );
		fclose( $fid );

		$reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
		$spreadsheet = $reader->load($this->upload_base_dir . '/report.html');
    ob_end_clean();
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

		$filetitle = 'Sementes_Relatorio_' . $file_name_cycle_title . '_' . $date->format( 'd-m-Y' );
		if ( $report_format  === 'PDF') {
			$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Mpdf');
			$filename = $this->upload_base_dir . '/' . $filetitle . '.pdf';
			$writer->save( $filename );
			$ctype = "application/pdf";
		} elseif ( $report_format  === 'XLSX' ) {
			$filename = $this->upload_base_dir . '/' . $filetitle . '.xlsx';
			$writer->save( $filename );
			$ctype = "application/vnd.ms-excel";
		}	

		header("Pragma: public"); // required
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false); // required for certain browsers
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=\"".basename($filename)."\";" );
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: ".filesize($filename));
		readfile("$filename");
		// exit();
	}

	public function eitagcr_add_cycle_to_order( $order_id ) {
		$active_cycle = get_option( 'sementes_active_cycle' );
        if ( isset( $active_cycle )) {
            update_post_meta( $order_id, '_eita_gcr_cycle_id', $active_cycle->ID );
        }
    }



	public function generate_report_notice() {
		?>
			<div class="notice notice-success is-dismissible">
				<p><?php _e( 'Report generated successfully', 'sementes-cas-gcrs' ); ?></p>
			</div>
		<?php
	}

	public function create_cycle_notice() {
		?>
			<div class="notice notice-success is-dismissible">
				<p><?php _e( 'Cycle created successfully', 'sementes-cas-gcrs' ); ?></p>
			</div>
		<?php
	}

	public function get_cycle_total($cycle_id) {
		global $wpdb;
		$result = $wpdb->get_results( $wpdb->prepare("
				SELECT sum(`order_item_price`) AS 'total', sum(`order_item_tax`) AS 'TotalTax' FROM `{$wpdb->prefix}eitagcr_report` WHERE `cycle_id` = %s
			", $cycle_id), OBJECT );
		return $result[0]->total;
	}

	public function get_cycle_suppliers($cycle_id) {
		global $wpdb;
		$terms = $wpdb->get_results($wpdb->prepare("
			SELECT
			`order_item_category_id`  AS 'term_id',
			`order_item_category` AS 'term_name',
			sum(`order_item_price`) AS total_price,
			sum(`order_item_tax`) AS total_tax,
			count(distinct(`order_id`)) AS total_orders
			FROM `{$wpdb->prefix}eitagcr_report`
			WHERE `cycle_id` = %s
			GROUP BY `order_item_category_id`
		", $cycle_id), OBJECT );
		return $terms;
	}

	public function get_orders($cycle_id) {
		global $wpdb;

		$orders = $wpdb->get_results($wpdb->prepare( "
				SELECT
				`order_id`,
				sum(`order_item_price`) AS total_price,
				sum(`order_item_tax`) AS total_tax,
				max(`order_first_name`) AS first_name,
				max(`order_last_name`) AS last_name,
				max(`order_datetime`) AS datetime,
				max(`order_payment_method_title`) AS payment_method
				FROM `{$wpdb->prefix}eitagcr_report`
				WHERE `cycle_id` = %s
				GROUP BY `order_id`
				", $cycle_id), OBJECT );

			return $orders;
	}

	public function sementes_proccess_order_object_save( $order, $datastore ) {
		$all_shipping_zones = $this->get_all_shipping_zones();
		$this->eitagcr_add_to_report( $order, $datastore, $all_shipping_zones );
	}

    public function eitagcr_add_to_report( $order, $datastore, $all_shipping_zones ) {

		global $wpdb;

		$order_id = $order->get_ID();

		// remove from report, to prevent duplicates when editing
		$this->eitagcr_remove_from_report( $order_id, 'eitagcr_report' );

		if ( 'cancelled' === $order->get_status() ){
			return;
		}

		$supplier_taxonomy = $this->get_sementes_option( 'supplier_taxonomy' );
		
		$order_items = $order->get_items();

		$cycle_id = get_post_meta( $order_id, '_eita_gcr_cycle_id', true);

		if ( ! $cycle_id ) {
			return;
		}
		// Sementes related data
		$cycle_name = get_the_title($cycle_id);
		$cycle_start_date = get_post_meta($cycle_id, 'cycle_start_date', true);
		$order_delivery_place_id = get_post_meta($order_id, '_shipping_deliveryplace', true);

		// WC related data, using WC_Order methods:

		$order_datetime = $order->get_date_created()->format('Y-m-d H:i:s');
		$order_first_name = $order->get_billing_first_name();
		$order_last_name = $order->get_billing_last_name();
		$order_payment_method_title = $order->get_payment_method_title();
		$order_phone_number = $order->get_billing_phone();
		$order_shipping_postcode = $order->get_shipping_postcode();
		$order_address_1 =  $order->get_shipping_address_1();
		$order_address_2 = $order->get_shipping_address_2();
		$order_shipping_city = $order->get_shipping_city();
		$order_shipping_state = $order->get_shipping_state();
		$order_shipping_items = $order->get_items('shipping');

		$order_zone = null;

		if ($order_shipping_items && count($order_shipping_items) > 0 ) {
			$order_shipping = reset( $order_shipping_items );
			$order_shipping_type = $order_shipping->get_method_id();//flat_rate, local_pickup...
			$order_shipping_id = $order_shipping->get_instance_id();
			$order_shipping_name = $order_shipping->get_name();
			
			$order_method = reset ($order_shipping_items );
			if( $order_method ) {
				$key = $order_method['method_id'] . ":" . $order_method['instance_id'];
				if ( isset($all_shipping_zones[$key]) && preg_match('#\[(.+?)\]#',$all_shipping_zones[$key],$m) ) {
					$order_zone = $m[1];
				}
			}	
		} else {
			$order_shipping = null;
			$order_shipping_type = null;
			$order_shipping_id = null;
			$order_shipping_name = null;
		}

    	$order_delivery_place = '';
		if ( $order_delivery_place_id ) {
			$order_delivery_place = get_the_title( $order_delivery_place_id );
			$order_shipping_name = $order_delivery_place;
		}


		

		foreach ($order_items as $order_item) {
			$product_category = wp_get_post_terms( $order_item['product_id'], $supplier_taxonomy );

			if ( count( $product_category ) == 0 ) {
				$product_category = ""; 
				$product_category_id = "";
			} else {
				$product_category_id = $product_category[0]->term_id;
				$product_category = $product_category[0]->name;
			}

			$wpdb->insert(
				"{$wpdb->prefix}eitagcr_report",
				array(
					'order_item_id'              => $order_item->get_id(),
					'order_id'                   => $order_id,
					'cycle_id'                   => $cycle_id,
					'cycle_name'				 => $cycle_name,
					'cycle_start_date'           => $cycle_start_date,
					'order_datetime'             => $order_datetime,
					'order_first_name'           => esc_sql( $order_first_name ),
					'order_last_name'            => esc_sql( $order_last_name ),
					'order_delivery_place_id'    => $order_delivery_place_id,
					'order_delivery_place'       => esc_sql( $order_delivery_place ),
					'order_payment_method_title' => esc_sql( $order_payment_method_title ),
					'order_item_product_id'      => $order_item['product_id'],
					'order_item_product'         => esc_sql( $order_item->get_name() ),
					'order_item_category_id'     => $product_category_id,
					'order_item_category'        => esc_sql( $product_category ),
					'order_item_quantity'        => $order_item->get_quantity(),
					'order_item_unit_price'      => $order_item->get_subtotal()/$order_item->get_quantity(),
					'order_item_unit_tax'        => $order_item->get_subtotal_tax()/$order_item->get_quantity(),
					'order_item_price'           => $order_item->get_subtotal(),
					'order_item_tax'             => $order_item->get_subtotal_tax(),
					'order_item_stock'           => $order_item['new_stock'],
					'order_phone'				 => esc_sql($order_phone_number),
					'order_shipping_postcode'	 => esc_sql($order_shipping_postcode),
					'order_address_1'			 => esc_sql( $order_address_1 ),
					'order_address_2'			 => esc_sql( $order_address_2 ),
					'order_shipping_state'		 => esc_sql( $order_shipping_state ),
					'order_shipping_city'		 => esc_sql( $order_shipping_city ),
					'order_zone'                 => esc_sql( $order_zone ),
					'order_shipping_method_id'	 => esc_sql( $order_shipping_id ), 
					'order_shipping_method_type' => esc_sql( $order_shipping_type ),
					'order_shipping_method_name' => esc_sql( $order_shipping_name )
				)
			);
		}
	}

	public function eitagcr_remove_from_report( $order_id,  $table_name) {

		global $wpdb;

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"
				DELETE
				from {$wpdb->prefix}{$table_name}
				WHERE `order_id` = %s",
				$order_id
			)
		);
	}

	public function get_all_shipping_zones() {
		global $wpdb;

		$shipping_methods = array();

		// get raw names
		$raw_methods = $wpdb->get_col( "SELECT DISTINCT order_item_name FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_type='shipping' ORDER BY order_item_name" );
		foreach ( $raw_methods as $method ) {
			$shipping_methods[ 'order_item_name:' . $method ] = $method;
		}


		// try get  methods for zones
		if ( ! class_exists( "WC_Shipping_Zone" ) ) {
			return $shipping_methods;
		}

		if ( ! method_exists( "WC_Shipping_Zone", "get_shipping_methods" ) ) {
			return $shipping_methods;
		}

		foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
			$methods = $zone['shipping_methods'];
			/** @var WC_Shipping_Method $method */
			foreach ( $methods as $method ) {
				$shipping_methods[ $method->get_rate_id() ] = '[' . $zone['zone_name'] . '] ' . $method->get_title();
			}
		}
		
		return $shipping_methods;
	}

  	public function generate_report() {

		global $wpdb;	
		$wpdb->get_results( "TRUNCATE TABLE {$wpdb->prefix}eitagcr_report" );

		$orders = wc_get_orders( array( 'limit' => -1) );
		$all_shipping_zones = $this->get_all_shipping_zones();

		foreach ($orders as $order) {
			$this->eitagcr_add_to_report( $order, NULL, $all_shipping_zones );
		}
	}
  
	public function get_report( $report, $cycle_id, $orderby, $includetax, $extra_configs ) {

		if( !in_array( $report, [ 'report_1', 'report_2', 'report_3', 'report_4', 'report_5', 'report_6', 'report_7', 'report_shipping_tag' ] )){
			return "";
		}

		if ( ! isset($orderby) || ! $orderby ) {
			$orderby = 'DateTime';
		}

		if ( ! isset($includetax) ) {
			$includetax = false;
		}

		global $wpdb;
		$cycle = get_post($cycle_id);

		if ( $report == 'report_1' ) {

			$include_client_info = false;
			if ( isset( $extra_configs['include_client_info_report_1'] ) ) {
				$include_client_info = $extra_configs[ 'include_client_info_report_1' ];
			}
			if ( $includetax ){
				$colspan = 5;
			} else {
				$colspan = 4;
			}

			$orders = $wpdb->get_results( $wpdb->prepare("
				SELECT
				`order_id`,
				sum(`order_item_price`) AS TotalPrice,
				sum(`order_item_tax`) AS TotalTax,
				max(`order_first_name`) AS FirstName,
				max(`order_last_name`) AS LastName,
				max(`order_datetime`) AS DateTime,
				max(`order_payment_method_title`) AS PaymentMethod,
				max(`order_phone`) AS OrderPhone,
				max(`order_address_1`) AS OrderAddress1,
				max(`order_address_2`) AS OrderAddress2,
				max(`order_shipping_city`) AS OrderShippingCity,
				max(`order_shipping_state`) AS OrderShippingState
				FROM `{$wpdb->prefix}eitagcr_report`
				WHERE `cycle_id` = %s
				GROUP BY `order_id`
				ORDER BY %1s
			", $cycle_id, $orderby), OBJECT );

			$orders_count = count( $orders );

			$html = "<table class='gcr_report'>";

			$html .= 	"<tr>
								<td style='background-color: #CCC; border: 1px solid black; height: 15px; vertical-align: center' colspan='$colspan'>". __('Total de pedidos do ciclo') .": $orders_count</td>
							</tr>";

			$html .= "
				<tr class='gcr_report_header'>
					<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('Qty', 'sementes-cas-gcrs' ) . "</td>
					<td style='text-align: center; font-weight: bold; width: 80px; border: 1px solid black'>" . __('Product', 'sementes-cas-gcrs' ) . "</td>
					<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Unit price', 'sementes-cas-gcrs' ) . "</td>
					<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Price per product', 'sementes-cas-gcrs' ) . "</td>";
			if ( $includetax ){
				$html .= "
					<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('With tax', 'sementes-cas-gcrs' ) . "</td>
				";
			}

			$html .= "
				</tr>
			";

			foreach ($orders as $order) {
				if ($include_client_info) {
					$html .= "
						<tr class='gcr_report_orderid'>
							<td class='order_info_expanded' style='text-align: center; background-color: #CCC; border: 1px solid black; height: 15px; vertical-align: center' colspan='$colspan'><span>{$order->FirstName} {$order->LastName} ({$order->order_id}) </span><br/> <span>{$order->OrderAddress1} {$order->OrderAddress2} - {$order->OrderShippingCity} - {$order->OrderShippingState}</span> <br/> <span>{$order->OrderPhone}</span></td>
						</tr>";
				} else {
					$html .= "
						<tr class='gcr_report_orderid'>
							<td style='text-align: center; background-color: #CCC; border: 1px solid black; height: 15px; vertical-align: center' colspan='$colspan'>{$order->FirstName} {$order->LastName} ({$order->order_id})</td>
						</tr>";
				}
				
				$order_items = $wpdb->get_results($wpdb->prepare( "
					SELECT * FROM `{$wpdb->prefix}eitagcr_report`
					WHERE `order_id` = %s
				", $order->order_id), OBJECT );

				foreach ($order_items as $order_item) {
					$html .= "
						<tr class='gcr_report_orderdesc'>
							<td style='border: 1px solid black; text-align: center'>{$order_item->order_item_quantity}</td>
							<td style='border: 1px solid black;'>{$order_item->order_item_product}</td>
							<td style='border: 1px solid black;'>" . wc_price( $order_item->order_item_unit_price) . "</td>
							<td style='border: 1px solid black;'>" . wc_price( $order_item->order_item_price) . "</td>
					";
					if ( $includetax ){
						$html .= "
							<td style='border: 1px solid black;'>" . wc_price( $order_item->order_item_price + $order_item->order_item_tax) . "</td>
						";
					}
					$html .= "
						</tr>
						";
				}
				$html .= "
				<tr class='gcr_report_ordertotal'>
					<td  style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right' colspan='$colspan'>" . __('Total', 'sementes-cas-gcrs' ) . ": " . wc_price( $order->TotalPrice) . " (" . $order->PaymentMethod . ")</td>
				</tr>";

				if ( $includetax ){
					$html .= "
					<tr class='gcr_report_ordertotal'>
						<td  style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right' colspan='$colspan'>" . __('Total with taxes', 'sementes-cas-gcrs' ) . ": " . wc_price( $order->TotalPrice+$order->TotalTax) . "</td>
					</tr>";
				}
			}
		}

		if ( $report == 'report_2' ) {

			if ( $includetax ){
				$colspan = 6;
			} else {
				$colspan = 5;
			}

			$terms = $wpdb->get_results($wpdb->prepare("
				SELECT DISTINCT `order_item_category_id` AS 'TermID',
				`order_item_category` AS 'TermName'
				FROM {$wpdb->prefix}eitagcr_report
				WHERE `cycle_id` = %s
			", $cycle_id), OBJECT );

			$html = "<table class='gcr_report'>";

			foreach ($terms as $term) {

				$html .= "
					<tr class='gcr_report_header'>
						<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('Qty', 'sementes-cas-gcrs' ) . "</td>
						<td style='text-align: center; font-weight: bold; width: 50px; border: 1px solid black'>" . __('Product', 'sementes-cas-gcrs' ) . "</td>
						<td style='text-align: center; font-weight: bold; width: 30px; border: 1px solid black'>" . __('Order', 'sementes-cas-gcrs' ) . "</td>
						<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Unit price', 'sementes-cas-gcrs' ) . "</td>
						<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Price per product', 'sementes-cas-gcrs' ) . "</td>
					";

					if ( $includetax ){
							$html .= "
								<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('With tax', 'sementes-cas-gcrs' ) . "</td>
							";
					}

					$html .= "
					</tr>
				";

				$html .= "
				<tr class='gcr_report_orderid'>
					<td style='text-align: center; background-color: #CCC; border: 1px solid black; height: 15px; vertical-align: center' colspan='$colspan'>$term->TermName</td>
				</tr>";

				$order_items = $wpdb->get_results($wpdb->prepare( "
				SELECT order_item_product AS 'Product',
				order_first_name AS 'FirstName',
				order_last_name AS 'LastName',
				order_id AS 'OrderID',
				order_item_quantity AS 'Quantity',
				order_item_tax AS 'Tax',
				order_item_unit_tax AS 'UnitTax',
				order_item_price AS 'Price',
				order_item_unit_price AS 'UnitPrice'
				FROM {$wpdb->prefix}eitagcr_report
				WHERE `cycle_id` = %s AND `order_item_category_id` = %s
				", $cycle_id, $term->TermID), OBJECT );

				$order_total_price = 0;
				$order_total_tax = 0;

				foreach ($order_items as $order_item) {
					$html .= "
						<tr class='gcr_report_orderdesc'>
						<td style='border: 1px solid black; text-align: center'>{$order_item->Quantity}</td>
						<td style='border: 1px solid black;'>{$order_item->Product}</td>
						<td style='border: 1px solid black;'>{$order_item->FirstName} {$order_item->LastName} ({$order_item->OrderID})</td>
						<td style='border: 1px solid black;'>" . wc_price( $order_item->UnitPrice ) . "</td>
						<td style='border: 1px solid black;'>" . wc_price( $order_item->Price ) . "</td>
						";

						if ( $includetax ){
								$html .= "
									<td style='border: 1px solid black;'>" . wc_price( $order_item->Price+$order_item->Tax ) . "</td>
								";
						}

						$html .= "
						</tr>
						";
					$order_total_price += $order_item->Price;
					$order_total_tax += $order_item->Tax;
				}
				$html .= "
				<tr class='gcr_report_ordertotal'>
					<td style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right' colspan='$colspan'>" . __('Total', 'sementes-cas-gcrs' ) . ": " . wc_price( $order_total_price) . "</td>
				</tr>";

				if ( $includetax ){
					$html .= "
					<tr class='gcr_report_ordertotal'>
						<td  style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right' colspan='$colspan'>" . __('Total with taxes', 'sementes-cas-gcrs' ) . ": " . wc_price( $order_total_price+$order_total_tax) . "</td>
					</tr>";
				}
			}
		}

		if ( $report == 'report_3' ) {

			if ( $includetax ){
				$colspan = 5;
			} else {
				$colspan = 4;
			}

			$terms = $wpdb->get_results($wpdb->prepare("
				SELECT DISTINCT `order_item_category_id` AS 'TermID',
				`order_item_category` AS 'TermName'
				FROM {$wpdb->prefix}eitagcr_report
				WHERE `cycle_id` = %s
			", $cycle_id), OBJECT );

			$html = "<table class='gcr_report' style='border: 1px solid black'>";

			foreach ($terms as $term) {
				$order_items = $wpdb->get_results($wpdb->prepare( "
				SELECT order_item_product AS 'Product',
				sum(order_item_quantity) AS 'Quantity',
				sum(order_item_tax) AS 'Tax',
				max(order_item_unit_tax) AS 'UnitTax',
				sum(order_item_price) AS 'Price',
				max(order_item_unit_price) AS 'UnitPrice'
				FROM {$wpdb->prefix}eitagcr_report
				WHERE `cycle_id` = %s AND `order_item_category_id` = %s
				GROUP BY `Product`
				", $cycle_id, $term->TermID), OBJECT );

				$order_total_price = 0;
				$order_total_tax = 0;

				$html .= "
					<tr class='gcr_report_header'>
						<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('Qty', 'sementes-cas-gcrs' ) . "</td>
						<td style='text-align: center; font-weight: bold; width: 80px; border: 1px solid black'>" . __('Product', 'sementes-cas-gcrs' ) . "</td>
						<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Unit price', 'sementes-cas-gcrs' ) . "</td>
						<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Price per product', 'sementes-cas-gcrs' ) . "</td>";

						if ( $includetax ){
								$html .= "
									<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('With tax', 'sementes-cas-gcrs' ) . "</td>
								";
						}

						$html .= "
					</tr>
				";

				$html .= "
				<tr class='gcr_report_orderid'>
					<td style='text-align: center; background-color: #CCC; border: 1px solid black; height: 15px; vertical-align: center' colspan='$colspan'>$term->TermName</td>
				</tr>";

				foreach ($order_items as $order_item) {
					$html .= "
						<tr class='gcr_report_orderdesc'>
						<td style='border: 1px solid black; text-align: center'>{$order_item->Quantity}</td>
						<td style='border: 1px solid black'>{$order_item->Product}</td>
						<td style='border: 1px solid black'>" . wc_price( $order_item->UnitPrice ) . "</td>
						<td style='border: 1px solid black'>" . wc_price( $order_item->Price ) . "</td>
						";

						if ( $includetax ){
								$html .= "
									<td style='border: 1px solid black;'>" . wc_price( $order_item->Price+$order_item->Tax ) . "</td>
								";
						}

						$html .= "
						</tr>
						";
					$order_total_price += $order_item->Price;
					$order_total_tax += $order_item->Tax;
				}

				$html .= "
				<tr class='gcr_report_ordertotal'>
					<td colspan='$colspan' style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right'>" . __('Total', 'sementes-cas-gcrs' ) . ": " . wc_price( $order_total_price ) . "</td>
				</tr>";

				if ( $includetax ){
					$html .= "<tr class='gcr_report_ordertotal'>
						<td colspan='$colspan' style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right'>" . __('Total with taxes', 'sementes-cas-gcrs' ) . ": " . wc_price( $order_total_price + $order_total_tax) . "</td>
					</tr>";
				}

			}
		}

		if ( $report == 'report_4' ) {

			$include_client_info = false;

      		if ( isset( $extra_configs['include_client_info_report_4'] ) ) {
				$include_client_info = $extra_configs[ 'include_client_info_report_4' ];
			}
			if ( $includetax ){
				$colspan = 6;
			} else {
				$colspan = 5;
			}

			$delivery_place_enabled = $this->get_sementes_option( 'enable_delivery_place' );

			if ( isset($delivery_place_enabled) && $delivery_place_enabled ) {
				$places = $wpdb->get_results($wpdb->prepare( "
					SELECT DISTINCT `order_delivery_place_id` AS 'PlaceID',
					`order_delivery_place` AS 'PlaceName'
					FROM {$wpdb->prefix}eitagcr_report
					WHERE `cycle_id` = %s
				", $cycle_id), OBJECT );

				$html = "";
				$html .= "<table class='gcr_report' style='page-break-after: always;'>";

				foreach ($places as $place) {
					$html .= "<tr><td colspan='$colspan' style='font-size: 18px; text-align: center; padding: 10px 0; background-color: #DDD'>" . $place->PlaceName . "</td></tr>";

					$html .= "
						<tr class='gcr_report_header'>
							<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('Qty', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 80px; border: 1px solid black'>" . __('Product', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 80px; border: 1px solid black'>" . __('Category', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Unit price', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Price per product', 'sementes-cas-gcrs' ) . "</td>";

							if ( $includetax ){
									$html .= "
										<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('With tax', 'sementes-cas-gcrs' ) . "</td>
									";
							}

							$html .= "
						</tr>
					";

					$orders = $wpdb->get_results($wpdb->prepare( "
						SELECT
						`order_id` AS 'OrderID',
						sum(`order_item_price`) AS TotalPrice,
						sum(`order_item_tax`) AS TotalTax,
						max(`order_first_name`) AS FirstName,
						max(`order_last_name`) AS LastName,
						max(`order_datetime`) AS DateTime,
						max(`order_payment_method_title`) AS PaymentMethod,
						max(`order_phone`) AS OrderPhone,
						max(`order_address_1`) AS OrderAddress1,
						max(`order_address_2`) AS OrderAddress2,
						max(`order_shipping_city`) AS OrderShippingCity,
						max(`order_shipping_state`) AS OrderShippingState
						FROM `{$wpdb->prefix}eitagcr_report`
						WHERE `cycle_id` = %s AND `order_delivery_place_id` = %s
						GROUP BY `order_id`
					", $cycle_id, $place->PlaceID), OBJECT );

					foreach ($orders as $order) {
						if ($include_client_info) {
							$html .= "
								<tr class='gcr_report_orderid'>
									<td class='order_info_expanded' style='text-align: center; background-color: #CCC; border: 1px solid black; height: 15px; vertical-align: center' colspan='$colspan'><span>{$order->FirstName} {$order->LastName} ({$order->OrderID}) </span><br/> <span>{$order->OrderAddress1} {$order->OrderAddress2} - {$order->OrderShippingCity} - {$order->OrderShippingState}</span> <br/> <span>{$order->OrderPhone}</span></td>
								</tr>";
						} else {
							$html .= "
								<tr class='gcr_report_orderid'>
									<td style='text-align: center; background-color: #CCC; border: 1px solid black; height: 15px; vertical-align: center' colspan='$colspan'>{$order->FirstName} {$order->LastName} ({$order->OrderID})</td>
								</tr>";
						}

						$order_items = $wpdb->get_results($wpdb->prepare( "
							SELECT order_item_product AS 'Product',
							order_first_name AS 'FirstName',
							order_last_name AS 'LastName',
							order_id AS 'OrderID',
							order_item_quantity AS 'Quantity',
							order_item_tax AS 'Tax',
							order_item_unit_tax AS 'UnitTax',
							order_item_price AS 'Price',
							order_item_unit_price AS 'UnitPrice',
							order_item_category AS 'Category'
							FROM {$wpdb->prefix}eitagcr_report
							WHERE `order_id` = %s
						", $order->OrderID), OBJECT );

						foreach ($order_items as $order_item) {

							$html .= "
								<tr class='gcr_report_orderdesc'>
								<td style='border: 1px solid black; text-align: center'>{$order_item->Quantity}</td>
								<td style='border: 1px solid black;'>{$order_item->Product}</td>
								<td style='border: 1px solid black;'>{$order_item->Category}</td>
								<td style='border: 1px solid black;'>" . wc_price( $order_item->UnitPrice) . "</td>
								<td style='border: 1px solid black;'>" . wc_price( $order_item->Price ) . "</td>
								";

								if ( $includetax ){
										$html .= "
											<td style='border: 1px solid black;'>" . wc_price( $order_item->Price+$order_item->Tax ) . "</td>
										";
								}

								$html .= "
								</tr>
								";
						}
						$html .= "
						<tr class='gcr_report_ordertotal'>
							<td  style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right' colspan='$colspan'>Total: " . wc_price( $order->TotalPrice ) . " (" . $order->PaymentMethod . ")</td>
						</tr>";

						if ( $includetax ){
							$html .= "
							<tr class='gcr_report_ordertotal'>
								<td  style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right' colspan='$colspan'>" . __('Total with taxes', 'sementes-cas-gcrs' ) . ": " . wc_price( $order->TotalPrice+$order->TotalTax) . "</td>
							</tr>";
						}
					}
				}
			} else {
				$shipping_methods = $wpdb->get_results($wpdb->prepare( "
					SELECT DISTINCT `order_shipping_method_name` AS 'ShippingMethod'
					FROM {$wpdb->prefix}eitagcr_report
					WHERE `cycle_id` = %s
				", $cycle_id), OBJECT );

				$html = "";
				$html .= "<table class='gcr_report' style='page-break-after: always;'>";

				foreach ($shipping_methods as $shipping_method) {
					$html .= "<tr><td colspan='$colspan' style='font-size: 18px; text-align: center; padding: 10px 0; background-color: #DDD'>" . $shipping_method->ShippingMethod . "</td></tr>";

					$html .= "
						<tr class='gcr_report_header'>
							<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('Qty', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 80px; border: 1px solid black'>" . __('Product', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 80px; border: 1px solid black'>" . __('Category', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Unit price', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Price per product', 'sementes-cas-gcrs' ) . "</td>";

							if ( $includetax ){
									$html .= "
										<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('With tax', 'sementes-cas-gcrs' ) . "</td>
									";
							}

							$html .= "
						</tr>
					";

					$orders = $wpdb->get_results($wpdb->prepare( "
						SELECT
						`order_id` AS 'OrderID',
						sum(`order_item_price`) AS TotalPrice,
						sum(`order_item_tax`) AS TotalTax,
						max(`order_first_name`) AS FirstName,
						max(`order_last_name`) AS LastName,
						max(`order_datetime`) AS DateTime,
						max(`order_payment_method_title`) AS PaymentMethod,
						max(`order_phone`) AS OrderPhone,
						max(`order_address_1`) AS OrderAddress1,
						max(`order_address_2`) AS OrderAddress2,
						max(`order_shipping_city`) AS OrderShippingCity,
						max(`order_shipping_state`) AS OrderShippingState
						FROM `{$wpdb->prefix}eitagcr_report`
						WHERE `cycle_id` = %s AND `order_shipping_method_name` = %s
						GROUP BY `order_id`
					", $cycle_id, $shipping_method->ShippingMethod), OBJECT );

					foreach ($orders as $order) {
						if ($include_client_info) {
							$html .= "
								<tr class='gcr_report_orderid'>
									<td class='order_info_expanded' style='text-align: center; background-color: #CCC; border: 1px solid black; height: 15px; vertical-align: center' colspan='$colspan'><span>{$order->FirstName} {$order->LastName} ({$order->OrderID}) </span><br/> <span>{$order->OrderAddress1} {$order->OrderAddress2} - {$order->OrderShippingCity} - {$order->OrderShippingState}</span> <br/> <span>{$order->OrderPhone}</span></td>
								</tr>";
						} else {
							$html .= "
								<tr class='gcr_report_orderid'>
									<td style='text-align: center; background-color: #CCC; border: 1px solid black; height: 15px; vertical-align: center' colspan='$colspan'>{$order->FirstName} {$order->LastName} ({$order->OrderID})</td>
								</tr>";
						}

						$order_items = $wpdb->get_results($wpdb->prepare( "
							SELECT order_item_product AS 'Product',
							order_first_name AS 'FirstName',
							order_last_name AS 'LastName',
							order_id AS 'OrderID',
							order_item_quantity AS 'Quantity',
							order_item_tax AS 'Tax',
							order_item_unit_tax AS 'UnitTax',
							order_item_price AS 'Price',
							order_item_unit_price AS 'UnitPrice',
							order_item_category AS 'Category'
							FROM {$wpdb->prefix}eitagcr_report
							WHERE `order_id` = %s
						", $order->OrderID), OBJECT );

						foreach ($order_items as $order_item) {

							$html .= "
								<tr class='gcr_report_orderdesc'>
								<td style='border: 1px solid black; text-align: center'>{$order_item->Quantity}</td>
								<td style='border: 1px solid black;'>{$order_item->Product}</td>
								<td style='border: 1px solid black;'>{$order_item->Category}</td>
								<td style='border: 1px solid black;'>" . wc_price( $order_item->UnitPrice) . "</td>
								<td style='border: 1px solid black;'>" . wc_price( $order_item->Price ) . "</td>
								";

								if ( $includetax ){
										$html .= "
											<td style='border: 1px solid black;'>" . wc_price( $order_item->Price+$order_item->Tax ) . "</td>
										";
								}

								$html .= "
								</tr>
								";
						}
						$html .= "
						<tr class='gcr_report_ordertotal'>
							<td  style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right' colspan='$colspan'>Total: " . wc_price( $order->TotalPrice ) . " (" . $order->PaymentMethod . ")</td>
						</tr>";

						if ( $includetax ){
							$html .= "
							<tr class='gcr_report_ordertotal'>
								<td  style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right' colspan='$colspan'>" . __('Total with taxes', 'sementes-cas-gcrs' ) . ": " . wc_price( $order->TotalPrice+$order->TotalTax) . "</td>
							</tr>";
						}
					}
				}
			}
		}
		if ( $report == 'report_5' ) {

			if ( $includetax ){
				$colspan = 5;
			} else {
				$colspan = 4;
			}

			
			$delivery_place_enabled = $this->get_sementes_option( 'enable_delivery_place' );

			if ( isset($delivery_place_enabled) && $delivery_place_enabled ) {

				$places = $wpdb->get_results($wpdb->prepare( "
					SELECT DISTINCT `order_delivery_place_id` AS 'PlaceID',
					`order_delivery_place` AS 'PlaceName'
					FROM {$wpdb->prefix}eitagcr_report
					WHERE `cycle_id` = %s
				", $cycle_id) , OBJECT );

				$html = "";
				$html .= "<table class='gcr_report' style='page-break-after: always;'>";

				foreach ($places as $place) {
					$html .= "<tr><td colspan='$colspan' style='font-size: 18px; text-align: center; padding: 10px 0; background-color: #DDD'>" . $place->PlaceName. "</td></tr>";
					$html .= "
						<tr class='gcr_report_header'>
							<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('Qty', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 80px; border: 1px solid black'>" . __('Product', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Unit price', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Price per product', 'sementes-cas-gcrs' ) . "</td>";

							if ( $includetax ){
									$html .= "
										<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('With tax', 'sementes-cas-gcrs' ) . "</td>
									";
							}

							$html .= "
						</tr>
					";
					$place_total_price = 0;
					$place_total_tax = 0;

					$terms = $wpdb->get_results($wpdb->prepare("
						SELECT DISTINCT `order_item_category_id` AS 'TermID',
						`order_item_category` AS 'TermName'
						FROM {$wpdb->prefix}eitagcr_report
						WHERE `cycle_id` = %s and `order_delivery_place_id` = %s
					", $cycle_id, $place->PlaceID), OBJECT );

					foreach ($terms as $term) {
						$html .= "
						<tr class='gcr_report_orderid'>
							<td style='text-align: center; background-color: #CCC; border: 1px solid black; height: 15px; vertical-align: center' colspan='$colspan'>$term->TermName</td>
						</tr>";

						$order_items = $wpdb->get_results($wpdb->prepare( "
						SELECT order_item_product AS 'Product',
						sum(order_item_quantity) AS 'Quantity',
						sum(order_item_tax) AS 'Tax',
						max(order_item_unit_tax) AS 'UnitTax',
						sum(order_item_price) AS 'Price',
						max(order_item_unit_price) AS 'UnitPrice'
						FROM {$wpdb->prefix}eitagcr_report
						WHERE order_item_category_id = %s
						AND order_delivery_place_id = %s
						AND cycle_id = %s
						GROUP BY `Product`
						", $term->TermID, $place->PlaceID, $cycle_id), OBJECT );

						foreach ($order_items as $order_item) {
							$html .= "
								<tr class='gcr_report_orderdesc'>
								<td style='border: 1px solid black; text-align: center'>{$order_item->Quantity}</td>
								<td style='border: 1px solid black'>{$order_item->Product}</td>
								<td style='border: 1px solid black'>" . wc_price( $order_item->UnitPrice ) . "</td>
								<td style='border: 1px solid black'>" . wc_price( $order_item->Price ) . "</td>
								";

								if ( $includetax ){
										$html .= "
											<td style='border: 1px solid black;'>" . wc_price( $order_item->Price+$order_item->Tax ) . "</td>
										";
								}

								$html .= "
								</tr>
								";
							$place_total_price += $order_item->Price;
							$place_total_tax += $order_item->Tax;
						}
					}
					$html .= "
					<tr class='gcr_report_ordertotal'>
						<td colspan='$colspan' style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right'>" . __('Total', 'sementes-cas-gcrs' ) . ": " . wc_price( $place_total_price ) . "</td>
					</tr>";
					if ( $includetax ){
						$html .= "
						<tr class='gcr_report_ordertotal'>
							<td colspan='$colspan' style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right'>" . __('Total with taxes', 'sementes-cas-gcrs' ) . ": " . wc_price( $place_total_price + $place_total_tax ) . "</td>
						</tr>";
					}

				}
			} else {
				$shipping_methods = $wpdb->get_results($wpdb->prepare( "
				SELECT DISTINCT `order_shipping_method_name` AS 'ShippingMethod'
				FROM {$wpdb->prefix}eitagcr_report
				WHERE `cycle_id` = %s
				", $cycle_id), OBJECT );

				$html = "";
				$html .= "<table class='gcr_report' style='page-break-after: always;'>";

				foreach ($shipping_methods as $shipping_method) {
					$html .= "<tr><td colspan='$colspan' style='font-size: 18px; text-align: center; padding: 10px 0; background-color: #DDD'>" . $shipping_method->ShippingMethod. "</td></tr>";
					$html .= "
						<tr class='gcr_report_header'>
							<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('Qty', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 80px; border: 1px solid black'>" . __('Product', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Unit price', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Price per product', 'sementes-cas-gcrs' ) . "</td>";

							if ( $includetax ){
									$html .= "
										<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('With tax', 'sementes-cas-gcrs' ) . "</td>
									";
							}

							$html .= "
						</tr>
					";
					$place_total_price = 0;
					$place_total_tax = 0;

					$terms = $wpdb->get_results($wpdb->prepare("
						SELECT DISTINCT `order_item_category_id` AS 'TermID',
						`order_item_category` AS 'TermName'
						FROM {$wpdb->prefix}eitagcr_report
						WHERE `cycle_id` = %s and `order_shipping_method_name` = %s
					", $cycle_id, $shipping_method->ShippingMethod), OBJECT );

					foreach ($terms as $term) {
						$html .= "
						<tr class='gcr_report_orderid'>
							<td style='text-align: center; background-color: #CCC; border: 1px solid black; height: 15px; vertical-align: center' colspan='$colspan'>$term->TermName</td>
						</tr>";

						$order_items = $wpdb->get_results($wpdb->prepare( "
						SELECT order_item_product AS 'Product',
						sum(order_item_quantity) AS 'Quantity',
						sum(order_item_tax) AS 'Tax',
						max(order_item_unit_tax) AS 'UnitTax',
						sum(order_item_price) AS 'Price',
						max(order_item_unit_price) AS 'UnitPrice'
						FROM {$wpdb->prefix}eitagcr_report
						WHERE order_item_category_id = %s
						AND order_shipping_method_name = %s
						AND cycle_id = %s
						GROUP BY `Product`
						", $term->TermID, $shipping_method->ShippingMethod, $cycle_id), OBJECT );

						foreach ($order_items as $order_item) {
							$html .= "
								<tr class='gcr_report_orderdesc'>
								<td style='border: 1px solid black; text-align: center'>{$order_item->Quantity}</td>
								<td style='border: 1px solid black'>{$order_item->Product}</td>
								<td style='border: 1px solid black'>" . wc_price( $order_item->UnitPrice ) . "</td>
								<td style='border: 1px solid black'>" . wc_price( $order_item->Price ) . "</td>
								";

								if ( $includetax ){
										$html .= "
											<td style='border: 1px solid black;'>" . wc_price( $order_item->Price+$order_item->Tax ) . "</td>
										";
								}

								$html .= "
								</tr>
								";
							$place_total_price += $order_item->Price;
							$place_total_tax += $order_item->Tax;
						}
					}
					$html .= "
					<tr class='gcr_report_ordertotal'>
						<td colspan='$colspan' style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right'>" . __('Total', 'sementes-cas-gcrs' ) . ": " . wc_price( $place_total_price ) . "</td>
					</tr>";
					if ( $includetax ){
						$html .= "
						<tr class='gcr_report_ordertotal'>
							<td colspan='$colspan' style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right'>" . __('Total with taxes', 'sementes-cas-gcrs' ) . ": " . wc_price( $place_total_price + $place_total_tax ) . "</td>
						</tr>";
					}

				}
			}
		}

		if ( $report == 'report_6' ) {

			if ( $includetax ){
				$colspan = 3;
			} else {
				$colspan = 2;
			}

			$paymentmethods = $wpdb->get_results($wpdb->prepare( "
				SELECT DISTINCT `order_payment_method_title` AS 'PaymentMethod'
				FROM {$wpdb->prefix}eitagcr_report
				WHERE `cycle_id` = %s
			", $cycle_id), OBJECT );

			$html = "";
			$html .= "<table class='gcr_report' style='page-break-after: always;'>";

			foreach ($paymentmethods as $paymentmethod) {
				$html .= "<tr><td colspan='$colspan' style='font-size: 18px; text-align: center; padding: 10px 0; background-color: #DDD'>" . $paymentmethod->PaymentMethod. "</td></tr>";
				$html .= "
					<tr class='gcr_report_header'>
						<td style='text-align: center; font-weight: bold; width: 80px; border: 1px solid black'>" . __('Order', 'sementes-cas-gcrs' ) . "</td>
						<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('Order Price', 'sementes-cas-gcrs' ) . "</td>";

						if ( $includetax ){
								$html .= "
									<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('With tax', 'sementes-cas-gcrs' ) . "</td>
								";
						}

						$html .= "
					</tr>
				";
				$paymentmethod_total_price = 0;
				$paymentmethod_total_price_tax = 0;

				$terms = $wpdb->get_results($wpdb->prepare("
					SELECT DISTINCT `order_item_category_id` AS 'TermID',
					`order_item_category` AS 'TermName'
					FROM {$wpdb->prefix}eitagcr_report
					WHERE `cycle_id` = %s and `order_payment_method_title` = '%s'
				", $cycle_id, $paymentmethod->PaymentMethod), OBJECT );

				foreach ($terms as $term) {
					$html .= "
					<tr class='gcr_report_orderid'>
						<td style='text-align: center; background-color: #CCC; border: 1px solid black; height: 15px; vertical-align: center' colspan='$colspan'>$term->TermName</td>
					</tr>";

					$orders = $wpdb->get_results($wpdb->prepare( "
					SELECT order_id AS 'OrderId',
					max(order_first_name) AS 'FirstName',
					max(order_last_name) AS 'LastName',
					sum(order_item_price) AS 'OrderPrice',
					sum(order_item_tax) AS 'OrderTax'
					FROM {$wpdb->prefix}eitagcr_report
					WHERE order_item_category_id = %s
					AND order_payment_method_title = '%s'
					AND cycle_id = %s
					GROUP BY `OrderId`
					", $term->TermID, $paymentmethod->PaymentMethod, $cycle_id), OBJECT );

					$paymentmethod_cat_total_price = 0;
					$paymentmethod_cat_total_price_tax = 0;
					foreach ($orders as $order) {
						$html .= "
							<tr class='gcr_report_orderdesc'>
							<td style='border: 1px solid black; text-align: center'>{$order->FirstName} {$order->LastName} ({$order->OrderId})</td>
							<td style='border: 1px solid black; text-align: right'>" . wc_price( $order->OrderPrice ) . "</td>
							";

							if ( $includetax ){
									$html .= "
										<td style='border: 1px solid black;'>" . wc_price( $order->OrderPrice+$order->OrderTax ) . "</td>
									";
							}

							$html .= "
							</tr>
							";
						$paymentmethod_total_price += $order->OrderPrice;
						$paymentmethod_total_price_tax += $order->OrderPrice + $order->OrderTax;
						$paymentmethod_cat_total_price += $order->OrderPrice;
						$paymentmethod_cat_total_price_tax += $order->OrderPrice + $order->OrderTax;
					}
					$html .= "
						<tr class='gcr_report_orderdesc'>
						<td style='border: 1px solid black; text-align: right'>" . __('Total', 'sementes-cas-gcrs' ) . "</td>
						<td style='border: 1px solid black; text-align: right'>" . wc_price( $paymentmethod_cat_total_price ) . "</td>
						";
						if ( $includetax ){
								$html .= "
									<td style='border: 1px solid black;'>" . wc_price( $paymentmethod_cat_total_price_tax ) . "</td>
								";
						}

						$html .= "
						</tr>
						";
				}
				$html .= "
				<tr class='gcr_report_ordertotal'>
					<td colspan='$colspan' style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right'>" . __('Payment Method Total', 'sementes-cas-gcrs' ) . ": " . wc_price( $paymentmethod_total_price ) . "</td>
				</tr>";

				if ( $includetax ){
					$html .= "
					<tr class='gcr_report_ordertotal'>
						<td colspan='$colspan' style='height: 30px; vertical-align: middle; font-weight: bold; border: 1px solid black; text-align: right'>" . __('Payment Method Total (with tax)', 'sementes-cas-gcrs' ) . ": " . wc_price( $paymentmethod_total_price_tax ) . "</td>
					</tr>";
				}
			}
		}
		if ( $report == 'report_7' ) {

			$all_wc_shipping_types = array();
			foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
				$all_wc_shipping_types[$method->id] = $method->get_method_title();
			}
		
			$colspan = 5;
			$remove_local_pickup = false;
			if ( isset( $extra_configs['remove_local_pickup_report_7'] ) ) {
				$remove_local_pickup = $extra_configs['remove_local_pickup_report_7'];
			}

			$shipping_types = $wpdb->get_results($wpdb->prepare( "
				SELECT DISTINCT `order_shipping_method_type` AS 'ShippingType'
				FROM {$wpdb->prefix}eitagcr_report
				WHERE `cycle_id` = %s
			", $cycle_id), OBJECT );

			$html = "";
			$html .= "<table class='gcr_report' style='page-break-after: always;'>";

			foreach ($shipping_types as $shipping_type) {
				if ( !($remove_local_pickup && $shipping_type->ShippingType == 'local_pickup') ) {
					$shipping_type_title = array_key_exists( $shipping_type->ShippingType, $all_wc_shipping_types ) ? $all_wc_shipping_types[$shipping_type->ShippingType] : '-';
					$html .= "<tr><td colspan='$colspan' style='font-size: 18px; text-align: center; padding: 10px 0; background-color: #DDD'>" . $shipping_type_title . "</td></tr>";

					$html .= "
						<tr class='gcr_report_header'>
							<td style='text-align: center; font-weight: bold; width: 30px; border: 1px solid black'>" . __('Order', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Telefone', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 50px; border: 1px solid black'>" . __('Endereço', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 10px; border: 1px solid black'>" . __('Zona', 'sementes-cas-gcrs' ) . "</td>
							<td style='text-align: center; font-weight: bold; width: 20px; border: 1px solid black'>" . __('Shipping', 'sementes-cas-gcrs' ) . "</td>
						</tr>
					";

					$orders = $wpdb->get_results($wpdb->prepare( "
						SELECT
						`order_id` AS 'OrderID',
						max(`order_first_name`) AS FirstName,
						max(`order_last_name`) AS LastName,
						max(`order_phone`) AS 'Phone',
						max(`order_shipping_method_name`) AS 'ShippingName',
						max(`order_zone`) AS 'OrderZone',
						max(`order_address_1`) AS 'Address1',
						max(`order_address_2`) AS 'Address2'
						FROM `{$wpdb->prefix}eitagcr_report`
						WHERE `cycle_id` = %s AND `order_shipping_method_type` = %s
						GROUP BY `order_id`
					", $cycle_id, $shipping_type->ShippingType), OBJECT );

					foreach ($orders as $order) {
						$html .= "
							<tr class='gcr_report_orderdesc'>
								<td style='border: 1px solid black; text-align: center'>{$order->FirstName} {$order->LastName} ({$order->OrderID})</td>
								<td style='border: 1px solid black; text-align: left'>{$order->Phone}</td>
								<td style='border: 1px solid black;'>{$order->Address1} {$order->Address2}</td>
								<td style='border: 1px solid black;'>{$order->OrderZone}</td>
								<td style='border: 1px solid black;'>{$order->ShippingName}</td>
							</tr>";
					}
				}
			}
		}
		if ( $report == 'report_shipping_tag' ) {
			$shipping_types = $wpdb->get_results($wpdb->prepare( "
				SELECT DISTINCT `order_shipping_method_type` AS 'ShippingType'
				FROM {$wpdb->prefix}eitagcr_report
				WHERE `cycle_id` = %s
			", $cycle_id), OBJECT );

			$html = "";
			$html .= "<table style='page-break-after: always;'>";

			$remove_local_pickup = false;
			if ( isset( $extra_configs['remove_local_pickup_report_7'] ) ) {
				$remove_local_pickup = $extra_configs['remove_local_pickup_report_7'];
			}

			foreach ($shipping_types as $shipping_type) {
				
				if(($remove_local_pickup) && ($shipping_type->ShippingType == 'local_pickup') )
					continue;
				$orders = $wpdb->get_results($wpdb->prepare( "
						SELECT
						`order_id` AS 'OrderID',
						max(`order_first_name`) AS FirstName,
						max(`order_last_name`) AS LastName,
						max(`order_phone`) AS 'Phone',
						max(`order_zone`) AS 'OrderZone',
						max(`order_address_1`) AS 'Address1',
						max(`order_address_2`) AS 'Address2',
						max(`order_shipping_city`) AS OrderShippingCity,
						max(`order_shipping_postcode`) AS OrderShippingPostcode
						FROM `{$wpdb->prefix}eitagcr_report`
						WHERE `cycle_id` = %s AND `order_shipping_method_type` = %s
						GROUP BY `order_id`
					", $cycle_id, $shipping_type->ShippingType), OBJECT );
						
				if ( !($shipping_type->ShippingType == 'local_pickup') ) {

					
					$html .= "
								<tr>
									<td colspan='10' style='border: 1px solid black;border-bottom: 1px solid black;text-align: center;font-size: 20px;'>
										<b>Pedidos para Entrega</b>
									</td>
								</tr>
					
					
						";
					foreach ($orders as $order) {
						
						$html .= "
									<tr>
						  				<td colspan='10' style='border: 1px solid black;border-bottom: 1px solid black;text-align: center;font-size: 15px;'>
										  ID Pedido: {$order->OrderID}<br/>{$order->FirstName} {$order->LastName}<br/>{$order->Address1}, {$order->Address2} - {$order->OrderZone}<br/>{$order->OrderShippingCity}, {$order->OrderShippingPostcode}<br/>{$order->Phone}
										</td>
									</tr>

								
						  	";
					}
				}
				if(!($remove_local_pickup) && ($shipping_type->ShippingType == 'local_pickup') )
				{

					$html .= "
								<tr>
									<td colspan='10' style='border: 1px solid black;border-bottom: 1px solid black;text-align: center;font-size: 20px;'>
										<b>Retirada no Local</b>
									</td>
								</tr>
					
					
						";

				foreach ($orders as $order) {
					
					$html .= "
								<tr>
									  <td colspan='10' style='border: 1px solid black;border-bottom: 1px solid black;text-align: center;font-size: 15px;'>
									  ID Pedido: {$order->OrderID}<br/>{$order->FirstName} {$order->LastName}<br/>Retirada no Local<br>{$order->Phone}
									</td>
								</tr>

							
						  ";
				}
				}
			}
		}

		$html .= "</table>";
		

		return $html;
	}

}
