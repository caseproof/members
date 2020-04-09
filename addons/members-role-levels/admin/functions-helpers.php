<?php
/**
 * Helper functions for dealing with role levels.
 *
 * @package    MembersRoleLevels
 * @subpackage Admin
 * @author     Justin Tadlock <justin@justintadlock.com>
 * @copyright  Copyright (c) 2015, Justin Tadlock
 * @link       http://themehybrid.com/plugins/members-role-levels
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/**
 * Removes the `level_` prefix from a role level to return the numeric version.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $level
 * @return string
 */
function mrl_remove_level_prefix( $level ) {
	return str_replace( 'level_', '', $level );
}

/**
 * Adds the `level_` prefix from a role level to return the non-numeric version.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $level
 * @return string
 */
function mrl_add_level_prefix( $level ) {
	return "level_{$level}";
}

/**
 * Checks the role level against a white-list of allowed role levels.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $level
 * @return bool
 */
function mrl_is_valid_level( $level ) {

	return in_array( $level, array_keys( mrl_get_role_levels() ) );
}

/**
 * Returns an array of role levels.  The array keys are the levels.  The array values are the
 * internationalized level labels.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function mrl_get_role_levels() {

	return array(
		'level_0'  => __( 'Level 0',  'members' ),
		'level_1'  => __( 'Level 1',  'members' ),
		'level_2'  => __( 'Level 2',  'members' ),
		'level_3'  => __( 'Level 3',  'members' ),
		'level_4'  => __( 'Level 4',  'members' ),
		'level_5'  => __( 'Level 5',  'members' ),
		'level_6'  => __( 'Level 6',  'members' ),
		'level_7'  => __( 'Level 7',  'members' ),
		'level_8'  => __( 'Level 8',  'members' ),
		'level_9'  => __( 'Level 9',  'members' ),
		'level_10' => __( 'Level 10', 'members' )
	);
}

/**
 * Returns the highest level a role has. Technically, roles have multiple levels.  For example,
 * if a role has `level_7`, it will also have `level_0` - `level_6`.  This function will simply
 * return the highest.
 *
 * @since  1.0.0
 * @access public
 * @param  string|object $role
 * @return string
 */
function mrl_get_role_level( $role ) {

	// Bail if the role is empty.
	if ( ! $role )
		return '';

	// Make sure we have the role object.
	if ( ! is_object( $role ) )
		$role = get_role( $role );

	// Get all the role's levels.
	$levels = array_intersect( array_keys( $role->capabilities ), array_keys( mrl_get_role_levels() ) );

	// Return an empty string if the role doesn't have any levels.
	if ( ! $levels )
		return '';

	// Get the numeric versions of the levels.
	$numeric_levels = array_map( 'mrl_remove_level_prefix', $levels );

	// Sort the levels in descending order (high to low).
	rsort( $numeric_levels );

	// Return the highest level and re-add the `level_` prefix.
	return mrl_add_level_prefix( array_shift( $numeric_levels ) );
}

/**
 * Sets a new role level. This function also updates all users of the given role to update
 * their user level.
 *
 * Note: WP will always set the user level to the highest level when calling the
 * `WP_User:update_user_level_from_caps()` method, so there's no need to check for the
 * highest role when dealing with users with multiple roles.
 *
 * @since  1.0.0
 * @access public
 * @param  string|object $role
 * @return void
 */
function mrl_set_role_level( $role, $new_level = 'level_0' ) {

	// Make sure we have the role object.
	if ( ! is_object( $role ) )
		$role = get_role( $role );

	// Get the allowed levels.
	$levels = array_keys( mrl_get_role_levels() );

	// Get the posted level without the `level` prefix.
	$new_level_numeric = absint( mrl_remove_level_prefix( $new_level ) );

	// Get the levels to add and remove.
	$add    = array_slice( $levels, 0, $new_level_numeric + 1, true );
	$remove = array_diff( $levels, $add );

	// Add new levels.
	foreach ( $add as $add_level )
		$role->add_cap( $add_level );

	// Remove levels.
	foreach ( $remove as $remove_level )
		$role->remove_cap( $remove_level );

	// Get the users with the current role.
	$users = get_users( array( 'role' => $role->name ) );

	// If there are users with the role, update their user level from caps.
	if ( $users ) {

		foreach ( $users as $user )
			$user->update_user_level_from_caps();
	}
}
