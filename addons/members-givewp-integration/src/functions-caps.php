<?php
/**
 * Capability Functions.
 *
 * @package   MembersIntegrationGiveWP
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-givewp-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\GiveWP;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Returns an array of the GiveWP plugin capabilities.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function givewp_caps() {

	return [
		// Plugin caps.
		'manage_give_settings'     => [
			'label'       => __( 'GiveWP: Manage Settings', 'members' ),
			'description' => __( 'Allows access to manage the GiveWP plugin settings.', 'members' )
		],

		'view_give_sensitive_data' => [
			'label'       => __( 'GiveWP: View Sensitive Data', 'members' ),
			'description' => __( 'Allows access to view sensitive user data.', 'members' )
		],

		// Report caps.
		'export_give_reports'  => [
			'label'       => __( 'Reports: Export',   'members' ),
			'description' => __( 'Allows access to export reports.', 'members' )
		],

		'view_give_reports'    => [
			'label'       => __( 'Reports: View', 'members' ),
			'description' => __( 'Allows access to view reports.', 'members' )
		],

		// Form caps.
		'edit_give_forms' => [
			'label'       => __( 'Forms: Edit',   'members' ),
			'description' => sprintf(
				__( "Allows users to edit donation forms. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>edit_*_forms</code>'
			)
		],

		'edit_others_give_forms'      => [
			'label'       => __( "Forms: Edit Others'", 'members' ),
			'description' => __( "Allows users to edit other user's donation forms.", 'members' )
		],

		'edit_private_give_forms'     => [
			'label'       => __( 'Forms: Edit Private', 'members' ),
			'description' => __( 'Allows users to edit private donation forms.', 'members' )
		],

		'edit_published_give_forms'   => [
			'label'       => __( 'Forms: Edit Published', 'members' ),
			'description' => __( 'Allows users to edit published donation forms.', 'members' )
		],

		'publish_give_forms'          => [
			'label'       => __( 'Forms: Publish', 'members' ),
			'description' => __( 'Allows users to publish donation forms.', 'members' )
		],

		'read_private_give_forms'     => [
			'label'       => __( 'Forms: Read Private', 'members' ),
			'description' => __( 'Allows users to read private donation forms.', 'members' )
		],

		'delete_give_forms'           => [
			'label'       => __( 'Forms: Delete',   'members' ),
			'description' => sprintf(
				__( "Allows users to delete donation forms. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>delete_*_forms</code>'
			)
		],

		'delete_private_give_forms'   => [
			'label'       => __( 'Forms: Delete Private', 'members' ),
			'description' => __( 'Allows users to delete private donation forms.', 'members' )
		],

		'delete_others_give_forms'    => [
			'label'       => __( "Forms: Delete Others'", 'members' ),
			'description' => __( "Allows users to delete other users' donation forms.", 'members' )
		],

		'delete_published_give_forms' => [
			'label'       => __( 'Forms: Delete Published', 'members' ),
			'description' => __( 'Allows users to delete published donation forms.', 'members' )
		],

		'import_give_forms'           => [
			'label'       => __( 'Forms: Import', 'members' ),
			'description' => __( 'Allows users to import donation forms.', 'members' )
		],

		'view_give_form_stats'        => [
			'label'       => __( 'Forms: View Stats', 'members' ),
			'description' => __( 'Allows users to view donation form stats.', 'members' )
		],

		// Form taxonomy caps.
		'assign_give_form_terms' => [
			'label'       => __( 'Forms: Assign Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to assign taxonomy terms to donation forms.', 'members' )
		],

		'edit_give_form_terms'   => [
			'label'       => __( 'Forms: Edit Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to edit donation form taxonomy terms.', 'members' )
		],

		'delete_give_form_terms' => [
			'label'       => __( 'Forms: Delete Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to delete donation form taxonomy terms.', 'members' )
		],

		'manage_give_form_terms' => [
			'label'       => __( 'Forms: Manage Taxonomy Terms', 'members' ),
			'description' => __( 'Allows access to donation taxonomy management screens.', 'members' )
		],

		// Donation caps.
		'edit_give_payments' => [
			'label'       => __( 'Donations: Edit',   'members' ),
			'description' => sprintf(
				__( "Allows users to edit donations. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>edit_*_payments</code>'
			)
		],

		'edit_others_give_payments'      => [
			'label'       => __( "Donations: Edit Others'", 'members' ),
			'description' => __( "Allows users to edit other user's donations.", 'members' )
		],

		'edit_private_give_payments'     => [
			'label'       => __( 'Donations: Edit Private', 'members' ),
			'description' => __( 'Allows users to edit private donations.', 'members' )
		],

		'edit_published_give_payments'   => [
			'label'       => __( 'Donations: Edit Published', 'members' ),
			'description' => __( 'Allows users to edit published donations.', 'members' )
		],

		'publish_give_payments'          => [
			'label'       => __( 'Donations: Publish', 'members' ),
			'description' => __( 'Allows users to publish donations.', 'members' )
		],

		'read_private_give_payments'     => [
			'label'       => __( 'Donations: Read Private', 'members' ),
			'description' => __( 'Allows users to read private donations.', 'members' )
		],

		'delete_give_payments'           => [
			'label'       => __( 'Donations: Delete',   'members' ),
			'description' => sprintf(
				__( "Allows users to delete donations. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>delete_*_payments</code>'
			)
		],

		'delete_private_give_payments'   => [
			'label'       => __( 'Donations: Delete Private', 'members' ),
			'description' => __( 'Allows users to delete private donations.', 'members' )
		],

		'delete_others_give_payments'    => [
			'label'       => __( "Donations: Delete Others'", 'members' ),
			'description' => __( "Allows users to delete other users' donations.", 'members' )
		],

		'delete_published_give_payments' => [
			'label'       => __( 'Donations: Delete Published', 'members' ),
			'description' => __( 'Allows users to delete published donations.', 'members' )
		],

		'import_give_payments'           => [
			'label'       => __( 'Donations: Import', 'members' ),
			'description' => __( 'Allows users to import donations.', 'members' )
		],

		'view_give_payments' => [
			'label' => __( 'Donations: View', 'members' ),
			'description' => __( 'Allows users to view donations.', 'members' )
		],

		'view_give_payment_stats'        => [
			'label'       => __( 'Donations: View Stats', 'members' ),
			'description' => __( 'Allows users to view donation stats.', 'members' )
		],

		// Donation taxonomy caps.
		'assign_give_payment_terms' => [
			'label'       => __( 'Donations: Assign Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to assign taxonomy terms to donations.', 'members' )
		],

		'edit_give_payment_terms'   => [
			'label'       => __( 'Donations: Edit Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to edit donation taxonomy terms.', 'members' )
		],

		'delete_give_payment_terms' => [
			'label'       => __( 'Donations: Delete Taxonomy Terms', 'members' ),
			'description' => __( 'Allows users to delete donation taxonomy terms.', 'members' )
		],

		'manage_give_payment_terms' => [
			'label'       => __( 'Donations: Manage Taxonomy Terms', 'members' ),
			'description' => __( 'Allows access to taxonomy management screens.', 'members' )
		]
	];
}
