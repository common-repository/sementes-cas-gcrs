<?php
/**
 * @package  EitagcrPlugin
 */
/*
 * Plugin Name: Sementes - Sistema de Cestas Agroecológicas e de Grupos de Consumo Responsável
 * Version: 1.2.21
 * Plugin URI: https://gitlab.com/eita/eitagcr
 * Description: This plugin offers some enhancements to the Woocommerce e-commerce platform to make it fit for using in Responsible Consumer Groups (GCR). It assumes GCRs are composed by several consumers and several suppliers, and the there is a buying cycle.
 * Author: Cooperativa EITA
 * Author URI: https://eita.coop.br
 * Requires at least: 5.7
 * Tested up to: 6.6
Text Domain: sementes-cas-gcrs
Domain Path: /lang/
*/

/*
Copyright 2020 - 2022  Cooperativa EITA

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

// If this file is called firectly, abort!!!
defined( 'ABSPATH' ) || die( 'Hey, what are you doing here? You silly human!' );

// Require once the Composer Autoload
if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __FILE__ ) . '/vendor/autoload.php';
}

/**
 * The code that runs during plugin activation
 */
function activate_scasgcrs_plugin() {
	SementesCasGcrs\Base\Activate::activate();
}
register_activation_hook( __FILE__, 'activate_scasgcrs_plugin' );

/**
 * The code that runs during plugin deactivation
 */
function deactivate_scasgcrs_plugin() {
	SementesCasGcrs\Base\Deactivate::deactivate();
}
register_deactivation_hook( __FILE__, 'deactivate_scasgcrs_plugin' );

/**
 * Initialize all the core classes of the plugin
 */
if ( class_exists( 'SementesCasGcrs\\Init' ) ) {
	SementesCasGcrs\Init::register_services();
}
