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
	 * Default option version.
	 */
	const OPTION_VERSION = 3;

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
	 * Default empty structure.
	 *
	 * @return array
	 */
	public static function get_default_option() {
		return array(
			'_meta'         => array(
				'version'        => self::OPTION_VERSION,
				'admin_editable' => false,
			),
			'roles'         => array(),
			'users'         => array(),
			'custom_items'  => array(),
			'capabilities'  => array(),
			'_defaults'     => array(
				'captured' => false,
			),
		);
	}
}
