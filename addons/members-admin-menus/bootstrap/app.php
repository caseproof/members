<?php
/**
 * Loads the Admin Menus add-on.
 *
 * @package    Members
 * @subpackage AddOns
 */

namespace Members\AddOns\AdminMenus;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the app instance.
 *
 * @return App
 */
function app() {
	static $instance = null;

	if ( is_null( $instance ) ) {
		$dir = trailingslashit( plugin_dir_path( __FILE__ ) );
		require_once $dir . '../app/class-app.php';
		$config   = require_once $dir . '../config/app.php';
		$instance = new App( $config );
	}

	return $instance;
}

require_once app()->dir . 'app/functions.php';

if ( is_admin() ) {
	require_once app()->dir . 'app/functions-admin.php';
}
