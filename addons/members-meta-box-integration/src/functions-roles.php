<?php
/**
 * Role Functions.
 *
 * @package   MembersIntegrationMetaBox
 * @author    The MemberPress Team 
 * @copyright 2019, The MemberPress Team
 * @link      https://members-plugin.com/-meta-box-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\MetaBox;

use function members_get_roles;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Returns an array of the Meta Box plugin roles.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function meta_box_roles() {

	$roles = [];

	// Add any roles that have any of the Meta Box capabilities to the group.
	$role_objects = members_get_roles();

	$meta_box_caps = array_keys( meta_box_caps() );

	foreach ( $role_objects as $role ) {

		if ( 0 < count( array_intersect( $meta_box_caps, (array) $role->get( 'granted_caps' ) ) ) ) {
			$roles[] = $role->get( 'name' );
		}
	}

	return $roles;
}
