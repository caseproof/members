<?php
/**
 * Loads the File Protection add-on.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/autoload.php';

register_autoloader();

require_once dirname( __DIR__ ) . '/inc/functions.php';

/**
 * Returns the plugin instance.
 *
 * @return Plugin
 */
function plugin() {
	static $instance = null;

	if ( null === $instance ) {
		require_once __DIR__ . '/../src/Plugin.php';
		$instance = new Plugin( __DIR__ . '/..' );
	}

	return $instance;
}

/**
 * Alias for add-on bootstrap code.
 *
 * @return Plugin
 */
function members_file_protection_plugin() {
	return plugin();
}

plugin()->boot();
