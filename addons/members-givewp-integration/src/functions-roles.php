<?php
/**
 * Role Functions.
 *
 * @package   MembersIntegrationGiveWP
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-givewp-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\GiveWP;

use function members_get_roles;
use function members_role_exists;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Returns an array of the GiveWP plugin roles.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function givewp_roles() {

	$roles = [];

	$givewp_roles = [
		// Core GiveWP plugin roles.
		'give_accountant',
		'give_manager',
		'give_subscriber',
		'give_worker',

		// Recurring Donations (add-on) roles.
		'give_donor'
	];

	// Specifically add the GiveWP plugin's roles. We need to check that
	// these exist in case a user decides to delete them or in case the role
	// is from an add-on that's not installed.
	foreach ( $givewp_roles as $role ) {
		if ( members_role_exists( $role ) ) {
			$roles[] = $role;
		}
	}

	// Add any roles that have any of the GiveWP capabilities to the group.
	$role_objects = members_get_roles();

	$givewp_caps = array_keys( givewp_caps() );

	foreach ( $role_objects as $role ) {

		if ( 0 < count( array_intersect( $givewp_caps, (array) $role->get( 'granted_caps' ) ) ) ) {
			$roles[] = $role->get( 'name' );
		}
	}

	return $roles;
}
