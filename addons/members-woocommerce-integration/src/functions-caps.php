<?php
/**
 * Capability Functions.
 *
 * @package   MembersIntegrationWooCommerce
 * @author    The MemberPress Team 
 * @copyright 2019, The MemberPress Team
 * @link      https://members-plugin.com/-woocommerce-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\WooCommerce;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Returns an array of the WooCommerce plugin capabilities.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function woocommerce_caps() {

	return [
		// Plugin caps.
		'manage_woocommerce'     => [
			'label'       => __( 'Manage WooCommerce', 'members' ),
			'description' => __( 'Allows access to manage the WooCommerce plugin settings.', 'members' )
		],

		// Report caps.
		'view_woocommerce_reports'    => [
			'label'       => __( 'Reports: View', 'members' ),
			'description' => __( 'Allows access to view reports.', 'members' )
		],

		// Product caps.
		'edit_products' => [
			'label'       => __( 'Products: Edit',   'members' ),
			'description' => sprintf(
				__( "Allows users to edit products. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>edit_*_products</code>'
			)
		],

		'edit_others_products'      => [
			'label'       => __( "Products: Edit Others'", 'members' ),
			'description' => __( "Allows users to edit other user's products.", 'members' )
		],

		'edit_private_products'     => [
			'label'       => __( 'Products: Edit Private', 'members' ),
			'description' => __( 'Allows users to edit private products.', 'members' )
		],

		'edit_published_products'   => [
			'label'       => __( 'Products: Edit Published', 'members' ),
			'description' => __( 'Allows users to edit published products.', 'members' )
		],

		'publish_products'          => [
			'label'       => __( 'Products: Publish', 'members' ),
			'description' => __( 'Allows users to publish products.', 'members' )
		],

		'read_private_products'     => [
			'label'       => __( 'Products: Read Private', 'members' ),
			'description' => __( 'Allows users to read private products.', 'members' )
		],

		'delete_products'           => [
			'label'       => __( 'Products: Delete',   'members' ),
			'description' => sprintf(
				__( "Allows users to delete products. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>delete_*_products</code>'
			)
		],

		'delete_private_products'   => [
			'label'       => __( 'Products: Delete Private', 'members' ),
			'description' => __( 'Allows users to delete private products.', 'members' )
		],

		'delete_others_products'    => [
			'label'       => __( "Products: Delete Others'", 'members' ),
			'description' => __( "Allows users to delete other users' products.", 'members' )
		],

		'delete_published_products' => [
			'label'       => __( 'Products: Delete Published', 'members' ),
			'description' => __( 'Allows users to delete published products.', 'members' )
		],

		// Product taxonomy caps.
		'assign_product_terms' => [
			'label'       => __( 'Products: Assign Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to assign taxonomy terms to products.', 'members' )
		],

		'edit_product_terms'   => [
			'label'       => __( 'Products: Edit Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to edit product taxonomy terms.', 'members' )
		],

		'delete_product_terms' => [
			'label'       => __( 'Products: Delete Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to delete product taxonomy terms.', 'members' )
		],

		'manage_product_terms' => [
			'label'       => __( 'Products: Manage Taxonomy Terms', 'members' ),
			'description' => __( 'Allows access to product taxonomy management screens.', 'members' )
		],

		// Order caps.
		'edit_shop_orders' => [
			'label'       => __( 'Orders: Edit',   'members' ),
			'description' => sprintf(
				__( "Allows users to edit orders. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>edit_*_shop_orders</code>'
			)
		],

		'edit_others_shop_orders'      => [
			'label'       => __( "Orders: Edit Others'", 'members' ),
			'description' => __( "Allows users to edit other user's orders.", 'members' )
		],

		'edit_private_shop_orders'     => [
			'label'       => __( 'Orders: Edit Private', 'members' ),
			'description' => __( 'Allows users to edit private orders.', 'members' )
		],

		'edit_published_shop_orders'   => [
			'label'       => __( 'Orders: Edit Published', 'members' ),
			'description' => __( 'Allows users to edit published orders.', 'members' )
		],

		'publish_shop_orders'          => [
			'label'       => __( 'Orders: Publish', 'members' ),
			'description' => __( 'Allows users to publish orders.', 'members' )
		],

		'read_private_shop_orders'     => [
			'label'       => __( 'Orders: Read Private', 'members' ),
			'description' => __( 'Allows users to read private orders.', 'members' )
		],

		'delete_shop_orders'           => [
			'label'       => __( 'Orders: Delete',   'members' ),
			'description' => sprintf(
				__( "Allows users to delete orders. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>delete_*_shop_orders</code>'
			)
		],

		'delete_private_shop_orders'   => [
			'label'       => __( 'Orders: Delete Private', 'members' ),
			'description' => __( 'Allows users to delete private orders.', 'members' )
		],

		'delete_others_shop_orders'    => [
			'label'       => __( "Orders: Delete Others'", 'members' ),
			'description' => __( "Allows users to delete other users' orders.", 'members' )
		],

		'delete_published_shop_orders' => [
			'label'       => __( 'Orders: Delete Published', 'members' ),
			'description' => __( 'Allows users to delete published orders.', 'members' )
		],

		// Order taxonomy caps.
		'assign_shop_order_terms' => [
			'label'       => __( 'Orders: Assign Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to assign taxonomy terms to orders.', 'members' )
		],

		'edit_shop_order_terms'   => [
			'label'       => __( 'Orders: Edit Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to edit order taxonomy terms.', 'members' )
		],

		'delete_shop_order_terms' => [
			'label'       => __( 'Orders: Delete Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to delete order taxonomy terms.', 'members' )
		],

		'manage_shop_order_terms' => [
			'label'       => __( 'Orders: Manage Taxonomy Terms', 'members' ),
			'description' => __( 'Allows access to order taxonomy management screens.', 'members' )
		],

		// Coupon caps.
		'edit_shop_coupons' => [
			'label'       => __( 'Coupons: Edit',   'members' ),
			'description' => sprintf(
				__( "Allows users to edit coupons. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>edit_*_shop_coupons</code>'
			)
		],

		'edit_others_shop_coupons'      => [
			'label'       => __( "Coupons: Edit Others'", 'members' ),
			'description' => __( "Allows users to edit other user's coupons.", 'members' )
		],

		'edit_private_shop_coupons'     => [
			'label'       => __( 'Coupons: Edit Private', 'members' ),
			'description' => __( 'Allows users to edit private coupons.', 'members' )
		],

		'edit_published_shop_coupons'   => [
			'label'       => __( 'Coupons: Edit Published', 'members' ),
			'description' => __( 'Allows users to edit published coupons.', 'members' )
		],

		'publish_shop_coupons'          => [
			'label'       => __( 'Coupons: Publish', 'members' ),
			'description' => __( 'Allows users to publish coupons.', 'members' )
		],

		'read_private_shop_coupons'     => [
			'label'       => __( 'Coupons: Read Private', 'members' ),
			'description' => __( 'Allows users to read private coupons.', 'members' )
		],

		'delete_shop_coupons'           => [
			'label'       => __( 'Coupons: Delete',   'members' ),
			'description' => sprintf(
				__( "Allows users to delete coupons. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>delete_*_shop_coupons</code>'
			)
		],

		'delete_private_shop_coupons'   => [
			'label'       => __( 'Coupons: Delete Private', 'members' ),
			'description' => __( 'Allows users to delete private coupons.', 'members' )
		],

		'delete_others_shop_coupons'    => [
			'label'       => __( "Coupons: Delete Others'", 'members' ),
			'description' => __( "Allows users to delete other users' coupons.", 'members' )
		],

		'delete_published_shop_coupons' => [
			'label'       => __( 'Coupons: Delete Published', 'members' ),
			'description' => __( 'Allows users to delete published coupons.', 'members' )
		],

		// Coupon taxonomy caps.
		'assign_shop_coupon_terms' => [
			'label'       => __( 'Coupons: Assign Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to assign taxonomy terms to coupons.', 'members' )
		],

		'edit_shop_coupon_terms'   => [
			'label'       => __( 'Coupons: Edit Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to edit coupon taxonomy terms.', 'members' )
		],

		'delete_shop_coupon_terms' => [
			'label'       => __( 'Coupons: Delete Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to delete coupon taxonomy terms.', 'members' )
		],

		'manage_shop_coupon_terms' => [
			'label'       => __( 'Coupons: Manage Taxonomy Terms', 'members' ),
			'description' => __( 'Allows access to coupon taxonomy management screens.', 'members' )
		]
	];
}
