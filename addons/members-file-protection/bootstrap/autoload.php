<?php
/**
 * PSR-4 autoloader for the File Protection add-on.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the add-on class autoloader once.
 *
 * Required before Activator/Deactivator run because Members core includes
 * those files directly without loading addon.php.
 *
 * @return void
 */
function register_autoloader() {
	static $registered = false;

	if ( $registered ) {
		return;
	}

	spl_autoload_register(
		static function ( $class ) {
			$prefix = __NAMESPACE__ . '\\';

			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}

			$relative = substr( $class, strlen( $prefix ) );
			$file     = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);

	$registered = true;
}
