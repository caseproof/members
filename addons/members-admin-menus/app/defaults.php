<?php
/**
 * Default stored settings for Admin Menus (no hooks).
 *
 * Single source for Activator seeding and get_default_settings() / wp_parse_args.
 *
 * @package    Members
 * @subpackage AddOns
 */

namespace Members\AddOns\AdminMenus;

defined( 'ABSPATH' ) || exit;

/**
 * Schema version stored in _meta.version (increment when running migrations).
 */
const SETTINGS_SCHEMA_VERSION = 3;

/**
 * Default option array for members_admin_menus_settings.
 *
 * @return array
 */
function members_admin_menus_default_settings_data() {
	return array(
		'_meta'         => array(
			'version'        => SETTINGS_SCHEMA_VERSION,
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
