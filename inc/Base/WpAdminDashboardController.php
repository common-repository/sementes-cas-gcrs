<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Base;

use SementesCasGcrs\Base\BaseController;

/**
 *
 */
class WpAdminDashboardController extends BaseController {
	public $hide_menu;

	public function register() {
		$enable_simplified_dashboard = $this->get_sementes_option( 'enable_simplified_dashboard' );
		if ( isset($enable_simplified_dashboard) && $enable_simplified_dashboard ) {
			
			$this->set_hide_menu();

			add_action( 'wp_dashboard_setup', array( $this, 'remove_dashboard_widgets' ), 99, 1 );

			add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ), 99, 1 );

			add_action( 'admin_menu', array( $this, 'remove_wp_side_menu' ), 999999 );

			add_action( 'admin_bar_menu',  array( $this, 'remove_admin_bar_menu'), 999999 );
		}
	}

	function set_hide_menu() {
		if( isset( $_GET['sementes_admin_menu'] ) ) {
			update_option( 'sementes_hide_admin_menu', sanitize_text_field( $_GET['sementes_admin_menu'] ) );
		}
		$this->hide_menu = filter_var( get_option( 'sementes_hide_admin_menu', true), FILTER_VALIDATE_BOOLEAN);
	}

	function remove_admin_bar_menu( $wp_admin_bar ) {
		$wp_logo = $wp_admin_bar->get_node('wp-logo');
		$wp_logo->href = '/wp-admin/index.php';
		$wp_admin_bar->add_node($wp_logo);
		if ( $this->hide_menu ) {
			foreach ( $wp_admin_bar->get_nodes() as $node ) {
				if( $node->id != 'wp-logo' && $node->id != 'site-name' && $node->id != 'menu-toggle' ) {
					$wp_admin_bar->remove_node($node->id);
				}
			}
		}
	}

	function remove_wp_side_menu() {
		global $menu;
		global $submenu;
		
		add_action('admin_head', array( $this, 'sementes_icon_dash_menu' ));
		$menu[2][0] = __( 'Voltar ao painel', 'sementes-cas-gcr' );
		$menu[2][6] = $this->plugin_url . 'assets/icon-black.png';

		if ( $this->hide_menu ) {
			$menu = array_slice($menu, 0, 1, true);
			$submenu = array_slice($submenu, 1, sizeof($submenu), true);
			add_action('admin_head', array( $this, 'hide_collapse_button_menu_and_notices' ));
		}
	}

	public function hide_collapse_button_menu_and_notices() {
		echo "<style>#collapse-menu,
					.wp-admin .update-nag, .updated, 
					.error, .is-dismissible,
					.wp-admin .notice {display:none !important;}
			</style>";
	}

	public function sementes_icon_dash_menu() {
		echo "<style>

				.wp-admin #wpwrap {
					background-color: #fff;
				}
				.sementes-admin-dash-icon {
					height: 67px;
					width: 67px;
					margin-bottom: 15px;
					width: 95px;
				}	
				
				.sementes-admin-dash-card {
					font-family: -apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,Oxygen-Sans,Ubuntu,Cantarell,\"Helvetica Neue\",sans-serif;
					font-style: normal;
					font-weight: 700;
					font-size: 33px;
					color: #000;
					text-decoration: unset;
					display: flex;
					flex-direction: column;
					height: inherit;
					align-items: center;
					justify-content: center;
					color: #343434;
				}

				@media (max-width: 1200px) {
					.sementes-admin-dash-card {
						font-size: 20px;
					}
				}

				#wpbody .notice-warning {
					display: none;
				}
				
				#dashboard-widgets .meta-box-sortables {
					display: flex;
					flex-wrap: wrap;
					justify-content: space-around;
					align-items: baseline;
					align-content: space-around;
				}
				#dashboard-widgets .postbox-container {
					display: none;
				}
				#dashboard-widgets .postbox-container:first-child {
					display: block;
					width: 100% !important;
				}
				
				#wpbody #dashboard-widgets-wrap .postbox-header {
					display: none;
				}
				
				#wpbody #dashboard-widgets-wrap .postbox {
					height: 275px;
					background-color: #ffff;
					width: 30%;
					box-shadow: 0px 1.02965px 9.26681px rgba(0, 0, 0, 0.25);
					border-radius: 12.3557px;
				}
				#adminmenu .wp-first-item {
					display: inline-block;
				}
				#adminmenu .wp-first-item .wp-menu-name {
					margin-top: 80px;
					margin-left: 16px;
					/* height: 175px; */
					display: table-cell;
					width: 100%;
					vertical-align: bottom;
					float: left;
					padding: 0 !important;
					margin-bottom: 15px;
				}
				#adminmenu .wp-first-item .wp-menu-name:before {
					content: \"\";
					background-image: url(". $this->plugin_url ."assets/eva_arrow-back-fill.png);
					background-size: 100% 100%;
					display: inline-block;
					height: 14px;
					width: 18px;
					right: 4px;
					top: 2px;
					position: relative;
				}

				@media (max-width: 785px) {
					#adminmenu .wp-first-item .wp-menu-name {
						margin-top: 120px;
						font-size: 14px;
					}
				}
				#adminmenu .wp-first-item .wp-menu-image img {
					opacity: unset;
					padding-left: 13px;
					width: 95px;
					max-width: 95px;
					margin-left: 20px;
				}

				@media (min-width: 782px) and (max-width:960px) {
					#adminmenu .wp-first-item .wp-menu-image img {
						padding-left: 0;
						width: 32px;
						margin-left: 0;
					}
				}
				#adminmenu .wp-first-item.wp-has-current-submenu .wp-submenu .wp-submenu-head, #adminmenu .wp-first-item .wp-menu-arrow, #adminmenu .wp-menu-arrow div, #adminmenu li.current.wp-first-item  a.menu-top, #adminmenu li.wp-has-current-submenu.wp-first-item  a.wp-has-current-submenu {
					background: unset;
				}
				#dashboard-widgets .postbox .inside,
				#adminmenu .sementes-admin-dash-card
				 {
					height: inherit;
					margin-top: 0;
				 }
		
		</style>";

	}

	public function remove_dashboard_widgets() {
		global $wp_meta_boxes;
		$wp_meta_boxes['dashboard']['normal']['core'] = array();
		$wp_meta_boxes['dashboard']['side']['core'] = array();
		$wp_meta_boxes['dashboard']['normal']['high'] = array();
		$wp_meta_boxes['dashboard']['side']['high'] = array();
	}

	public function add_dashboard_widgets() {
		wp_add_dashboard_widget(
			'wpexplorer_dashboard_widget', // Widget slug.
			'My Custom Dashboard Widget', // Title.
			array($this,'update_products_widget_function') // Display function.
		);
		wp_add_dashboard_widget(
			'wpexplorer_dashboard_widget2', // Widget slug.
			'My Custom Dashboard Widget', // Title.
			array($this,'add_product_widget_function') // Display function.
		);
		wp_add_dashboard_widget(
			'wpexplorer_dashboard_widget7', // Widget slug.
			'My Custom Dashboard Widget', // Title.
			array($this,'manage_delivery_widget_function') // Display function.
		);
		wp_add_dashboard_widget(
			'wpexplorer_dashboard_widget3', // Widget slug.
			'My Custom Dashboard Widget', // Title.
			array($this,'manage_cycles_widget_function') // Display function.
		);
		wp_add_dashboard_widget(
			'wpexplorer_dashboard_widget4', // Widget slug.
			'My Custom Dashboard Widget', // Title.
			array($this,'manage_orders_widget_function') // Display function.
		);
		wp_add_dashboard_widget(
			'wpexplorer_dashboard_widget5', // Widget slug.
			'My Custom Dashboard Widget', // Title.
			array($this,'view_reports_widget_function') // Display function.
		);
		
		wp_add_dashboard_widget(
			'wpexplorer_dashboard_widget6', // Widget slug.
			'My Custom Dashboard Widget', // Title.
			array($this,'simple_menu_button') // Display function.
		);
	}
	
	/**
	 * Create the function to output the contents of your Dashboard Widget.
	 */
	public function update_products_widget_function() {
		echo "<a class=\"sementes-admin-dash-card\"  href=\"/wp-admin/edit.php?post_type=product\"><img class=\"sementes-admin-dash-icon\" src = \" {$this->plugin_url}assets/humbleicons_refresh.svg\" />
		" . __( 'Atualizar produtos', 'sementes-cas-gcr') . "</a>";
	}
	public function add_product_widget_function() {
		echo "<a class=\"sementes-admin-dash-card\"  href=\"/wp-admin/post-new.php?post_type=product\"><img class=\"sementes-admin-dash-icon\" src = \" {$this->plugin_url}assets/humbleicons_add.svg\" />
		" . __( 'Cadastrar produto', 'sementes-cas-gcr'). "</a>";
	}
	public function manage_cycles_widget_function() {
		echo "<a class=\"sementes-admin-dash-card\"  href=\"/wp-admin/edit.php?post_type=cycle\"><img class=\"sementes-admin-dash-icon\" src = \" {$this->plugin_url}assets/humbleicons_cycle.svg\" />
		" . __( 'Gerenciar ciclos', 'sementes-cas-gcr'). "</a>";
	}
	public function manage_orders_widget_function() {
		echo "<a class=\"sementes-admin-dash-card\"  href=\"/wp-admin/edit.php?post_type=shop_order\"><img class=\"sementes-admin-dash-icon\" src = \" {$this->plugin_url}assets/humbleicons_box.svg\" />
		" . __( 'Gerenciar pedidos', 'sementes-cas-gcr'). "</a>";
	}
	public function view_reports_widget_function() {
		echo "<a class=\"sementes-admin-dash-card\"  href=\"/wp-admin/admin.php?page=eitagcr_panel&tab=2\"><img class=\"sementes-admin-dash-icon\" src = \" {$this->plugin_url}assets/humbleicons_report.svg\" />
		" . __( 'Relatórios', 'sementes-cas-gcr'). "</a>";
	}
	public function manage_delivery_widget_function() {
		$delivery_place_enabled = $this->get_sementes_option( 'enable_delivery_place' );
		if ( isset($delivery_place_enabled) && $delivery_place_enabled ) {
			$del_method_link = '/wp-admin/edit.php?post_type=deliveryplace';
			$label = __( 'Locais de retirada', 'sementes-cas-gcrs' );
		} else {
			$del_method_link = '/wp-admin/admin.php?page=wc-settings&tab=shipping';
			$label = __( 'Formas de entrega', 'sementes-cas-gcrs' );
		}

		echo "<a class=\"sementes-admin-dash-card\"  href=\"$del_method_link\"><img class=\"sementes-admin-dash-icon\" src = \" {$this->plugin_url}assets/humbleicons_report.svg\" />
		" . $label . "</a>";
	}
	public function simple_menu_button() {
		$hide_menu = filter_var( get_option( 'sementes_hide_admin_menu', false), FILTER_VALIDATE_BOOLEAN);
		$hide_menu_btn = !$this->hide_menu ? 'true' : 'false';
		$hide_menu_text =$this->hide_menu ? __( 'Painel avançado', 'sementes-cas-gcr') : __( 'Painel simplificado', 'sementes-cas-gcr');
		echo "<a class=\"sementes-admin-dash-card\"  href=\"/wp-admin/index.php?sementes_admin_menu=" . $hide_menu_btn . "\"><img class=\"sementes-admin-dash-icon\" src = \" {$this->plugin_url}assets/humbleicons_engine.svg\" />
		" . $hide_menu_text . "</a>";
	}

}
