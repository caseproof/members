<?php
/**
 * Functions related to role positions.  Role positions are how we determine a role's
 * "position" in the hierarchy.  The higher the position, the higher the role is in
 * the hierarchy.
 *
 * @package   MembersRoleHierarchy
 * @author    Justin Tadlock <justin@justintadlock.com>
 * @copyright Copyright (c) 2017, Justin Tadlock
 * @link      http://themehybrid.com/plugins/members-role-hierarchy
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/**
 * Returns an array of role positions.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function mrh_get_role_positions() {

	$defaults = mrh_get_default_role_positions();

	$positions = get_option( 'members_role_hierarchy', $defaults );

	return apply_filters( 'mrh_get_role_positions', wp_parse_args( $positions, $defaults ) );
}

/**
 * Returns the default role positions.  We're just setting defaults the core roles.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function mrh_get_default_role_positions() {

	$defaults = array(
		'administrator' => 100,
		'editor'        => 80,
		'author'        => 60,
		'contributor'   => 40,
		'subscriber'    => 20
	);

	return apply_filters( 'mrh_get_default_role_positions', $defaults );
}

/**
 * Returns a role's position in the hierarchy.
 *
 * @since  1.0.0
 * @access public
 * @param  string   $role
 * @return int
 */
function mrh_get_role_position( $role ) {

	$positions = mrh_get_role_positions();

	$position = isset( $positions[ $role ] ) ? $positions[ $role ] : mrh_get_default_role_position();

	return apply_filters( 'mrh_get_role_position', $position, $role );
}

/**
 * Returns the fallback role position when none is set for a particular role.
 *
 * @since  1.0.0
 * @access public
 * @return int
 */
function mrh_get_default_role_position() {

	return apply_filters( 'mrh_get_default_role_position', 0 );
}

/**
 * Sets a role position.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $role
 * @param  int     $position
 * @return bool
 */
function mrh_set_role_position( $role, $position = 0 ) {

	$positions = mrh_get_role_positions();

	$positions[ $role ] = $position;

	return update_option( 'members_role_hierarchy', $positions );
}

/**
 * Function for comparing roles by their position.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $role_a
 * @param  int     $role_b
 * @param  string  $operator
 * @return bool
 */
function mrh_compare_role( $role_a, $role_b, $operator = '==' ) {

	$pos_a = mrh_get_role_position( $role_a );
	$pos_b = mrh_get_role_position( $role_b );

	return mrh_compare( $pos_a, $pos_b, $operator );
}

/**
 * Helper function for comparing numbers with a dynamic operator.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $a
 * @param  int     $b
 * @param  string  $operator
 * @return bool
 */
function mrh_compare( $a, $b, $operator = '==' ) {

	switch ( $operator ) {

		case '=='  : return $a ==  $b;
		case '===' : return $a === $b;
		case '!='  : return $a !== $b;
		case '<>'  : return $a <>  $b;
		case '!==' : return $a !== $b;
		case '>='  : return $a >=  $b;
		case '<='  : return $a <=  $b;
		case '>'   : return $a >   $b;
		case '<'   : return $a <   $b;
	//	case '<=>' : return $a <=> $b; // PHP7
		default    : return false;
	}
}

/**
 * Conditional function to check if a role is higher than another.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $role_a
 * @param  string  $role_b
 * @return bool
 */
function mrh_is_role_higher( $role_a, $role_b ) {

	$role_a_pos = mrh_get_role_position( $role_a );
	$role_b_pos = mrh_get_role_position( $role_b );

	return $role_a_pos > $role_b_pos;
}

/**
 * Returns an array with the highest roles by position.  It's very possible for
 * the two or more roles to have the same position.  That's why we're returning
 * an array of roles rather than a single "highest" role.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function mrh_get_highest_roles() {

	$highest = array();

	$roles = mrh_get_role_positions();

	// Sort numbers in descending order.
	arsort( $roles, SORT_NUMERIC );

	// Reset the array pointer.
	reset( $roles );

	// Get the first key, which is the highest role.
	$key = key( $roles );

	// Loop through the sorted roles and add any that match the highest role's position.
	foreach ( $roles as $role => $position ) {

		// Bail if we hit a role that doesn't match.  No others will after either.
		if ( $roles[ $key ] != $position )
			break;

		$highest[] = $role;
	}

	return $highest;
}

/**
 * Gets a user's highest role based on its position in the hierarchy.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $user_id
 * @return string
 */
function mrh_get_user_highest_role( $user_id ) {

	$positions = mrh_get_role_positions();

	$user = new WP_User( $user_id );

	$highest_role = '';

	if ( $user->roles ) {

		foreach ( $user->roles as $role ) {

			if ( ! $highest_role ) {

				$highest_role = $role;

			} else if ( $highest_role ) {

				// Get the position of the highest role.
				$h_role_pos = mrh_get_role_position( $highest_role );

				// Get the position of the current role.
				$c_role_pos = mrh_get_role_position( $role );

				// If the current role is higher than the highest role, it becomes
				// the new highest role.
				if ( $c_role_pos > $h_role_pos )
					$highest_role = $role;
			}
		}
	}

	return $highest_role;
}
