<?php
/**
 * Role Functions.
 *
 * @package   MembersIntegrationWooCommerce
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-woocommerce-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\WooCommerce;

use function members_get_roles;
use function members_role_exists;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Returns an array of the WooCommerce plugin roles.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function woocommerce_roles() {

	$roles = [];

	$woocommerce_roles = [
		// Core WooCommerce plugin roles.
		'customer',
		'shop_manager'
	];

	// Specifically add the WooCommerce plugin's roles. We need to check that
	// these exist in case a user decides to delete them or in case the role
	// is from an add-on that's not installed.
	foreach ( $woocommerce_roles as $role ) {
		if ( members_role_exists( $role ) ) {
			$roles[] = $role;
		}
	}

	// Add any roles that have any of the WooCommerce capabilities to the group.
	$role_objects = members_get_roles();

	$woocommerce_caps = array_keys( woocommerce_caps() );

	foreach ( $role_objects as $role ) {

		if ( 0 < count( array_intersect( $woocommerce_caps, (array) $role->get( 'granted_caps' ) ) ) ) {
			$roles[] = $role->get( 'name' );
		}
	}

	return $roles;
}
