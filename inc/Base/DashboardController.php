<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Base;

use SementesCasGcrs\Base\BaseController;
use SementesCasGcrs\Api\Callbacks\ReportCallbacks;
use Redux;

class DashboardController extends BaseController {

	public $callbacks;


	public $callbacks_mngr;

	public $pages = array();

	public function register() {
		$this->report_callbacks = new ReportCallbacks();

		$this->set_panel();

		add_filter( 'gettext', array( $this, 'translate_redux' ), 20 );

		add_action( 'plugins_loaded', array( $this, 'set_dashboard_section' ), 5, 1 );

	}

	public function set_panel() {
		$opt_name = $this->redux_opt_name;
		$args     = array(
			// REQUIRED!!  Change these values as you need/desire.
			'opt_name'           => $opt_name,

			// Name that appears at the top of your panel.
			'display_name'       => 'Sementes - Sistema de Cestas Agroecológicas e de Grupos de Consumo Responsável',

			// Version that appears at the top of your panel.
			'display_version'    => null,

			// Specify if the admin menu should appear or not. Options: menu or submenu (Under appearance only).
			'menu_type'          => 'menu',

			'ajax_save'          => true,

			'save_defaults'      => true,

			// Show the sections below the admin menu item or not.
			'allow_sub_menu'     => true,

			'menu_title'         => esc_html__( 'Sementes', 'sementes-cas-gcrs' ),

			'page_title'         => esc_html__( 'Sementes', 'sementes-cas-gcrs' ),

			'menu_icon'          => $this->plugin_url . 'assets/nova-logo.svg',

			// Show the panel pages on the admin bar.
			'admin_bar'          => false,

			// Set a different name for your global variable other than the opt_name.
			// 'global_variable'           => '',

			// Show the time the page took to load, etc.
			'dev_mode'           => false,

			// Enable basic customizer support.
			'customizer'         => false,

			// Order where the menu appears in the admin area. If there is any conflict, something will not show. Warning.
			'page_priority'      => 56,

			// Icon displayed in the admin panel next to your menu_title.
			'page_icon'          => 'dashicons-image-rotate',

			// Page slug used to denote the panel.
			'page_slug'          => 'eitagcr_panel',

			'show_import_export' => false,

			// // Set the theme of the option panel.  Use 'wp' to use a more modern style, default is classic.
			'admin_theme'        => 'wp',

			'page_permissions'          => 'manage_woocommerce',

		);

		Redux::set_args( $opt_name, $args );

	}

	public function set_dashboard_section() {
		$opt_name = $this->redux_opt_name;

		$html = $this->get_dashboard_html();

		Redux::set_section(
			$opt_name,
			array(
				'title'            => esc_html__( 'Dashboard', 'sementes-cas-gcrs' ),
				'id'               => 'dashboard',
				'customizer_width' => '400px',
				'icon'             => 'el el-home',
				'section_priority' => '1',
				'fields'           => array(
					array(
						'id'      => 'dashboard_info',
						'type'    => 'raw',
						'content' => $html,
					),
				),
			)
		);
	}

	public function translate_redux( $translated_text ) {
		if ( 'Save Changes' === $translated_text ) {
			$translated_text = 'Salvar Alterações';
		} elseif ( 'Reset Section' === $translated_text ) {
			$translated_text = 'Reiniciar Seção';
		} elseif ( 'Reset All' === $translated_text ) {
			$translated_text = 'Reiniciar Tudo';
		} elseif ( 'Settings have changed, you should save them!' === $translated_text ) {
			$translated_text = 'As configurações mudaram, você deveria salvá-las!';
		} elseif ( 'This field cannot be empty. Please provide a value.' === $translated_text) {
			$translated_text = 'Esse campo não pode ficar vazio.';
		}
		return $translated_text;
	}

