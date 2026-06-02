<?php

namespace Members\Integration\ACF;

# Don't execute code if file is file is accessed directly.
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
require_once __DIR__ . '/src/Plugin.php';
require_once __DIR__ . '/src/functions-caps.php';
require_once __DIR__ . '/src/functions-roles.php';

# Boot the plugin.
plugin()->boot();
