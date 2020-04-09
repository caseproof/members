<?php
/**
 * Plugin Filters.
 *
 * @package   MembersIntegrationWooCommerce
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-woocommerce-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\WooCommerce;

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

	$roles = woocommerce_roles();

	// Add the plugin-specific role group only if the WooCommerce plugin is active
	// and there are existing roles with plugin-specific caps.
	if ( class_exists( 'WooCommerce' ) && $roles ) {
		members_register_role_group( 'plugin-woocommerce', [
			'label'       => esc_html__( 'WooCommerce', 'members' ),
			'label_count' => _n_noop( 'WooCommerce %s', 'WooCommerce %s', 'members' ),
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

	// Only run if we have the `product` post type.
	if ( $type = get_post_type_object( 'product' ) ) {

		$groups = [
			'product',
			'product_variation',
			'shop_order_refund',
			'shop_coupon',
			'shop_order'
		];

		// Unregister any cap groups already registered for the plugin's
		// custom post types.
		foreach ( $groups as $group ) {
			members_unregister_cap_group( "type-{$group}" );
		}

		// Register a cap group for the WooCommerce plugin.
		members_register_cap_group( 'plugin-woocommerce', [
			'label'    => esc_html__( 'WooCommerce', 'members' ),
			'icon'     => 'dashicons-cart',
			'priority' => 11,
			'caps'     => array_keys( woocommerce_caps() )
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

	if ( class_exists( 'WooCommerce' ) ) {

		foreach ( woocommerce_caps() as $name => $options ) {

			members_register_cap( $name, [
				'label'       => $options['label'],
				'description' => $options['description']
			] );
		}
	}

} );
