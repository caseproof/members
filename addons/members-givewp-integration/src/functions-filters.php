<?php
/**
 * Plugin Filters.
 *
 * @package   MembersIntegrationGiveWP
 * @author    The MemberPress Team 
 * @copyright 2019, The MemberPress Team
 * @link      https://members-plugin.com/-givewp-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\GiveWP;

use function members_register_cap;
use function members_register_cap_group;
use function members_register_role_group;
use function members_unregister_cap_group;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Registers custom role groups.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
add_action( 'members_register_role_groups', function() {

	$roles = givewp_roles();

	// Add the plugin-specific role group only if the GiveWP plugin is active
	// and there are existing roles with plugin-specific caps.
	if ( class_exists( 'Give' ) && $roles ) {
		members_register_role_group( 'plugin-givewp', [
			'label'       => esc_html__( 'GiveWP', 'members' ),
			'label_count' => _n_noop( 'GiveWP %s', 'GiveWP %s', 'members' ),
			'roles'       => $roles,
		] );
	}
} );

/**
 * Registers custom cap groups.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
add_action( 'members_register_cap_groups', function() {

	// Only run if we have the `give_forms` post type.
	if ( $type = get_post_type_object( 'give_forms' ) ) {

		$groups = [
			'give_forms',
			'give_payment'
		];

		// Unregister any cap groups already registered for the plugin's
		// custom post types.
		foreach ( $groups as $group ) {
			members_unregister_cap_group( "type-{$group}" );
		}

		// Register a cap group for the GiveWP plugin.
		members_register_cap_group( 'plugin-givewp', [
			'label'    => esc_html__( 'GiveWP', 'members' ),
			'icon'     => $type->menu_icon,
			'priority' => 11
		] );
	}

} );

/**
 * Registers the GiveWP plugin capabilities.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
add_action( 'members_register_caps', function() {

	if ( class_exists( 'Give' ) ) {

		foreach ( givewp_caps() as $name => $options ) {

			members_register_cap( $name, [
				'label'       => $options['label'],
				'description' => $options['description'],
				'group'       => 'plugin-givewp'
			] );
		}
	}

} );
