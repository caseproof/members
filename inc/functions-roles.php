<?php
/**
 * Role-related functions that extend the built-in WordPress Roles API.
 *
 * @package    Members
 * @subpackage Includes
 * @author     The MemberPress Team 
 * @copyright  Copyright (c) 2009 - 2018, The MemberPress Team
 * @link       https://members-plugin.com/
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

# Register roles.
add_action( 'wp_roles_init',          'members_register_roles',         95 );
add_action( 'members_register_roles', 'members_register_default_roles',  5 );

/**
 * Fires the role registration action hook.
 *
 * @since  2.0.0
 * @access public
 * @param  object  $wp_roles
 * @return void
 */
function members_register_roles( $wp_roles ) {

	do_action( 'members_register_roles', $wp_roles );
}

/**
 * Registers any roles stored globally with WordPress.
 *
 * @since  2.0.0
 * @access public
 * @param  object  $wp_roles
 * @return void
 */
function members_register_default_roles( $wp_roles ) {

	foreach ( $wp_roles->roles as $name => $object ) {

		$args = array(
			'label' => $object['name'],
			'caps'  => $object['capabilities']
		);

		members_register_role( $name, $args );
	}

	// Unset any roles that were registered previously but are not currently available.
	foreach ( members_get_roles() as $role ) {

		if ( ! isset( $wp_roles->roles[ $role->name ] ) )
			members_unregister_role( $role->name );
	}
}

/**
 * Returns the instance of the role registry.
 *
 * @since  2.0.0
 * @access public
 * @return object
 */
function members_role_registry() {

	return \Members\Registry::get_instance( 'role' );
}

/**
 * Returns all registered roles.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function members_get_roles() {

	return members_role_registry()->get_collection();
}

/**
 * Registers a role.
 *
 * @since  2.0.0
 * @access public
 * @param  string  $name
 * @param  array   $args
 * @return void
 */
function members_register_role( $name, $args = array() ) {

	members_role_registry()->register( $name, new \Members\Role( $name, $args ) );
}

/**
 * Unregisters a role.
 *
 * @since  2.0.0
 * @access public
 * @param  string  $name
 * @return void
 */
function members_unregister_role( $name ) {

	members_role_registry()->unregister( $name );
}

/**
 * Returns a role object.
 *
 * @since  2.0.0
 * @access public
 * @param  string  $name
 * @return object
 */
function members_get_role( $name ) {

	return members_role_registry()->get( $name );
}

/**
 * Checks if a role object exists.
 *
 * @since  2.0.0
 * @access public
 * @param  string  $name
 * @return bool
 */
function members_role_exists( $name ) {

	return members_role_registry()->exists( $name );
}

/* ====== Multiple Role Functions ====== */

/**
 * Returns an array of editable roles.
 *
 * @since  2.0.0
 * @access public
 * @global array  $wp_roles
 * @return array
 */
function members_get_editable_roles() {
	global $wp_roles;

	$editable = function_exists( 'get_editable_roles' ) ? get_editable_roles() : apply_filters( 'editable_roles', $wp_roles->roles );

	return array_keys( $editable );
}

/**
 * Returns an array of uneditable roles.
 *
 * @since  2.0.0
 * @access public
 * @return array
 */
function members_get_uneditable_roles() {

	return array_diff( array_keys( members_get_roles() ), members_get_editable_roles() );
}

/**
 * Returns an array of core WP roles.  Note that we remove any that are not registered.
 *
 * @since  2.0.0
 * @access public
 * @return array
 */
function members_get_wordpress_roles() {

	$roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

	return array_intersect( $roles, array_keys( members_get_roles() ) );
}

/**
 * Returns an array of the roles that have users.
 *
 * @since  2.0.0
 * @access public
 * @return array
 */
function members_get_active_roles() {

	$has_users = array();

	foreach ( members_get_role_user_count() as $role => $count ) {

		if ( 0 < $count )
			$has_users[] = $role;
	}

	return $has_users;
}

/**
 * Returns an array of the roles that have no users.
 *
 * @since  2.0.0
 * @access public
 * @return array
 */
function members_get_inactive_roles() {

	return array_diff( array_keys( members_get_roles() ), members_get_active_roles() );
}

/**
 * Returns a count of all the available roles for the site.
 *
 * @since  1.0.0
 * @access public
 * @return int
 */
function members_get_role_count() {

	return count( $GLOBALS['wp_roles']->role_names );
}

/* ====== Single Role Functions ====== */

