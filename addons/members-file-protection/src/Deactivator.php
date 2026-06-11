<?php
/**
 * Add-on deactivation routine.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/../bootstrap/autoload.php';

register_autoloader();

use Members\FileProtection\Services\ApacheServerConfig;
use Members\FileProtection\Services\MaintenanceService;
use Members\FileProtection\Services\ServerConfigResolver;
use Members\FileProtection\Services\Settings;

/**
 * Removes runtime protection when the add-on is deactivated.
 */
class Deactivator {

	/**
	 * @return bool False when server rule cleanup could not complete.
	 */
	public static function deactivate(): bool {
		$settings = new Settings();
		$server   = ServerConfigResolver::create( $settings );

		$removed = $server->remove();

		// Always strip uploads .htaccess markers; nginx sites may still have them from prior stacks.
		$removed = ( new ApacheServerConfig( $settings ) )->remove() && $removed;

		if ( ! $removed ) {
			return false;
		}

		MaintenanceService::unschedule();

		delete_option( 'members_fp_nginx_manual' );
		delete_option( 'members_fp_htaccess_manual' );
		delete_option( 'members_fp_last_test_result' );

		/**
		 * Fires after File Protection cleanup on deactivation.
		 *
		 * Database tables and attachment meta are preserved so data survives reactivation.
		 */
		do_action( 'members_fp_deactivated' );

		return true;
	}
}
