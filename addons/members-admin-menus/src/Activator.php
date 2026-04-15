<?php
/**
 * Runs when the add-on is activated from Members > Add-Ons.
 *
 * @package    Members
 * @subpackage AddOns
 */

namespace Members\AddOns\AdminMenus;

defined( 'ABSPATH' ) || exit;

/**
 * Activator.
 */
class Activator {

	/**
	 * On activation, seed empty settings if missing.
	 *
	 * @return void
	 */
	public static function activate() {
		$existing = get_option( 'members_admin_menus_settings', null );
		if ( null === $existing || ! is_array( $existing ) ) {
			update_option( 'members_admin_menus_settings', self::get_default_option() );
		}
	}

	/**
	 * Default empty structure (same as get_default_settings() in app/functions.php).
	 *
	 * @return array
	 */
	public static function get_default_option() {
		require_once dirname( __DIR__ ) . '/app/defaults.php';
		return members_admin_menus_default_settings_data();
	}
}
