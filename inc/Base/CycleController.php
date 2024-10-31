<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Base;

use SementesCasGcrs\Api\CustomPostTypeApi;
use SementesCasGcrs\Base\BaseController;
use SementesCasGcrs\Api\Callbacks\CycleCallbacks;
use SementesCasGcrs\Api\Callbacks\ReportCallbacks;
use Redux;
use Redux_Metaboxes;
use DateTime;
use DateTimeZone;

/**
 *
 */
class CycleController extends BaseController {

	public $settings;

	public $callbacks;

	public $cycle_callbacks;

	public $active_cycle;

	public $is_cycle_closed;

	public $post_id;

	public $cycle_close_time_for_warning;

	public $warning_time;

	public $subpages = array();

	public function register() {
		$this->callbacks = new CycleCallbacks();

		$this->report_callbacks = new ReportCallbacks();

		$this->set_post_id();

		$this->set_active_cycle();

		$this->set_store_purchasability();

		$this->set_front_page();

		$this->create_cycle_cpt();

		// $this->set_cycle_edit_screen();

		add_action( 'plugins_loaded', array( $this, 'set_cycle_edit_screen' ), 20, 1 );

		add_action( 'plugins_loaded', array( $this, 'add_cycle_metabox_to_wc_order' ), 20, 1 );

		$this->show_time_warning();

		add_action( 'add_meta_boxes', array( $this, 'remove_slug_metabox' ) );

		// $this->clean_cycle_drafts();

		add_action( 'wp_ajax_cycle_pre_submit_validation', array( $this, 'pre_submit_validation' ) );

		add_filter( 'manage_cycle_posts_columns', array( $this, 'add_custom_columns_to_cycle_list' ), 10, 2 );

		add_action( 'manage_cycle_posts_custom_column', array( $this, 'add_custom_columns_value_to_cycle_list' ), 10, 2 );
		
		// Legacy CPT Orders custom collumn creation
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_cycle_column_to_wc_orders_list' ), 10, 2 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_cycle_column_value_to_wc_orders_list' ), 10, 2 );

		// HPOS collumn creation
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_cycle_column_to_wc_orders_list' ), 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'add_cycle_column_value_to_wc_orders_list' ), 10, 2 );

		add_filter( 'gettext', array( $this, 'translate_cycle_edit_page' ), 20 );

	}

	public function translate_cycle_edit_page( $translated_text ) {
		global $pagenow;
		if ( isset( $_GET['post_type'] ) && isset( $pagenow ) && $_GET['post_type'] == 'cycle' && $pagenow == 'edit.php' ) {
			if ( 'Data' === $translated_text ) {
				$translated_text = 'Publicado em ';
			} elseif ( 'Add New' === $translated_text ) {
				$translated_text = 'Adicionar novo';
			}
		}

		return $translated_text;
	}

	public function set_post_id() {
		if ( ! isset( $this->post_id ) ) {
			foreach ( $_GET as $key => $value ) {
				if ( $key === 'post' ) {
					$this->post_id = $value;
				}
			}
		}
	}

	private function set_active_cycle() {
		$this->active_cycle  = null;
		$this->suspended_cycle = null;
		$active_manual_cycle = null;
		$active_auto_cycle   = null;

		$args       = array(
			'post_type'      => 'cycle',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);
		$all_cycles = get_posts( $args );

		if ( count( $all_cycles ) == 0 ) {
			update_option( 'sementes_active_cycle', $this->active_cycle );
			return;
		}

		foreach ( $all_cycles as $cycle ) {
			$is_manual_cycle = get_post_meta( $cycle->ID, 'is_manual_cycle', true );
			$is_cycle_closed = get_post_meta( $cycle->ID, 'is_cycle_closed', true );
			$now   = new DateTime( 'NOW', new DateTimeZone( 'America/Sao_Paulo' ) );
			if ( $is_manual_cycle ) {
				if ( ! $is_cycle_closed ) {
					$active_manual_cycle = $cycle;
				}
			} else {
				$cycle_open_time  = get_post_meta( $cycle->ID, 'cycle_open_time', true );
				$cycle_close_time = get_post_meta( $cycle->ID, 'cycle_close_time', true );
				if ( $cycle_open_time && $cycle_close_time ) {
					$open  = $this->callbacks->create_date_from_any_format( $cycle_open_time);
					$close = $this->callbacks->create_date_from_any_format( $cycle_close_time);
					if ( $now > $open && $now < $close ) {
						$active_auto_cycle = $cycle;
						if ( $is_cycle_closed ) {
							update_post_meta( $cycle->ID, 'is_cycle_closed', false );
						}
					} elseif ( ( $now < $open || $now > $close ) && ! $is_cycle_closed ) {
						update_post_meta( $cycle->ID, 'is_cycle_closed', true );
					}
				}
			}
		}

		if ( isset( $active_auto_cycle ) && isset( $active_manual_cycle ) ) {
			$is_manual_preference_mode = $this->get_sementes_option( 'is_manual_preference_mode' );
			if ( $is_manual_preference_mode ) {
				$this->active_cycle = $active_manual_cycle;
				$this->suspended_cycle = $active_auto_cycle;
			} else {
				$this->active_cycle = $active_auto_cycle;
				$this->suspended_cycle = $active_manual_cycle;
			}
		} elseif ( isset( $active_manual_cycle ) && ! isset( $active_auto_cycle ) ) {
			$this->active_cycle = $active_manual_cycle;
		} elseif ( ! isset( $active_manual_cycle ) && isset( $active_auto_cycle ) ) {
			$this->active_cycle = $active_auto_cycle;
		}
		update_option( 'sementes_active_cycle', $this->active_cycle );
		update_option( 'sementes_suspended_cycle', $this->suspended_cycle );
		if ( isset($this->active_cycle) ) {
			update_option( 'sementes_last_active_cycle', $this->active_cycle );
			
			$cycle_start_date = get_post_meta( $this->active_cycle->ID, 'cycle_start_date', true );
			if ( empty($cycle_start_date ) ) {
				
				update_post_meta( $this->active_cycle->ID, 'cycle_start_date', $now->format('Y-m-d H:i:s'));
			}
		}
	}

	public function set_store_purchasability() {
		// Disable purchasability if cycle is closed
		if ( ! isset( $this->active_cycle ) ) {
			add_filter( 'woocommerce_is_purchasable', '__return_false' );
		}
	}

	public function show_time_warning() {
		if ( isset( $this->active_cycle ) ) {
			$is_manual_cycle = get_post_meta( $this->active_cycle->ID, 'is_manual_cycle', true );
			if ( ! $is_manual_cycle ) {
				$warning_time     = $this->get_sementes_option( 'warning_time' );
				$cycle_close_time = get_post_meta( $this->active_cycle->ID, 'cycle_close_time', true );
				$cycle_close_time = $this->callbacks->create_date_from_any_format( $cycle_close_time );
				$cycle_close_time = $cycle_close_time->format( 'Y/m/d H:i' );

				if ( $cycle_close_time && $warning_time ) {
					$this->cycle_close_time_for_warning = $cycle_close_time;
					$this->warning_time                 = $warning_time;
					add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_warning' ), 10 );
				}
			}
		}
	}

	/**
	 * Load warning timer Javascript.
	 *
	 * @access  public
	 * @return  void
	 * @since
	 */
	public function enqueue_warning() {
		wp_register_script( 'cycle-warning', $this->plugin_url . 'assets/warning.js', array( 'jquery' ) );
		wp_enqueue_script( 'cycle-warning' );
		wp_add_inline_script(
			'cycle-warning',
			'const PHP_WARNING_VARIABLES = ' . json_encode(
				array(
					'cycleCloseTime' => $this->cycle_close_time_for_warning,
					'warningTime'    => $this->warning_time,
				)
			),
			'before'
		);
	}

	// Define open/close page
	public function set_front_page() {
		update_option( 'show_on_front', 'page' );
		if ( ! isset( $this->active_cycle ) ) {
			$page = $this->get_sementes_option( 'page_closed' );
			update_option( 'page_on_front', $page );
		} else {
			$page = $this->get_sementes_option( 'page_open' );
			update_option( 'page_on_front', $page );
		}
	}


	private function create_cycle_cpt() {
		// Add cycle post type
		$cpt_api = new CustomPostTypeApi(
			'cycle',
			__( 'Cycles', 'sementes-cas-gcrs' ),
			__( 'Cycle', 'sementes-cas-gcrs' ),
			'',
			array(
				'supports'            => array( 'title' ),
				'hierarchical'        => false,
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'show_in_rest'        => false,
			)
		);

		add_action( 'admin_menu', array( $this, 'fix_cycle_admin_menu_submenu' ), 11 );
		add_filter( 'parent_file', array( $this, 'fix_cycle_admin_parent_file' ) );

	}

	public function fix_cycle_admin_menu_submenu() {
		add_submenu_page( 'eitagcr_panel', __( 'Cycles', 'sementes-cas-gcrs' ), __( 'Cycles', 'sementes-cas-gcrs' ), 'edit_pages', 'edit.php?post_type=cycle' );
	}

	public function fix_cycle_admin_parent_file( $parent_file ) {
		global $submenu_file, $current_screen;

		if ( $current_screen->post_type == 'cycle' ) {
			$submenu_file = 'edit.php?post_type=cycle';
			$parent_file  = 'eitagcr_panel';
		}
		return $parent_file;
	}

	public function remove_slug_metabox() {
		remove_meta_box( 'slugdiv', 'cycle', 'normal' );
	}

	public function set_cycle_edit_screen() {
		$cycle_status              = '';
		$can_open_manual_cycle     = true;
		$open_manual_cycle_warning = '';

		if ( isset( $this->post_id ) ) {
			if ( isset( $this->active_cycle ) && $this->post_id == $this->active_cycle->ID ) {
				$cycle_status = __( 'The cycle is ', 'sementes-cas-gcrs' ) . '<span class="cycle-status-tag open-cycle-tag">' . __( 'Open', 'sementes-cas-gcrs' ) . '</span>';
			} else if ( isset( $this->suspended_cycle ) && $this->post_id == $this->suspended_cycle->ID ) {
				$cycle_status = __( 'The cycle is ', 'sementes-cas-gcrs' ) . '<span class="cycle-status-tag suspended-cycle-tag">' . __( 'Suspended', 'sementes-cas-gcrs' ) . '</span>';
			} else {
				$cycle_status = __( 'The cycle is ', 'sementes-cas-gcrs' ) . '<span class="cycle-status-tag closed-cycle-tag">' . __( 'Closed', 'sementes-cas-gcrs' ) . '</span>';
			}
		}

		if ( isset( $this->active_cycle ) ) {
			$is_active_cycle_manual = get_post_meta( $this->active_cycle->ID, 'is_manual_cycle', true );
			if ( isset( $is_active_cycle_manual ) && $is_active_cycle_manual ) {
				if ( ! isset( $this->post_id ) || $this->post_id != $this->active_cycle->ID ) {
					$active_cycle_title        = get_the_title( $this->active_cycle->ID );
					$open_manual_cycle_warning = sprintf( __( 'Close cycle "%s" before opening another manual cycle', 'sementes-cas-gcrs' ), $active_cycle_title );
					$can_open_manual_cycle     = false;
				}
			}
		}

		$now_date = new DateTime( 'NOW', new DateTimeZone( 'America/Sao_Paulo' ) );
		$now_date = $now_date->format( 'd/m/Y H:i' );

		$default_cycle_type        = false;
		$is_manual_preference_mode = $this->get_sementes_option( 'is_manual_preference_mode' );
		if ( isset( $is_manual_preference_mode ) && $is_manual_preference_mode ) {
			$default_cycle_type = true;
		}

		$cycle_summary_html = $this->get_cycle_summary_html();

		Redux_Metaboxes::set_box(
			$this->redux_opt_name,
			array(
				'id'         => 'metabox-cycle-dates',
				'post_types' => array( 'cycle' ),
				'position'   => 'normal', // normal, advanced, side.
				'priority'   => 'low', // high, core, default, low.
				'title'      => esc_html__( 'Cycle settings', 'sementes-cas-gcrs' ),
				'sections'   => array(
					array(
						'icon_class' => 'icon-large',
						'icon'       => 'el-icon-home',
						'fields'     => array(
							array(
								'id'      => 'cycle_status',
								'type'    => 'raw',
								'content' => '<div>' . $cycle_status . '</div>',
							),
							array(
								'id'      => 'is_manual_cycle',
								'title'   => __( 'Cycle mode:', 'sementes-cas-gcrs' ),
								'type'    => 'switch',
								'default' => $default_cycle_type,
								'off'     => __( 'Automatic (date/time)', 'sementes-cas-gcrs' ),
								'on'      => __( 'Manual', 'sementes-cas-gcrs' ),
							),
							array(
								'id'       => 'cycle_open_time',
								'title'    => esc_html__( 'Opening', 'sementes-cas-gcrs' ),
								'type'     => 'date',
								'default'  => $now_date,
								'required' => array( 'is_manual_cycle', '=', false ),
							),
							array(
								'id'       => 'cycle_close_time',
								'title'    => esc_html__( 'Closing', 'sementes-cas-gcrs' ),
								'type'     => 'date',
								'default'  => $now_date,
								'required' => array( 'is_manual_cycle', '=', false ),
							),
							array(
								'id'       => 'is_cycle_closed',
								'title'    => __( 'Status', 'sementes-cas-gcrs' ),
								'subtitle' => $open_manual_cycle_warning,
								'type'     => 'switch',
								'disabled' => ! $can_open_manual_cycle,
								'default'  => true,
								'required' => array( 'is_manual_cycle', '=', true ),
								'on'       => __( 'Closed', 'sementes-cas-gcrs' ),
								'off'      => __( 'Open', 'sementes-cas-gcrs' ),
							),
							array(
								'id'      => 'cycle_summary',
								'type'    => 'raw',
								'content' => $cycle_summary_html,
							),
							array(
								'id'      => 'cycle_start_date',
								'type'    => 'date',
								'hidden'  => true,
							),
						),
					),
				),
			)
		);
	}

	public function add_cycle_metabox_to_wc_order() {
		$args      = array(
			'post_type'      => 'cycle',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);
		$allcycles = get_posts( $args );

		$cycles   = array();
		if ( count( $allcycles ) > 0 ) {
			foreach ( $allcycles as $cycle ) {
				$cycles[ $cycle->ID ] = $cycle->post_title;
			}
		} else {
			$cycles   = array( 0 => __( 'No cycle', 'sementes-cas-gcrs' ) );
		}

		Redux_Metaboxes::set_box(
			$this->redux_opt_name,
			array(
				'id'         => 'metabox-order-cycle',
				'post_types' => array( 'shop_order' ),
				'position'   => 'side', // normal, advanced, side.
				'priority'   => 'low', // high, core, default, low.
				'title'      => esc_html__( 'Cycle', 'sementes-cas-gcrs' ),
				'sections'   => array(
					array(
						'icon_class' => 'icon-large',
						'icon'       => 'el-icon-home',
						'fields'     => array(
							array(
								'id'      => '_eita_gcr_cycle_id',
								// 'type'    => 'text',
								'type'        => 'select',
								'data'        => $cycles,
							),
						),
					),
				),
			)
		);
	}


	public function add_cycle_column_to_wc_orders_list( $columns ) {
		$order_total_column = $columns['order_total'];
		unset( $columns['order_total'] );

		$columns         = array_merge( $columns, array( 'cycle_id' => __( 'Cycle', 'sementes-cas-gcrs' ) ) );
		$columns         = array_merge( $columns, array( 'order_total' => $order_total_column ) );


		return $columns;
	}

	public function add_cycle_column_value_to_wc_orders_list( $column_key, $order_or_post_id ) {
		
		if ( $column_key == 'cycle_id') {
			//  Since the second arg is a WC_Order in HPOS:
			$post_id = ( is_a($order_or_post_id,'WC_Order') ? $order_or_post_id->get_id() : $order_or_post_id ) ;
		
			$cycle_id = get_post_meta( $post_id, '_eita_gcr_cycle_id', true ); 
			$cycle_name = '-';
			if ( isset($cycle_id) && $cycle_id ) {
				$cycle_name = get_the_title( $cycle_id );
			}
				echo esc_html($cycle_name); 
		}
	}

	public function get_cycle_summary_html() {
		$html = '';
		if ( isset( $this->post_id ) && $this->post_id && !is_array( $this->post_id )) {
			$html         = '<div class="cycle-info-ctn">';
			$orders       = $this->report_callbacks->get_orders( $this->post_id );
			$orders_count = count( $orders );

			if ( $orders_count > 0 ) {
				$total_cycle_price = $this->report_callbacks->get_cycle_total( $this->post_id );
				$total_cycle_price = $total_cycle_price ? $total_cycle_price : '0,00';
				$cycle_suppliers   = $this->report_callbacks->get_cycle_suppliers( $this->post_id );
				$suppliers_count   = count( $cycle_suppliers );
				$orders_count      = count( $orders );
				$supplier_taxonomy_name = $this->get_sementes_option( 'supplier_taxonomy_name' );
				
				$html .= '<div class="cycle-summary">
							<h1>' . __( 'Cycle summary', 'sementes-cas-gcrs' ) . '</h1>
							<h2>' . __( 'Orders', 'sementes-cas-gcrs' ) . ': ' . esc_html( $orders_count ) . '</h2>
							<h2>' . __( 'Total sold', 'sementes-cas-gcrs' ) . ': ' . wc_price( $total_cycle_price ) . '</h2>
							<h2>' . __( 'Number of ', 'sementes-cas-gcrs' ) . strtolower(esc_html($supplier_taxonomy_name)) . ': ' . esc_html( $suppliers_count ) . '</h2>
						</div>
						<div class="medium-div">
							<h1>' . esc_html($supplier_taxonomy_name) . '</h1>
							<div class="medium-div-list">';
				foreach ( $cycle_suppliers as $supplier ) {
					$supplier_term_name = empty($supplier->term_name) ? __( 'No ', 'sementes-cas-gcrs' ) . strtolower($supplier_taxonomy_name) : $supplier->term_name;
					$html .= '<h2>' . esc_html( $supplier_term_name ) . ': ' . wc_price( $supplier->total_price ) . ' - ' . esc_html( $supplier->total_orders ) . ' ' . sprintf( _n( 'order', 'orders', $supplier->total_orders, 'sementes-cas-gcrs' ) ) . '</h2>';
				}
				$html .= '</div></div>
						<div class="tall-div">
							<h1>Pedidos do ciclo</h1>
							<div class="tall-div-list">';
				foreach ( $orders as $order ) {
					$html .= '<h2>' . esc_html( $order->first_name ) . ' ' . esc_html( $order->last_name ) . ': ' . wc_price( $order->total_price ) . ' (' . esc_html( $order->payment_method ) . ')</h2>';
				}
				$html .= '</div><a href="/wp-admin/admin.php?page=eitagcr_panel&tab=2&cycle_id='.esc_html( $this->post_id ).'">' . __( 'View reports', 'sementes-cas-gcrs' ) . '</a>';

				$html .= '</div></div>';

			} else {
				$html .= '<h1>' . __( 'There are no orders in this cycle', 'sementes-cas-gcrs' ) . '</h1>
						</div>';
			}
		}

		return $html;
	}

	public function validate_cycle( $post_id, $is_manual_cycle_validate, $is_cycle_closed, $open_time_validate, $close_time_validate ) {
		$args       = array(
			'post_type'      => 'cycle',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);
		$all_cycles = get_posts( $args );

		if ( ! $is_manual_cycle_validate ) {
			foreach ( $all_cycles as $cycle ) {
				if ( $cycle->ID != $post_id ) {
					$existing_cycle_is_manual_cycle = get_post_meta( $cycle->ID, 'is_manual_cycle', true );
					if ( ! $existing_cycle_is_manual_cycle ) {
						$existing_cycle_open_time  = get_post_meta( $cycle->ID, 'cycle_open_time', true );
						$existing_cycle_close_time = get_post_meta( $cycle->ID, 'cycle_close_time', true );
						$is_overlaped              = $this->dates_overlap( $existing_cycle_open_time, $existing_cycle_close_time, $open_time_validate, $close_time_validate );
						if ( $is_overlaped ) {
							return sprintf( __( 'The cycle you are creating overlaps the cycle: %1$s (%2$s - %3$s). Choose new dates and try again.', 'sementes-cas-gcrs' ), get_the_title( $cycle->ID ), $existing_cycle_open_time, $existing_cycle_close_time );
						}
					}
				}
			}
		} else {
			if ( ! $is_cycle_closed ) {
				foreach ( $all_cycles as $cycle ) {
					if ( $cycle->ID != $post_id ) {
						$existing_cycle_is_manual_cycle = get_post_meta( $cycle->ID, 'is_manual_cycle', true );
						if ( $existing_cycle_is_manual_cycle ) {
							$is_existing_cycle_closed = get_post_meta( $cycle->ID, 'is_cycle_closed', true );
							if ( ! $is_existing_cycle_closed ) {
								return sprintf( __( 'The cycle %s is already open. Close it before opening a new manual cycle.', 'sementes-cas-gcrs' ), get_the_title( $cycle->ID ) );
							}
						}
					}
				}
			}
		}

		return '';
	}

	public function pre_submit_validation() {

		check_ajax_referer( 'pre_cycle_publish_validation', 'security' );

		$error_message = '';
		$values = array();
		$post_ID = null;
		if ( isset( $_POST['form_data'] )) {
			parse_str( $_POST['form_data'], $values );
			$post_ID = isset( $values['post_ID'] ) ? sanitize_text_field( $values['post_ID'] ) : null;
			$values = array_map( 'sanitize_text_field', $values['eitagcr'] );
		}

		$is_manual_cycle  = isset( $values['is_manual_cycle'] ) ? sanitize_text_field( $values['is_manual_cycle'] ) : false;
		$cycle_open_time  = isset( $values['cycle_open_time'] ) ? sanitize_text_field( $values['cycle_open_time'] ) : false;
		$cycle_close_time = isset( $values['cycle_close_time'] ) ? sanitize_text_field( $values['cycle_close_time'] ) : false;
		$is_cycle_closed  = isset( $values['is_cycle_closed'] ) ? sanitize_text_field( $values['is_cycle_closed'] ) : false;
		if ( ! $is_manual_cycle && ( ! $cycle_open_time || empty( $cycle_open_time ) ) ) {
			$error_message = __( 'Opening time can\'t be empty', 'sementes-cas-gcrs' );
		} elseif ( ! $is_manual_cycle && ( ! $cycle_close_time || empty( $cycle_close_time ) ) ) {
			$error_message = __( 'Closing time can\'t be empty', 'sementes-cas-gcrs' );
		} else {
			$error_message = $this->validate_cycle( $post_ID, $is_manual_cycle, $is_cycle_closed, $cycle_open_time, $cycle_close_time );
		}

		if ( $error_message ) {
			$created_cycle_staus = get_post_status( $post_ID );
			echo esc_html($error_message);
		} else {
			echo 'true';
		}
	}

	private function dates_overlap( $start_one, $end_one, $start_two, $end_two ) {
		$start_one = $this->callbacks->create_date_from_any_format( $start_one );
		$end_one   = $this->callbacks->create_date_from_any_format( $end_one );
		$start_two = $this->callbacks->create_date_from_any_format( $start_two );
		$end_two   = $this->callbacks->create_date_from_any_format( $end_two );
		if ( $start_one <= $end_two && $end_one >= $start_two ) { // If the dates overlap
			return min( $end_one, $end_two )->diff( max( $start_two, $start_one ) )->days + 1; // return how many days overlap
		}

		return 0; // Return 0 if there is no overlap
	}

	public function clean_cycle_drafts() {
		$cycle_drafts = get_posts(
			array(
				'post_type'      => 'cycle',
				'post_status'    => 'draft',
				'posts_per_page' => -1,
			)
		);
		if ( $cycle_drafts ) {
			foreach ( $cycle_drafts as $cycle ) {
				wp_delete_post( $cycle->ID, true );
			}
		}
	}

	public function add_custom_columns_to_cycle_list( $columns ) {
		$date_column = $columns['date'];
		unset( $columns['date'] );
		$columns         = array_merge( $columns, array( 'cycle-status' => __( 'Status', 'sementes-cas-gcrs' ) ) );
		$columns         = array_merge( $columns, array( 'cycle-type' => __( 'Mode', 'sementes-cas-gcrs' ) ) );
		$columns = array_merge($columns, ['cycle-open-time' => __( 'Opening time', 'sementes-cas-gcrs' )]); 
    	$columns = array_merge($columns, ['cycle-close-time' => __( 'Closing time', 'sementes-cas-gcrs' )]); 
 		$columns = array_merge($columns, ['cycle-total-orders' => __( 'Total orders', 'sementes-cas-gcrs' )]); 
		$columns['date'] = $date_column;

		return $columns;
	}

	public function add_custom_columns_value_to_cycle_list( $column_key, $post_id ) {
		$is_manual_cycle = get_post_meta( $post_id, 'is_manual_cycle', true ); 
		switch ($column_key) { 
			case 'cycle-status': 
			 	if ( isset( $this->active_cycle ) && $post_id == $this->active_cycle->ID ) { 
			  		echo '<div class="cycle-status-tag open-cycle-tag">'.__( 'Open', 'sementes-cas-gcrs' ).'</div>'; 
			 	} else if ( isset( $this->suspended_cycle ) && $post_id == $this->suspended_cycle->ID ) {
					echo '<div class="cycle-status-tag suspended-cycle-tag">' . __( 'Suspended', 'sementes-cas-gcrs' ) . '</div>';
			   	} else { 
			  		echo '<div class="cycle-status-tag closed-cycle-tag">'.__( 'Closed', 'sementes-cas-gcrs' ).'</div>'; 
			 	} 
			 	break; 
			case 'cycle-type': 
				if ( $is_manual_cycle ) { 
			  		echo '<span>'.__( 'Manual', 'sementes-cas-gcrs' ).'</span>'; 
			 	} else { 
			  		echo '<span>'.__( 'Automatic', 'sementes-cas-gcrs' ).'</span>'; 
			 	} 
			 	break; 
			case 'cycle-open-time': 
			 	$cycle_open_time = get_post_meta( $post_id, 'cycle_open_time', true); 
			 	if ( $is_manual_cycle || empty($cycle_open_time) ){ 
			  		echo '-'; 
			 	} else { 
			  		echo $cycle_open_time; 
			 	} 
			 	break; 
			case 'cycle-close-time': 
			 	$cycle_close_time = get_post_meta( $post_id, 'cycle_close_time', true); 
			 	if ( $is_manual_cycle || empty($cycle_close_time) ){ 
			  		echo '-'; 
			 	} else { 
			  		echo $cycle_close_time; 
			 	} 
			 	break; 
			case 'cycle-total-orders': 
			 	$orders = $this->report_callbacks->get_orders( $post_id ); 
			 	$orders_count = count( $orders ); 
			 	echo $orders_count; 
			 	break; 
			default: 
			 	break; 
		}
	}
}