/**
 * Sanitizes a role name.  This is a wrapper for the `sanitize_key()` WordPress function.  Only
 * alphanumeric characters and underscores are allowed.  Hyphens are also replaced with underscores.
 *
 * @since  1.0.0
 * @access public
 * @return int
 */
function members_sanitize_role( $role ) {

	$_role = strtolower( $role );
	$_role = preg_replace( '/[^a-z0-9_\-\s]/', '', $_role );

	return apply_filters( 'members_sanitize_role', str_replace( ' ', '_', $_role ), $role );
}

/**
 * WordPress provides no method of translating custom roles other than filtering the
 * `translate_with_gettext_context` hook, which is very inefficient and is not the proper
 * method of translating.  This is a method that allows plugin authors to hook in and add
 * their own translations.
 *
 * Note the core WP `translate_user_role()` function only translates core user roles.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $role
 * @return string
 */
function members_translate_role( $role ) {
	global $wp_roles;

	return members_translate_role_hook( $wp_roles->role_names[ $role ], $role );
}

/**
 * Hook for translating user roles. I needed to separate this from the primary
 * `members_translate_role()` function in case `$wp_roles` was not yet available
 * but both the role and role label were.
 *
 * @since  2.0.1
 * @access public
 * @param  string  $label
 * @param  string  $role
 * @return string
 */
function members_translate_role_hook( $label, $role ) {

	return apply_filters( 'members_translate_role', translate_user_role( $label ), $role );
}

/**
 * Conditional tag to check if a role has any users.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function members_role_has_users( $role ) {

	return in_array( $role, members_get_active_roles() );
}

/**
 * Conditional tag to check if a role has any capabilities.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function members_role_has_caps( $role ) {

	return members_get_role( $role )->has_caps;
}

/**
 * Counts the number of users for all roles on the site and returns this as an array.  If
 * the `$role` parameter is given, the return value will be the count just for that particular role.
 *
 * @since  0.2.0
 * @access public
 * @param  string     $role
 * @return int|array
 */
function members_get_role_user_count( $role = '' ) {
	global $wpdb;

	// If the count is not already set for all roles, let's get it.
	if ( empty( members_plugin()->role_user_count ) ) {
		// Use transient cache to avoid full table scan + PHP processing on every request.
		$cached = get_transient( members_role_user_count_transient_key() );
		if ( is_array( $cached ) ) {
			members_plugin()->role_user_count = $cached;
		} else {
			// Count all users with each role anywhere in wp_capabilities (primary or secondary).
			// Matches wp user list --role= behavior and fixes undercounting when multiple roles are enabled.
			$blog_prefix = $wpdb->get_blog_prefix();
			$meta_key    = $blog_prefix . 'capabilities';

			// Fetch only meta_value to reduce memory (no stdClass objects per row).
			$results = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
					$meta_key
				)
			);

			// Only count keys that are registered roles, not individual capabilities (e.g. edit_posts).
			$all_roles   = array_keys( wp_roles()->get_names() );
			$role_counts = array_fill_keys( $all_roles, 0 );

			if ( is_array( $results ) ) {
				foreach ( $results as $meta_value ) {
					// Safe deserialization: prevent object injection (allowed_classes => false).
					$caps = @unserialize( trim( $meta_value ), array( 'allowed_classes' => false ) );
					if ( ! is_array( $caps ) ) {
						continue;
					}
					// Count only granted capabilities that are actual role names.
					$user_roles = array_intersect_key( array_filter( $caps ), $role_counts );
					foreach ( array_keys( $user_roles ) as $role_name ) {
						$role_counts[ $role_name ]++;
					}
				}
			}
			members_plugin()->role_user_count = $role_counts;
			set_transient( members_role_user_count_transient_key(), $role_counts, 12 * HOUR_IN_SECONDS );
		}
	}

	// Return the role count.
	if ( $role ) {
		return isset( members_plugin()->role_user_count[ $role ] ) ? members_plugin()->role_user_count[ $role ] : 0;
	}

	// If the `$role` parameter wasn't passed into this function, return the array of user counts.
	return members_plugin()->role_user_count;
}

/**
 * Returns the transient key used for caching role user counts (multisite-safe).
 *
 * @since  3.2.20
 * @return string
 */
function members_role_user_count_transient_key() {
	return 'members_role_user_count_' . get_current_blog_id();
}

/**
 * Clears the cached role user counts. Called when user roles change.
 *
 * @since  3.2.20
 */
