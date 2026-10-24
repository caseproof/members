<?php
/**
 * Plugin Activator.
 *
 * Runs the plugin activation routine.
 *
 * @package   MembersIntegrationACF
 * @author    The MemberPress Team 
 * @copyright 2019, The MemberPress Team
 * @link      https://members-plugin.com/
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */
namespace Members\Integration\ACF;

defined('ABSPATH') || exit;
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
		// for the plugin. Leave caps a site owner has already granted or
		// denied alone, since this runs again whenever the add-on is
		// switched back on.
		if ( ! empty( $role ) ) {

			foreach ( [
				'manage_acf',
				'edit_acf_field_groups',
				'edit_others_acf_field_groups',
				'delete_acf_field_groups',
				'delete_others_acf_field_groups',
			] as $cap ) {

				if ( ! isset( $role->capabilities[ $cap ] ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}
}
