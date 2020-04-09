<?php
/**
 * Custom filters to make the plugin work.
 *
 * @package   MembersRoleHierarchy
 * @author    Justin Tadlock <justin@justintadlock.com>
 * @copyright Copyright (c) 2017, Justin Tadlock
 * @link      http://themehybrid.com/plugins/members-role-hierarchy
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

# Filter the editable roles.
add_filter( 'editable_roles', 'mrh_editable_roles', 95 );

# Map meta capabilities.
add_filter( 'map_meta_cap', 'mrh_map_meta_cap', 95, 4 );

/**
 * Filters the array of editable roles to remove any roles that are lower than the
 * current user's highest role by role position.
 *
 * @since  1.0.0
 * @access public
 * @param  array  $editable
 * @return array
 */
function mrh_editable_roles( $editable ) {

	// Gets the roles.
	$roles = array_keys( $editable );

	// Gets the user's highest role.
	$user_role = mrh_get_user_highest_role( get_current_user_id() );

	// Administrators are the exception to the operator.  Admins can always edit any role.
	$operator = in_array( $user_role, mrh_get_highest_roles() ) ? '>=' : mrh_get_comparison_operator();

	// Loop through the roles and removes any that can't be edited for the current user.
	foreach ( $roles as $edit_role ) {

		if ( ! mrh_compare_role( $user_role, $edit_role, $operator ) )
			unset( $editable[ $edit_role ] );
	}

	return $editable;
}

/**
 * Filter on `map_meta_cap` that listens for user-related meta capabilities.  If one is called,
 * we make sure that the user's highest role is greater than the highest role of the user
 * being promoted, edited, removed, deleted, etc.
 *
 * @since  1.0.0
 * @access public
 * @param  array   $caps
 * @param  string  $cap
 * @param  int     $user_id
 * @param  array   $args
 * @return array
 */
function mrh_map_meta_cap( $caps, $cap, $user_id, $args ) {

	// If not a user-related cap, bail.
	if ( ! in_array( $cap, array( 'promote_user', 'edit_user', 'remove_user', 'delete_user' ) ) )
		return $caps;

	// If we have a user (it's the first argument of the array).
	// Also, only proceed if the user is not attempting to edit themselves.
	if ( isset( $args[ 0 ] ) && $user_id != $args[ 0 ] ) {

		$user_role = mrh_get_user_highest_role( $user_id   );
		$edit_role = mrh_get_user_highest_role( $args[ 0 ] );

		// Administrators are the exception to the operator.  Admins can always edit other admins.
		$operator = in_array( $user_role, mrh_get_highest_roles() ) ? '>=' : mrh_get_comparison_operator();

		// If the current user cannot edit, don't allow.
		if ( ! mrh_compare_role( $user_role, $edit_role, $operator ) )
			$caps[] = 'do_not_allow';
	}

	return $caps;
}
