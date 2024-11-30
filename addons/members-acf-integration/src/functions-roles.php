<?php
/**
 * Role Functions.
 *
 * @package   MembersIntegrationACF
 * @author    The MemberPress Team 
 * @copyright 2019, The MemberPress Team
 * @link      https://members-plugin.com/-acf-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\ACF;

use function members_get_roles;
use function members_role_exists;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Returns an array of the ACF plugin roles.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function acf_roles() {

	$roles = [];

	// Add any roles that have any of the ACF capabilities to the group.
	$role_objects = members_get_roles();

	$acf_caps = array_keys( acf_caps() );

	foreach ( $role_objects as $role ) {

		if ( 0 < count( array_intersect( $acf_caps, (array) $role->get( 'granted_caps' ) ) ) ) {
			$roles[] = $role->get( 'name' );
		}
	}

	return $roles;
}
