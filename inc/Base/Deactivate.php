<?php
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Base;

use SementesCasGcrs\Base\BaseController;

class Deactivate extends BaseController {

	public static function deactivate() {
		update_option( 'sementes_active_cycle', null );
		flush_rewrite_rules();
	}
}
