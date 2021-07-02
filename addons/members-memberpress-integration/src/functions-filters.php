<?php
/**
 * Plugin Filters.
 *
 * @package   MembersIntegrationMemberPress
 * @author    Krista Butler <krista@caseproof.com>
 * @copyright 2021, Caseproof LLC
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\MemberPress;

use function members_register_cap;
use function members_register_cap_group;
use function members_register_role_group;
use function members_unregister_cap_group;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Registers custom cap groups.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
add_action( 'members_register_cap_groups', function() {

	// Only run if we have the MeprCapabilities class, this means we have MemberPress at a late enough version
	if ( class_exists('MeprCapabilities') ) {

		$groups = [
      'memberpressgroup',
      'memberpressproduct',
      'memberpresscoupon',
      'mp-reminder',
      'memberpressrule'
		];

		// Unregister any cap groups already registered for the plugin's
		// custom post types.
		foreach ( $groups as $group ) {
			members_unregister_cap_group( "type-{$group}" );
		}

		// Register a cap group for the Memberpress plugin.
		members_register_cap_group( 'plugin-memberpress', [
			'label'    => esc_html__( 'MemberPress', 'members' ),
      'icon' =>  'memberpress-icon',
			'priority' => 11,
			'caps'     => array_keys( memberpress_caps() )
		] );
	}

} );

/**
 * Registers the WooCommerce plugin capabilities.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
add_action( 'members_register_caps', function() {
  // Only run if we have the MeprCapabilities class, this means we have MemberPress at a late enough version
	if ( class_exists('MeprCapabilities') ) {

		foreach ( memberpress_caps() as $name => $options ) {

			members_register_cap( $name, [
				'label'       => $options['label'],
				'description' => $options['description']
			] );
		}
	}

add_action( 'admin_enqueue_scripts', function() {
  wp_enqueue_style( 'members-mp-int-css', members_plugin()->uri . "addons/members-memberpress-integration/public/css/memberpress-integration.css" );
}, 0 );
} );
