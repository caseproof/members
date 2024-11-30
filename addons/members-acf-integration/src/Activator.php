<?php
/**
 * Plugin Activator.
 *
 * Runs the plugin activation routine.
 *
 * @package   MembersIntegrationACF
 * @author    The MemberPress Team 
 * @copyright 2019, The MemberPress Team
 * @link      https://members-plugin.com/-acf-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\ACF;

/**
 * Activator class.
 *
 * @since  1.0.0
 * @access public
 */
class Activator {

	/**
	 * Runs necessary code when first activating the plugin.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public static function activate() {

		// Get the administrator role.
		$role = get_role( 'administrator' );

		// If the administrator role exists, add required capabilities
		// for the plugin.
		if ( ! empty( $role ) ) {

			$role->add_cap( 'manage_acf'                     );
			$role->add_cap( 'edit_acf_field_groups'          );
			$role->add_cap( 'edit_others_acf_field_groups'   );
			$role->add_cap( 'delete_acf_field_groups'        );
			$role->add_cap( 'delete_others_acf_field_groups' );
		}
	}
}
