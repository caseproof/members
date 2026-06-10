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
use Members\FileProtection\Services\Settings;

/**
 * Removes runtime protection when the add-on is deactivated.
 */
class Deactivator {

	/**
	 * @return void
	 */
	public static function deactivate() {
		MaintenanceService::unschedule();

		$apache = new ApacheServerConfig( new Settings() );
		$apache->remove();

		delete_option( 'members_fp_nginx_manual' );
		delete_option( 'members_fp_htaccess_manual' );
		delete_option( 'members_fp_last_test_result' );

		/**
		 * Fires after File Protection cleanup on deactivation.
		 *
		 * Database tables and attachment meta are preserved so data survives reactivation.
		 */
		do_action( 'members_fp_deactivated' );
	}
}
