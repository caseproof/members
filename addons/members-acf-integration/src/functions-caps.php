<?php
/**
 * Capability Functions.
 *
 * @package   MembersIntegrationACF
 * @author    The MemberPress Team 
 * @copyright 2019, The MemberPress Team
 * @link      https://members-plugin.com/
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\ACF;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Returns an array of the ACF plugin capabilities.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function acf_caps() {

	return [
		'manage_acf'   => [
			'label'       => __( 'Manage Advanced Custom Fields', 'members' ),
			'description' => __( 'Allows access to settings and tools for the Advanced Custom Fields plugin and may be required to access some third-party add-ons.', 'members' )
		],

		'edit_acf_field_groups' => [
			'label'       => __( 'Edit Field Groups',   'members' ),
			'description' => sprintf(
				// Translators: %s is a capability name.
				__( "Allows users to edit field groups. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>edit_*_acf_field_groups</code>'
			)
		],

		'edit_others_acf_field_groups'   => [
			'label'       => __( "Edit Others' Field Groups", 'members' ),
			'description' => __( "Allows users to edit others user's field groups.", 'members' )
		],

		'delete_acf_field_groups'           => [
			'label'       => __( 'Delete Field Groups',   'members' ),
			'description' => sprintf(
				// Translators: %s is a capability name.
				__( "Allows users to delete field groups. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>delete_*_acf_field_groups</code>'
			)
		],

		'delete_others_acf_field_groups' => [
			'label'       => __( "Delete Others' Field Groups", 'members' ),
			'description' => __( "Allows users to delete other user's field groups.", 'members' )
		]
	];
}
