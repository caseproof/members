<?php
/**
 * Loads the plugin.
 *
 * @package   MembersAdminAccess
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-admin-access
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Members\AddOns\AdminAccess;

/**
 * Single instance of the application.  Primarily use this for getting
 * config data.
 *
 * @since  1.0.0
 * @access public
 * @return object
 */
function app() {

	static $instance = null;

	if ( is_null( $instance ) ) {

		$dir = trailingslashit( plugin_dir_path( __FILE__ ) );

		require_once( $dir . '../app/class-app.php' );

		$config = require_once( $dir . '../config/app.php' );

		$instance = new App( $config );
	}

	return $instance;
}

# Load functions files.
require_once( app()->dir . 'app/functions.php' );

# Load admin functions files.
if ( is_admin() ) {

	require_once( app()->dir . 'app/functions-admin.php' );
}
