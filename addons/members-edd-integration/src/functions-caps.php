<?php
/**
 * Capability Functions.
 *
 * @package   MembersIntegrationEDD
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-edd-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\EDD;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Returns an array of the plugin capabilities.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function edd_caps() {

	return [

		// -------------------------------------------------------------
		// Shop caps.
		// -------------------------------------------------------------

		'manage_shop_settings'     => [
			'label'       => __( 'Shop: Manage Settings', 'members' ),
			'description' => __( 'Allows management of the shop settings.', 'members' )
		],

		'view_shop_sensitive_data' => [
			'label'       => __( 'Shop: View Sensitive Data', 'members' ),
			'description' => __( 'Allows access to sensitive user data.', 'members' )
		],

		// -------------------------------------------------------------
		// Reports caps.
		// -------------------------------------------------------------

		'view_shop_reports'        => [
			'label'       => __( 'Reports: View', 'members' ),
			'description' => __( 'Allows users to view shop reports.', 'members' )
		],

		'export_shop_reports'      => [
			'label'       => __( 'Reports: Export', 'members' ),
			'description' => __( 'Allows users to export shop reports data.', 'members' )
		],

		// -------------------------------------------------------------
		// Download/Product caps.
		// -------------------------------------------------------------

		// Custom caps.
		'view_product_stats' => [
			'label'       => __( 'Downloads: View Stats', 'members' ),
			'description' => __( 'Allows users to view download stats.', 'members' )
		],

		'import_products'    => [
			'label'       => __( 'Downloads: Import', 'members' ),
			'description' => __( 'Allows users to import downloads into the database.', 'members' )
		],

		// Download CPT caps.
		'edit_products'             => [
			'label'       => __( 'Downloads: Edit', 'members' ),
			'description' => sprintf(
				__( "Allows users to edit downloads. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>edit_*_products</code>'
			)
		],

		'edit_others_products'      => [
			'label'       => __( "Downloads: Edit Others'",     'members' ),
			'description' => __( "Allows users to edit other user's downloads.", 'members' )
		],

		'edit_private_products'     => [
			'label'       => __( 'Downloads: Edit Private', 'members' ),
			'description' => __( 'Allows users to edit private downloads.', 'members' )
		],

		'edit_published_products'   => [
			'label'       => __( 'Downloads: Edit Published', 'members' ),
			'description' => __( 'Allows users to edit published downloads.', 'members' )
		],

		'publish_products'          => [
			'label'       => __( 'Downloads: Publish', 'members' ),
			'description' => __( 'Allows users to publish downloads.', 'members' )
		],

		'read_private_products'     => [
			'label'       => __( 'Downloads: Read Private', 'members' ),
			'description' => __( 'Allows users to read private downloads.', 'members' )
		],

		'delete_products'           => [
			'label'       => __( 'Downloads: Delete', 'members' ),
			'description' => sprintf(
				__( "Allows users to delete downloads. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>delete_*_products</code>'
			)
		],

		'delete_private_products'   => [
			'label'       => __( 'Downloads: Delete Private', 'members' ),
			'description' => __( 'Allows users to delete private downloads.', 'members' )
		],

		'delete_others_products'    => [
			'label'       => __( "Downloads: Delete Others'",   'members' ),
			'description' => __( "Allows users to delete other users' downloads.", 'members' )
		],

		'delete_published_products' => [
			'label'       => __( 'Downloads: Delete Published', 'members' ),
			'description' => __( 'Allows users to delete published downloads.', 'members' )
		],

		// Product taxonomy caps.
		'assign_product_terms' => [
			'label'       => __( 'Downloads: Assign Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to assign taxonomy terms to downloads.', 'members' )
		],

		'edit_product_terms'   => [
			'label'       => __( 'Downloads: Edit Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to edit download taxonomy terms.', 'members' )
		],

		'delete_product_terms' => [
			'label'       => __( 'Downloads: Delete Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to delete download taxonomy terms.', 'members' )
		],

		'manage_product_terms' => [
			'label'       => __( 'Downloads: Manage Taxonomy Terms', 'members' ),
			'description' => __( 'Allows access to download taxonomy management screens.', 'members' )
		],

		// -------------------------------------------------------------
		// Payment caps.
		// -------------------------------------------------------------

		// Payment custom caps.
		'view_shop_payment_stats' => [
			'label'       => __( 'Payments: View Stats', 'members' ),
			'description' => __( 'Allows users to view payment stats.', 'members' )
		],

		'import_shop_payments'    => [
			'label'       => __( 'Payments: Import', 'members' ),
			'description' => __( 'Allows users to import payments into the database.', 'members' )
		],

		// Payment CPT caps.
		'edit_shop_payments'             => [
			'label'       => __( 'Payments: Edit', 'members' ),
			'description' => sprintf(
				__( "Allows users to edit payments. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>edit_*_shop_payments</code>'
			)
		],

		'edit_others_shop_payments'      => [
			'label'       => __( "Payments: Edit Others'",     'members' ),
			'description' => __( "Allows users to edit other user's payments.", 'members' )
		],

		'edit_private_shop_payments'     => [
			'label'       => __( 'Payments: Edit Private', 'members' ),
			'description' => __( 'Allows users to edit private payments.', 'members' )
		],

		'edit_published_shop_payments'   => [
			'label'       => __( 'Payments: Edit Published', 'members' ),
			'description' => __( 'Allows users to edit published payments.', 'members' )
		],

		'publish_shop_payments'          => [
			'label'       => __( 'Payments: Publish', 'members' ),
			'description' => __( 'Allows users to publish payments.', 'members' )
		],

		'read_private_shop_payments'     => [
			'label'       => __( 'Payments: Read Private', 'members' ),
			'description' => __( 'Allows users to read private payments.', 'members' )
		],

		'delete_shop_payments'           => [
			'label'       => __( 'Payments: Delete', 'members' ),
			'description' => sprintf(
				__( "Allows users to delete payments. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>delete_*_shop_payments</code>'
			)
		],

		'delete_private_shop_payments'   => [
			'label'       => __( 'Payments: Delete Private', 'members' ),
			'description' => __( 'Allows users to delete private payments.', 'members' )
		],

		'delete_others_shop_payments'    => [
			'label'       => __( "Payments: Delete Others'",   'members' ),
			'description' => __( "Allows users to delete other users' payments.", 'members' )
		],

		'delete_published_shop_payments' => [
			'label'       => __( 'Payments: Delete Published', 'members' ),
			'description' => __( 'Allows users to delete published payments.', 'members' )
		],

		// Payment taxonomy caps.
		'assign_shop_payment_terms' => [
			'label'       => __( 'Payments: Assign Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to assign taxonomy terms to payments.', 'members' )
		],

		'edit_shop_payment_terms'   => [
			'label'       => __( 'Payments: Edit Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to edit payment taxonomy terms.', 'members' )
		],

		'delete_shop_payment_terms' => [
			'label'       => __( 'Payments: Delete Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to delete payment taxonomy terms.', 'members' )
		],

		'manage_shop_payment_terms' => [
			'label'       => __( 'Payments: Manage Taxonomy Terms', 'members' ),
			'description' => __( 'Allows access to payment taxonomy management screens.', 'members' )
		],

		// -------------------------------------------------------------
		// Discount caps.
		// -------------------------------------------------------------

		// Discount custom caps.
		'manage_shop_discounts'    => [
			'label'       => __( 'Discounts: Manage', 'members' ),
			'description' => __( 'Allows users to manage shop discounts.', 'members' )
		],

		'view_shop_discount_stats'        => [
			'label'       => __( 'Discounts: View Stats', 'members' ),
			'description' => __( 'Allows users to view discount stats.', 'members' )
		],

		'import_shop_discounts'           => [
			'label'       => __( 'Discounts: Import', 'members' ),
			'description' => __( 'Allows users to import discounts into the database.', 'members' )
		],

		// Discount CPT caps.
		'edit_shop_discounts'             => [
			'label'       => __( 'Discounts: Edit', 'members' ),
			'description' => sprintf(
				__( "Allows users to edit discounts. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>edit_*_shop_discounts</code>'
			)
		],

		'edit_others_shop_discounts'      => [
			'label'       => __( "Discounts: Edit Others'",     'members' ),
			'description' => __( "Allows users to edit other user's discounts.", 'members' )
		],

		'edit_private_shop_discounts'     => [
			'label'       => __( 'Discounts: Edit Private', 'members' ),
			'description' => __( 'Allows users to edit private discounts.', 'members' )
		],

		'edit_published_shop_discounts'   => [
			'label'       => __( 'Discounts: Edit Published', 'members' ),
			'description' => __( 'Allows users to edit published discounts.', 'members' )
		],

		'publish_shop_discounts'          => [
			'label'       => __( 'Discounts: Publish', 'members' ),
			'description' => __( 'Allows users to publish discounts.', 'members' )
		],

		'read_private_shop_discounts'     => [
			'label'       => __( 'Discounts: Read Private', 'members' ),
			'description' => __( 'Allows users to read private discounts.', 'members' )
		],

		'delete_shop_discounts'           => [
			'label'       => __( 'Discounts: Delete', 'members' ),
			'description' => sprintf(
				__( "Allows users to delete discounts. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>delete_*_shop_discounts</code>'
			)
		],

		'delete_private_shop_discounts'   => [
			'label'       => __( 'Discounts: Delete Private', 'members' ),
			'description' => __( 'Allows users to delete private discounts.', 'members' )
		],

		'delete_others_shop_discounts'    => [
			'label'       => __( "Discounts: Delete Others'",   'members' ),
			'description' => __( "Allows users to delete other users' discounts.", 'members' )
		],

		'delete_published_shop_discounts' => [
			'label'       => __( 'Discounts: Delete Published', 'members' ),
			'description' => __( 'Allows users to delete published discounts.', 'members' )
		],

		// Discount taxonomy caps.
		'assign_shop_discount_terms' => [
			'label'       => __( 'Discounts: Assign Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to assign taxonomy terms to discounts.', 'members' )
		],

		'edit_shop_discount_terms'   => [
			'label'       => __( 'Discounts: Edit Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to edit discount taxonomy terms.', 'members' )
		],

		'delete_shop_discount_terms' => [
			'label'       => __( 'Discounts: Delete Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to delete discount taxonomy terms.', 'members' )
		],

		'manage_shop_discount_terms' => [
			'label'       => __( 'Discounts: Manage Taxonomy Terms', 'members' ),
			'description' => __( 'Allows access to discount taxonomy management screens.', 'members' )
		]

	];
}
