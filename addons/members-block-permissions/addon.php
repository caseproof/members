<?php

namespace Members\BlockPermissions;

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
		$instance = new Plugin(
			__DIR__,
			plugin_dir_url( __FILE__ )
		);
	}

	return $instance;
}

# Bootstrap plugin.
require_once 'src/Block.php';
require_once 'src/Editor.php';
require_once 'src/Integration.php';
require_once 'src/Plugin.php';

# Boot the plugin.
plugin()->boot();
