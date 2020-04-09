<?php
/**
 * Uninstall routine.
 *
 * @package   MembersCategoryAndTagCaps
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-category-and-tag-caps
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

// Make sure we're actually uninstalling the plugin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	wp_die( sprintf(
		__( '%s should only be called when uninstalling the plugin.', 'members-category-and-tag-caps' ),
		'<code>' . __FILE__ . '</code>'
	) );
	exit;
}

// Get all roles.

$roles = wp_roles();

// Get an array of the plugin's caps.
//
// Note that we're not including `manage_categories` here because it is a core
// WordPress cap.  We don't want to remove it.

$plugin_caps = [
	'assign_categories',
	'edit_categories',
	'delete_categories',
	'manage_post_tags',
	'assign_post_tags',
	'edit_post_tags',
	'delete_post_tags'
];

// Loop through all roles and remove the plugin's capabilities because they will
// no longer work once the plugin is uninstalled.

foreach ( array_keys( $roles->get_names() ) as $name ) {

	// Get the role object.
	$role = $roles->get_role( $name );

	foreach ( $plugin_caps as $cap ) {

		// Check if the role has the cap before trying to remove it.
		if ( $role->has_cap( $cap ) ) {
			$roles->remove_cap( $name, $cap );
		}
	}
}
