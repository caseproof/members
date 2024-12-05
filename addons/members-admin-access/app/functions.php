<?php
/**
 * Plugin functions.
 *
 * @package   MembersAdminAccess
 * @author    The MemberPress Team 
 * @copyright Copyright (c) 2018, The MemberPress Team
 * @link      https://members-plugin.com/-admin-access
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Members\AddOns\AdminAccess;

# Filter whether to show the toolbar.
add_filter( 'show_admin_bar', __NAMESPACE__ . '\show_admin_bar', 95 );

# Modify toolbar items.
add_action( 'admin_bar_menu', __NAMESPACE__ . '\admin_bar_menu', 95 );

/**
 * Filter on the `show_admin_bar` hook to disable the admin bar for users without admin access.
 *
 * @since  1.0.0
 * @access public
 * @param  bool   $show
 * @return bool
 */
function show_admin_bar( $show ) {

	return disable_toolbar() && ! current_user_has_access() ? false : $show;
}

/**
 * Removes items from the toolbar if it is showing for a user without access.
 *
 * @since  1.0.0
 * @access public
 * @param  object  $wp_admin_bar
 * @return void
 */
function admin_bar_menu( $wp_admin_bar ) {

	if ( is_admin() || disable_toolbar() || current_user_has_access() )
		return;

	$items = [
		'about',
		'site-name',
		'dashboard',
		'customize',
		'updates',
		'comments',
		'new-content',
		'edit',
		'edit-profile',
		'user-info'
	];

	apply_filters( app()->namespace . '/remove_toolbar_items', $items );

	foreach ( $items as $item )
		$wp_admin_bar->remove_menu( $item );
}

/**
 * Returns an array of the default plugin settings.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function get_default_settings() {

	$settings = [
		'roles'           => array_keys( members_get_roles() ), // Defaults to all roles.
		'redirect_url'    => esc_url_raw( home_url() ),
		'disable_toolbar' => true
	];

	return apply_filters( app()->namespace . '/get_default_settings', $settings );
}

/**
 * Returns the requested plugin setting's value.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $option
 * @return mixed
 */
function get_setting( $option = '' ) {

	$defaults = get_default_settings();

	$settings = wp_parse_args( get_option( 'members_admin_access_settings', $defaults ), $defaults );

	return isset( $settings[ $option ] ) ? $settings[ $option ] : false;
}

/**
 * Returns the redirect to ID.  A value of `0` is the home/front page.
 *
 * @since  1.0.0
 * @access public
 * @return int
 */
function get_redirect_url() {

	return apply_filters( app()->namespace . '/get_redirect_url', get_setting( 'redirect_url' ) );
}

/**
 * Conditional check on whether to disable the toolbar for users without admin access.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function disable_toolbar() {

	return (bool) apply_filters( app()->namespace . '/disable_toolbar', get_setting( 'disable_toolbar' ) );
}

/**
 * Returns an array of roles with admin access.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function get_roles_with_access() {

	$roles = (array) apply_filters( app()->namespace . '/get_roles_with_access', get_setting( 'roles' ) );

	return array_merge( get_roles_with_permanent_access(), $roles );
}

/**
 * Returns an array of roles with permanent admin access, such as administrators.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function get_roles_with_permanent_access() {

	return apply_filters( app()->namespace . '/get_roles_with_permanent_access', [ 'administrator' ] );
}

/**
 * Conditional function for checking if a particular role has access.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $role
 * @return bool
 */
function role_has_access( $role ) {

	return apply_filters(
		app()->namespace . '/role_has_access',
		in_array( $role, get_roles_with_access() ),
		$role
	);
}

/**
 * Conditional function to check if the current user has admin access.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function current_user_has_access() {

	return user_has_access( get_current_user_id() );
}

/**
 * Conditional function to check if a specific user has admin access.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $user_id
 * @return bool
 */
function user_has_access( $user_id = 0 ) {

	return apply_filters(
		app()->namespace . '/user_has_access',
		members_user_has_role( $user_id, get_roles_with_access() ),
		$user_id
	);
}
