<?php
/**
 * Capability Functions.
 *
 * @package   MembersIntegrationMetaBox
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-meta-box-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\MetaBox;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Returns an array of the Meta Box plugin capabilities.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function meta_box_caps() {

	return [
		'create_metabox_field_groups' => [
			'label'       => __( 'Create Field Groups',   'members' ),
			'description' => __( 'Allows users to create new field groups.', 'members' )
		],

		'edit_metabox_field_groups' => [
			'label'       => __( 'Edit Field Groups',   'members' ),
			'description' => sprintf(
				// Translators: %s is a capability name.
				__( "Allows users to edit field groups. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>edit_*_metabox_field_groups</code>'
			)
		],

		'delete_metabox_field_groups' => [
			'label'       => __( 'Delete Field Groups',   'members' ),
			'description' => sprintf(
				// Translators: %s is a capability name.
				__( "Allows users to delete field groups. May need to be combined with other %s capabilities, depending on the scenario.", 'members' ),
				'<code>delete_*_metabox_field_groups</code>'
			)
		]
	];
}
