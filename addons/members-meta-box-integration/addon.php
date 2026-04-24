<?php

namespace Members\Integration\MetaBox;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Wrapper for the plugin instance.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function plugin() {
	static $instance = null;

	if ( is_null( $instance ) ) {
		$instance = new Plugin();
	}

	return $instance;
}

# Bootstrap plugin.
require_once 'src/Plugin.php';
require_once 'src/functions-caps.php';
require_once 'src/functions-roles.php';

# Boot the plugin.
plugin()->boot();
