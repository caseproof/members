<?php
/**
 * Role Functions.
 *
 * @package   MembersIntegrationEDD
 * @author    The MemberPress Team 
 * @copyright 2019, The MemberPress Team
 * @link      https://members-plugin.com/-edd-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\EDD;

use function members_get_roles;
use function members_role_exists;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Returns an array of the plugin roles.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function edd_roles() {

	$roles = [];

	$edd_roles = [
		'shop_accountant',
		'shop_manager',
		'shop_vendor',
		'shop_worker'
	];

	// Specifically add the  plugin's roles. We need to check that these
	// exist in case a user decides to delete them or in case the role
	// is from an add-on that's not installed.
	foreach ( $edd_roles as $role ) {
		if ( members_role_exists( $role ) ) {
			$roles[] = $role;
		}
	}

	// Add any roles that have any of the plugin capabilities to the group.
	$role_objects = members_get_roles();

	$edd_caps = array_keys( edd_caps() );

	foreach ( $role_objects as $role ) {

		if ( 0 < count( array_intersect( $edd_caps, (array) $role->get( 'granted_caps' ) ) ) ) {
			$roles[] = $role->get( 'name' );
		}
	}

	return $roles;
}