function members_clear_role_user_count_cache() {
	delete_transient( members_role_user_count_transient_key() );
}

add_action( 'set_user_role', 'members_clear_role_user_count_cache' );
add_action( 'add_user_role', 'members_clear_role_user_count_cache' );
add_action( 'remove_user_role', 'members_clear_role_user_count_cache' );
add_action( 'add_user_to_blog', 'members_clear_role_user_count_cache' );
add_action( 'remove_user_from_blog', 'members_clear_role_user_count_cache' );

/**
 * Returns the number of granted capabilities that a role has.
 *
 * @since  1.0.0
 * @access public
 * @param  string
 * @return int
 */
function members_get_role_granted_cap_count( $role ) {

	return members_get_role( $role )->granted_cap_count;
}

/**
 * Returns the number of denied capabilities that a role has.
 *
 * @since  1.0.0
 * @access public
 * @param  string
 * @return int
 */
function members_get_role_denied_cap_count( $role ) {

	return members_get_role( $role )->denied_cap_count;
}

/**
 * Conditional tag to check whether a role can be edited.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $role
 * @return bool
 */
function members_is_role_editable( $role ) {

	return in_array( $role, members_get_editable_roles() );
}

/**
 * Conditional tag to check whether a role is a core WordPress role.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $role
 * @return bool
 */
function members_is_wordpress_role( $role ) {

	return in_array( $role, array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ) );
}

/* ====== URLs ====== */

/**
 * Returns the URL for the add-new role admin screen.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function members_get_new_role_url() {

	return add_query_arg( 'page', 'members', admin_url( 'admin.php' ) );
}

/**
 * Returns the URL for the clone role admin screen.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $role
 * @return string
 */
function members_get_clone_role_url( $role ) {

	return add_query_arg( 'clone', $role, members_get_new_role_url() );
}

/**
 * Returns the URL for the edit roles admin screen.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function members_get_edit_roles_url() {

	return add_query_arg( 'page', 'roles', admin_url( 'admin.php?page=roles' ) );
}

/**
 * Returns the URL for the edit "mine" roles admin screen.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $view
 * @return string
 */
function members_get_role_view_url( $view ) {

	return add_query_arg( 'view', $view, members_get_edit_roles_url() );
}

/**
 * Returns the URL for the edit role admin screen.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $role
 * @return string
 */
function members_get_edit_role_url( $role ) {

	return add_query_arg( array( 'action' => 'edit', 'role' => $role ), members_get_edit_roles_url() );
}

/**
 * Returns the URL to permanently delete a role (edit roles screen).
 *
 * @since  1.0.0
 * @access public
 * @param  string  $role
 * @return string
 */
function members_get_delete_role_url( $role ) {

	$url = add_query_arg( array( 'action' => 'delete', 'role' => $role ), members_get_edit_roles_url() );

	return wp_nonce_url( $url, 'delete_role', 'members_delete_role_nonce' );
}

/**
 * Returns the URL for the users admin screen specific to a role.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $role
 * @return string
 */
function members_get_role_users_url( $role ) {

	return admin_url( add_query_arg( 'role', $role, 'users.php' ) );
}

/**
 * Returns role slugs that were created via the Members plugin UI (Add New Role).
 * Used by the reset-roles feature to only remove Members-created roles.
 *
 * @since  3.2.18
 * @access public
 * @return array
 */
function members_get_created_roles() {

	$roles = get_option( 'members_created_roles', array() );

	return is_array( $roles ) ? $roles : array();
}

/**
 * Tracks a role as created by the Members plugin UI.
 *
 * @since  3.2.18
 * @access public
 * @param  string $role Role slug.
 * @return void
 */
function members_track_created_role( $role ) {

	if ( ! $role || ! is_string( $role ) ) {
		return;
	}

	$roles = members_get_created_roles();

	if ( ! in_array( $role, $roles, true ) ) {
		$roles[] = $role;
		update_option( 'members_created_roles', $roles );
	}
}

/**
 * Stops tracking a role as created by Members (e.g. after delete or reset).
 *
 * @since  3.2.18
 * @access public
 * @param  string $role Role slug.
 * @return void
 */
function members_untrack_created_role( $role ) {

	if ( ! $role || ! is_string( $role ) ) {
		return;
	}

	$roles = members_get_created_roles();
	$key   = array_search( $role, $roles, true );

	if ( false !== $key ) {
		array_splice( $roles, $key, 1 );
		update_option( 'members_created_roles', $roles );
	}
}
