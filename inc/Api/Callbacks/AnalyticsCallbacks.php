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

class AnalyticsCallbacks extends BaseController
{

	public function download_analytics($analytics_id, $cycle_ids, $analytics_format) {
		// Download report	
		$html = $this->get_analytics( $analytics_id, $cycle_ids );

		$html = apply_filters( 'eitagcr_get_report', $html, $analytics_id );

		$html = str_replace( "<bdi>", "", str_replace( "</bdi>", "", $html ));

		if ( $analytics_format  === 'XLSX'){
			$html = str_replace( get_woocommerce_currency_symbol(), "", $html );
			$html = str_replace( ".", "", $html );
			$html = str_replace( ",", ".", $html );
		}

		$cycle_titles = '';
		$i = 0;
		foreach( $cycle_ids as $cycle_id ) {
			$cycle_titles .= get_the_title( $cycle_id );
			if( $i !== array_key_last($cycle_ids)) {
				$cycle_titles .= ', ';
			}
			$i++;
		}

		$date = new DateTime( 'NOW', new DateTimeZone( 'America/Sao_Paulo' ) );
		$date = $date->format( 'd/m/Y H:i' );		
		if ($analytics_id == 'analytics_3') {
			$header = "<tr><td colspan='4'>Análise dos dados brutos gerada em $date</td></tr><tr><td style='height: 30px' colspan='4'>&nbsp;</td></tr>";
		}
		else {
			$header = "<tr><td colspan='4'>Análise gerada em $date, referente aos ciclos: {$cycle_titles}</td></tr><tr><td style='height: 30px' colspan='4'>&nbsp;</td></tr>";
		}
		

		$fid = fopen( $this->upload_base_dir . '/report.html', 'w' );
		fwrite( $fid, $header . $html );
		fclose( $fid );

		$reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
		$spreadsheet = $reader->load($this->upload_base_dir . '/report.html');

		$now   = new DateTime( 'NOW', new DateTimeZone( 'America/Sao_Paulo' ) );
		$filetitle = 'Sementes_Analises_' . $now->format( 'd-m-Y' );
		if ( $analytics_format  === 'PDF') {
			$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Mpdf');
			$filename = $this->upload_base_dir . '/' . $filetitle . '.pdf';
			$writer->save( $filename );
			$ctype = "application/pdf";
		} elseif ( $analytics_format  === 'XLSX' ) {
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
			$filename = $this->upload_base_dir . '/' . $filetitle . '.xlsx';
			$writer->save( $filename );
			$ctype = "application/vnd.ms-excel";
		} elseif ( $analytics_format  === 'CSV' ) {
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
			$filename = $this->upload_base_dir . '/' . $filetitle . '.csv';
			$writer->save( $filename );
			// $ctype = "application/vnd.ms-excel";
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


    public function get_analytics( $analytics, $cycle_ids ) {

		global $wpdb;

		if( !in_array( $analytics, [ 'analytics_1', 'analytics_2', 'analytics_raw' ] )){
			return "";
		}

		if( (!isset( $cycle_ids ) || count( $cycle_ids ) < 1) && $analytics != 'analytics_raw' ){
			return "";
		}

		$html = "";

		if ( $analytics == 'analytics_1' ) {

			$colspan = 1; 
			$sql = "
			SELECT *,
			C.order_item_stock as current_stock,
			C.order_item_stock + total_sold as total_registered
			FROM (
				SELECT 
				order_item_product,
				GROUP_CONCAT(IF(`row_number` = 1 AND `order_item_stock` = 0, `cycle_name`, NULL) SEPARATOR ', ') AS `sold_out_cycles`,
				COUNT(IF(`row_number` = 1 AND `order_item_stock` = 0, `cycle_id`, NULL)) AS `total_sold_out_cycles`,
				ROUND(AVG(IF(`row_number` = 1 AND `order_item_stock` = 0, DATEDIFF(`order_datetime`, `cycle_start_date`), NULL)), 2) AS `avg_date_diff`,
				SUM(`order_item_quantity`) as `total_sold`,
				ROUND(SUM(`order_item_quantity`)/COUNT(DISTINCT(`cycle_id`)), 2) AS `avg_sold`,
				MAX(`order_datetime`) as `current_stock_date`,
        MAX(`order_item_category`) as `term_name`
				FROM (
					SELECT DISTINCT 
					ROW_NUMBER() OVER(PARTITION BY `order_item_product`, `cycle_id` ORDER BY `order_datetime` DESC) AS `row_number`,
					`order_item_product`, 
					`order_datetime` as os_datetime,
          `order_item_category`,
					`cycle_id`,
					`cycle_name`,
					`order_datetime`,
					`cycle_start_date`,
					`order_id`, 
					`order_item_quantity`,
					`order_item_stock`
					FROM {$wpdb->prefix}eitagcr_report
					WHERE `cycle_id` IN (".implode(', ', array_fill(0, count($cycle_ids), '%s')).")
				) A
				GROUP BY `order_item_product`) B
			LEFT JOIN {$wpdb->prefix}eitagcr_report C ON B.order_item_product = C.order_item_product AND B.current_stock_date = C.order_datetime
			";

			$query = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $cycle_ids));
			$products = $wpdb->get_results($query, OBJECT );

			$html = "";
			$html .= "<table id='sementes_analytics_1' class='sementes_analytics_table display compact cell-border' style='width:100%'>";

			$html .= "
			<thead>
				<tr>
					<td>" . __('Produto', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Produtora', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Quantidade total vendida', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Média da quantidade vendida/ciclo', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Total de ciclos em que esgotou', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Ciclos em que esgotou', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Média de dias para esgotamento/ciclo', 'sementes-cas-gcrs' ) . "</td>";
				if (count($cycle_ids) < 2) {
					$html .= "
					<td>" . __('Estoque final do ciclo', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Total cadastrado no ciclo', 'sementes-cas-gcrs' ) . "</td>";
				}
				$html .= "
				</tr>
			</thead>
			<tbody>
			";

			foreach ($products as $product) {
				// $total_registred = $product->stock + $product->qty_order;
				$html .= "
				<tr>
					<td>$product->order_item_product</td>
					<td>$product->term_name</td>
					<td>$product->total_sold</td>
					<td>$product->avg_sold</td>
					<td>$product->total_sold_out_cycles</td>
					<td>$product->sold_out_cycles</td>
					<td>$product->avg_date_diff</td>";
				if (count($cycle_ids) < 2) {
					$html .= "
					<td>$product->current_stock</td>
					<td>$product->total_registered</td>";
				}
					
				$html .= "</tr>";
			}
			$html .= "</tbody>";
			
		}

		if ( $analytics == 'analytics_2' ) {

			$colspan = 1;
			$sql = "
				SELECT DISTINCT 
				`order_first_name`, 
				`order_last_name`,
				COUNT( DISTINCT(`order_id`) ) as qty_order,
				COUNT( DISTINCT(`cycle_id`) ) as qty_cycles,
				ROUND(SUM(`order_item_price`) / COUNT( DISTINCT(`order_id`) ), 2) as average_order_total,
				ROUND(SUM(`order_item_quantity`)/COUNT(DISTINCT(`order_id`)), 2)  as average_items_total,
				ROUND(SUM(`order_item_price`), 2)  as _total,
				GROUP_CONCAT(DISTINCT(`cycle_name`) SEPARATOR ', ') AS `cycles_names`
				FROM {$wpdb->prefix}eitagcr_report
				WHERE `cycle_id` IN (".implode(', ', array_fill(0, count($cycle_ids), '%s')).")
				GROUP BY `order_first_name`, `order_last_name`
			";

			$query = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $cycle_ids));
			$clients = $wpdb->get_results($query, OBJECT );

