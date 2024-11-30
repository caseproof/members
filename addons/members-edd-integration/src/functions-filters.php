<?php
/**
 * Plugin Filters.
 *
 * @package   MembersIntegrationEDD
 * @author    The MemberPress Team 
 * @copyright 2019, The MemberPress Team
 * @link      https://members-plugin.com/-edd-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\EDD;

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

	$roles = edd_roles();

	// Add the plugin-specific role group only if the plugin is active
	// and there are existing roles with plugin-specific caps.
	if ( class_exists( 'Easy_Digital_Downloads' ) && $roles ) {
		members_register_role_group( 'plugin-edd', [
			'label'       => esc_html__( 'Shop', 'members' ),
			'label_count' => _n_noop( 'Shop %s', 'Shop %s', 'members' ),
			'roles'       => $roles
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

	// Only run if we have the `download` post type.
	if ( $type = get_post_type_object( 'download' ) ) {

		$groups = [
			'download',
			'edd_payment',
			'edd_discount'
		];

		// Unregister any cap groups already registered for the plugin's
		// custom post types.
		foreach ( $groups as $group ) {
			members_unregister_cap_group( "type-{$group}" );
		}

		// Register a cap group for the EDD plugin.
		members_register_cap_group( 'plugin-edd',[
			'label'    => esc_html__( 'Downloads', 'members' ),
			'icon'     => $type->menu_icon,
			'priority' => 11
		] );
	}

} );

/**
 * Registers the EDD plugin capabilities.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
add_action( 'members_register_caps', function() {

	if ( class_exists( 'Easy_Digital_Downloads' ) ) {

		foreach ( edd_caps() as $name => $args ) {

			members_register_cap( $name, [
				'label'       => $args['label'],
				'description' => $args['description'],
				'group'       => 'plugin-edd'
			] );
		}
	}

} );
