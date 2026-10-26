<?php
/**
 * User-related functions and filters.
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

// Filter `user_has_cap` if denied caps should take precedence.
if ( members_explicitly_deny_caps() ) {
	add_filter( 'user_has_cap', 'members_user_has_cap_filter', 10, 4 );
}

/**
 * Filter on `user_has_cap` to explicitly deny caps if there are conflicting caps when a
 * user has multiple roles.  WordPress doesn't consistently handle two or more roles that
 * have the same capability but a conflict between being granted or denied.  Core WP
 * merges the role caps so that the last role the user has will take precedence.  This
 * has the potential for granting permission for things that a user shouldn't have
 * permission to do.
 *
 * @since  1.0.0
 * @access public
 * @param  array  $allcaps
 * @param  array  $caps
 * @param  array  $args
 * @param  object $user
 * @return array
 */
function members_user_has_cap_filter( $allcaps, $caps, $args, $user ) {

	// If the user doesn't have more than one role, bail.
	if ( 1 >= count( (array) $user->roles ) )
		return $allcaps;

	// Get the denied caps.
	$denied_caps = array_keys( (array) $allcaps, false );

	// Loop through the user's roles and find any denied caps.
	foreach ( (array) $user->roles as $role ) {

		// Get the role object.
		$role_obj = get_role( $role );

		// If we have an object, merge it's denied caps.
		if ( ! is_null( $role_obj ) ) {
			$denied_caps = array_merge(
				(array) $denied_caps,
				array_keys( (array) $role_obj->capabilities, false )
			);
		}
	}

	// If there are any denied caps, make sure they take precedence.
	if ( $denied_caps ) {

		foreach ( $denied_caps as $denied_cap ) {
			$allcaps[ $denied_cap ] = false;
		}
	}

	// Return all the user caps.
	return $allcaps;
}

/**
 * Conditional tag to check whether a user has a specific role.
 *
 * @since  1.0.0
 * @access public
 * @param  int           $user_id
 * @param  string|array  $roles
 * @return bool
 */
function members_user_has_role( $user_id, $roles ) {

	$user = new WP_User( $user_id );

	foreach ( (array) $roles as $role ) {

		if ( in_array( $role, (array) $user->roles ) )
			return true;
	}

	return false;
}

/**
 * Conditional tag to check whether the currently logged-in user has a specific role.
 *
 * @since  1.0.0
 * @access public
 * @param  string|array  $roles
 * @return bool
 */
function members_current_user_has_role( $roles ) {

	return is_user_logged_in() ? members_user_has_role( get_current_user_id(), $roles ) : false;
}

/**
 * Wrapper for `current_user_can()` that checks if the user can perform any action.
 * Accepts an array of caps instead of a single cap.
 *
 * @since  2.0.0
 * @access public
 * @param  array   $caps
 * @return bool
 */
function members_current_user_can_any( $caps = array() ) {

	foreach ( $caps as $cap ) {

		if ( current_user_can( $cap ) )
			return true;
	}

	return false;
}

/**
 * Wrapper for `current_user_can()` that checks if the user can perform all actions.
 * Accepts an array of caps instead of a single cap.
 *
 * @since  2.0.0
 * @access public
 * @param  array   $caps
 * @return bool
 */
function members_current_user_can_all( $caps = array() ) {

	foreach ( $caps as $cap ) {

		if ( ! current_user_can( $cap ) )
			return false;
	}

	return true;
}

/**
 * Returns an array of the role names a user has.
 *
 * @since  1.0.0
 * @access public
 * @param  int    $user_id
 * @return array
 */
function members_get_user_role_names( $user_id ) {

	$user = new WP_User( $user_id );

	$names = array();

	foreach ( $user->roles as $role )
		$names[ $role ] = members_get_role( $role )->get( 'label' );

	return $names;
}

/**
 * Sets a user's roles to exactly `$roles`, touching only roles the current user may edit (the
 * same rule as the Edit User screen). Shared by that screen and the AI (MCP) abilities.
 *
 * @since  3.3.0
 * @access public
 * @param  \WP_User  $user   User to change.
 * @param  array     $roles  Role slugs the user should end up with.
 * @return void
 */
function members_set_user_roles( $user, $roles ) {

	$new_roles = members_sanitize_access_role_meta_list( (array) $roles );
	$old_roles = (array) $user->roles;

	foreach ( $new_roles as $new_role ) {

		// If the user doesn't already have the role, add it.
		if ( members_is_role_editable( $new_role ) && ! in_array( $new_role, $old_roles, true ) )
			$user->add_role( $new_role );
	}

	foreach ( $old_roles as $old_role ) {

		// If the role is editable and not in the new roles array, remove it.
		if ( members_is_role_editable( $old_role ) && ! in_array( $old_role, $new_roles, true ) )
			$user->remove_role( $old_role );
	}
}