			$html = "";
			$html .= "<table id='sementes_analytics_2'  class='display compact sementes_analytics_table cell-border' style='width:100%'>";

			// $html .= "<tr><td colspan=4 style='font-size: 18px; text-align: center; padding: 10px 0; background-color: #DDD'>" . __( 'Clients', 'sementes-cas-gcr'). "</td></tr>";
			$html .= "
			<thead>
				<tr class='gcr_report_header'>
					<td>" . __('Nome', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Valor total gasto', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Quantidade de pedidos', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Valor médio do pedido', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Quantidade média de itens', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Quantidade de ciclos em que comprou', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Ciclos em que comprou', 'sementes-cas-gcrs' ) . "</td>
				</tr>
			</thead>
			<tbody>
			";

			foreach ($clients as $client) {
				$html .= "
				<tr>
					<td>$client->order_first_name $client->order_last_name</td>
					<td>$client->_total</td>
					<td>$client->qty_order</td>
					<td>$client->average_order_total</td>
					<td>$client->average_items_total</td>
					<td>$client->qty_cycles</td>
					<td>$client->cycles_names</td>
				</tr>";
			}
			$html .= "</tbody>";
				
			
		}

		if ( $analytics == 'analytics_raw' ) {
			$colspan = 1;
			$empty='';
			$sql = "
				SELECT * 
				FROM {$wpdb->prefix}eitagcr_report". '%1$s';
			

			$query = call_user_func_array(array($wpdb, 'prepare'),  array_merge(array($sql), array($empty)));
			$datas = $wpdb->get_results($query, OBJECT );

			$html = "";
			$html .= "<table id='sementes_analytics_3'  class='display compact sementes_analytics_table cell-border' style='width:100%'>";

			// $html .= "<tr><td colspan=4 style='font-size: 18px; text-align: center; padding: 10px 0; background-color: #DDD'>" . __( 'Clients', 'sementes-cas-gcr'). "</td></tr>";
			$html .= "
			<thead>
				<tr class='gcr_report_header'>
					<td>" . __('Id de Item do Pedido', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Id do Pedido', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Id do Ciclo', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Nome do Ciclo', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Data de Inicio do Ciclo', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Data do Pedido', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Nome', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Sobrenome', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Id do Local de Entrega "Sementes"', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Local de Entrega "Sementes"', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Metodo de Pagamento', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Telefone', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Endereco_1', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Endereco_2', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Zona', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('ID do Metodo de Entrega', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Tipo de Metodo de Entrega', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Nome do Metodo de Entrega', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Cidade', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Estado', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('ID do Produto', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Produto', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('ID da Categoria', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Categoria', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Quantidade de Itens', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Preço Unitario', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Taxa Unitaria', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Preço Total', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Taxa Total', 'sementes-cas-gcrs' ) . "</td>
					<td>" . __('Estoque', 'sementes-cas-gcrs' ) . "</td>
				</tr>
			</thead>
			<tbody>
			";
			foreach ($datas as $data) {

				$html .= "
				<tr>
					<td>$data->order_item_id</td>
					<td>$data->order_id</td>
					<td>$data->cycle_id</td>
					<td>$data->cycle_name</td>
					<td>$data->cycle_start_date</td>
					<td>$data->order_datetime</td>
					<td>$data->order_first_name</td>
					<td>$data->order_last_name</td>
					<td>$data->order_delivery_place_id</td>
					<td>$data->order_delivery_place</td>
					<td>$data->order_payment_method_title</td>
					<td>$data->order_phone</td>
					<td>$data->order_address_1</td>
					<td>$data->order_address_2</td>
					<td>$data->order_zone</td>
					<td>$data->order_shipping_method_id</td>
					<td>$data->order_shipping_method_type</td>
					<td>$data->order_shipping_method_name</td>
					<td>$data->order_shipping_city</td>
					<td>$data->order_shipping_state</td>
					<td>$data->order_item_product_id</td>
					<td>$data->order_item_product</td>
					<td>$data->order_item_category_id</td>
					<td>$data->order_item_category</td>
					<td>$data->order_item_quantity</td>
					<td>$data->order_item_unit_price</td>
					<td>$data->order_item_unit_tax</td>
					<td>$data->order_item_price</td>
					<td>$data->order_item_tax</td>
					<td>$data->order_item_stock</td>";
					
				$html .= "</tr>";
			}
			$html .= "</tbody>";

			return $html;
		}

		$html .= "</table>";

		return $html;
	}

}