	private function get_dashboard_html() {
		$active_cycle = get_option( 'sementes_active_cycle' );
    $last_active_cycle = get_option( 'sementes_last_active_cycle' );
		$suspended_cycle = get_option( 'sementes_suspended_cycle' );
    $cycle_to_show = $active_cycle;

		$html = '<div class="cycle-info-ctn sementes-dashboard" >
					<div class="cycle-status-ctn">
						<div class="cycle-status">';

		if ( !isset( $cycle_to_show ) || !$cycle_to_show ) {
      $cycle_to_show = $last_active_cycle;
    }

		if ( isset( $cycle_to_show ) && $cycle_to_show ) {
			$cycle_open_time   = get_post_meta( $cycle_to_show->ID, 'cycle_open_time', true );
			$cycle_close_time  = get_post_meta( $cycle_to_show->ID, 'cycle_close_time', true );
			$cycle_name        = get_the_title( $cycle_to_show->ID );
			$is_manual_cycle   = get_post_meta( $cycle_to_show->ID, 'is_manual_cycle', true );
			$orders            = $this->report_callbacks->get_orders( $cycle_to_show->ID );
			$total_cycle_price = $this->report_callbacks->get_cycle_total( $cycle_to_show->ID );
			$total_cycle_price = $total_cycle_price ? $total_cycle_price : '0,00';
			$cycle_suppliers   = $this->report_callbacks->get_cycle_suppliers( $cycle_to_show->ID );
			$suppliers_count   = count( $cycle_suppliers );
			$orders_count      = count( $orders );
			$supplier_taxonomy_name = $this->get_sementes_option( 'supplier_taxonomy_name' );

			if ( $is_manual_cycle ) {
        if ( isset( $active_cycle ) && $active_cycle ) {
          $html .= '<h1>'.sprintf( __( 'The manual cycle "%s" is ', 'sementes-cas-gcrs' ), esc_html( $cycle_name ) ). __( 'open', 'sementes-cas-gcrs' ) . '</h1>';
        } else {
          $html .= '<h1>'.sprintf( __( 'The manual cycle "%s" is ', 'sementes-cas-gcrs' ), esc_html( $cycle_name ) ). __( 'closed', 'sementes-cas-gcrs' ) . '</h1>';
        }
			} else {
        if ( isset( $active_cycle ) && $active_cycle ) {
          $html .= '<h1>'.sprintf( __( 'The automatic cycle "%s" is open from %s until %s', 'sementes-cas-gcrs' ), esc_html( $cycle_name ), esc_html( $cycle_open_time ), esc_html( $cycle_close_time ) ).'</h1>';
        } else {
          $html .= '<h1>'.sprintf( __( 'The automatic cycle "%s" was open from %s until %s', 'sementes-cas-gcrs' ), esc_html( $cycle_name ), esc_html( $cycle_open_time ), esc_html( $cycle_close_time ) ).'</h1>';
        }
			}
			if ( isset($suspended_cycle) && $suspended_cycle ) {
				$suspended_cycle_name        = get_the_title( $suspended_cycle->ID );
				$html .= '<h2 class="suspended-cycle-warning">'.sprintf( __( 'Warning: the cycle "%s" is suspended', 'sementes-cas-gcrs' ), esc_html( $suspended_cycle_name ) ).'</h2>';
			}
			$html .= '</div>';
			$html .= '<a href="' . get_admin_url() . '/post.php?post=' . esc_html( $cycle_to_show->ID ) . '&action=edit"> '.__( 'Edit cycle', 'sementes-cas-gcrs' ).'</a>
						</div>';

			$html .= '<div class="cycle-summary">
						<h1>' . __( 'Cycle summary', 'sementes-cas-gcrs' ) . '</h1>
						<h2>' . __( 'Orders', 'sementes-cas-gcrs' ) . ': ' . esc_html( $orders_count ) . '</h2>
						<h2>' . __( 'Total sold', 'sementes-cas-gcrs' ) . ': ' . wc_price( $total_cycle_price ) . '</h2>
						<h2>' . __( 'Number of ', 'sementes-cas-gcrs' ) . strtolower(esc_html($supplier_taxonomy_name)) . ': ' . esc_html( $suppliers_count ) . '</h2>
					</div>
					<div class="medium-div">
						<h1>' . __( 'Cycle orders', 'sementes-cas-gcrs' ) . '</h1>
						<div class="medium-div-list">';
			foreach ( $orders as $order ) {
				$html .= '<h2>' . esc_html( $order->first_name ) . ' ' . esc_html( $order->last_name ) . ': ' . wc_price( $order->total_price ) . ' (' . esc_html( $order->payment_method ) . ')</h2>';
			}
			$html .= '</div><a href="' . get_admin_url() . '/admin.php?page=eitagcr_panel&tab=2&cycle_id='.esc_html( $cycle_to_show->ID ).'">' . __( 'View reports', 'sementes-cas-gcrs' ) . '</a>';

			$html .= '</div> <div class="medium-div">
						<h1>' . esc_html($supplier_taxonomy_name) . '</h1>
						<div class="medium-div-list">';
			foreach ( $cycle_suppliers as $supplier ) {
				$supplier_term_name = empty($supplier->term_name) ? __( 'No ', 'sementes-cas-gcrs' ) . strtolower($supplier_taxonomy_name) : $supplier->term_name;
				$html .= '<h2>' . esc_html( $supplier_term_name ) . ': ' . wc_price( $supplier->total_price ) . ' - ' . esc_html( $supplier->total_orders ) . ' ' . sprintf( _n( 'order', 'orders', $supplier->total_orders, 'sementes-cas-gcrs' ) ) . '</h2>';
			}
			$html .= '</div></div></div>';

		} else {
			$html .= '<h1>' . __( 'There is no open cycle', 'sementes-cas-gcrs' ) . '</h1>
						<button  type="button" onclick="location.href=\'' . get_admin_url() . '/post-new.php?post_type=cycle\'">Criar novo ciclo</button>
			</div>';
		}
		return $html;
	}

}
