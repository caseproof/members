<?php
/**
 * Add-on activation routine.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/../bootstrap/autoload.php';

register_autoloader();

use Members\FileProtection\Services\Installer;
use Members\FileProtection\Services\MaintenanceService;
use Members\FileProtection\Services\ServerConfigResolver;
use Members\FileProtection\Services\Settings;

/**
 * Runs when the add-on is activated via Members → Add-ons.
 */
class Activator {

	/**
	 * @return void
	 */
	public static function activate() {
		$role = get_role( 'administrator' );

		if ( $role && ! $role->has_cap( Capabilities::CAP_MANAGE ) ) {
			$role->add_cap( Capabilities::CAP_MANAGE );
		}

		Installer::maybeInstall();
		MaintenanceService::schedule();

		$settings = new Settings();
		$server   = ServerConfigResolver::create( $settings );
		$server->write( $settings->getExtensions() );
	}
}
