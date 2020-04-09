<?php
/**
 * Functions and filters related to plugin settings in the admin.
 *
 * @package   MembersRoleHierarchy
 * @author    Justin Tadlock <justin@justintadlock.com>
 * @copyright Copyright (c) 2017, Justin Tadlock
 * @link      http://themehybrid.com/plugins/members-role-hierarchy
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

# Register settings on `admin_init`.
add_action( 'admin_init', 'mrh_register_settings', 15 );

/**
 * Registers plugin settings and adds custom settings fields to the Members Settings
 * screen in the admin.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function mrh_register_settings() {

	// Register our plugin setting to the `members_settings` group (defined in Members plugin).
	register_setting( 'members_settings', 'mrh_plugin_settings', 'mrh_validate_settings' );

	// Adds the Role Hierarchy setting field.
	add_settings_field(
		'mrh_role_hierarchy',
		esc_html__( 'Role Hierarchy', 'members' ),
		'mrh_settings_field_hierarchy',
		'admin_page_members-settings',
		'roles_caps'
	);
}

/**
 * Callback function for validating plugin settings.
 *
 * @since  1.0.0
 * @access public
 * @param  array  $settings
 * @return array
 */
function mrh_validate_settings( $settings ) {

	$allowed = array( '>', '>=' );

	$settings['comparison_operator'] = isset( $settings['comparison_operater'] ) && in_array( $settings['comparison_operater'], $allowed ) ? $settings['comparison_operater'] : '>';

	return $settings;
}

/**
 * Outputs the hierarchy settings field.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function mrh_settings_field_hierarchy() {

	$exceptions = array();

	foreach ( mrh_get_highest_roles() as $role )
		$exceptions[] = "<code>{$role}</code>"; ?>

	<p class="description">
		<?php esc_html_e( 'Should users manage users/roles that are "lower" or "lower or equal" to their own role?', 'members' ); ?>
	</p>

	<ul>
		<li>
			<label>
				<input type="radio" name="members_settings[comparison_operator]" value=">" <?php checked( '>', mrh_get_comparison_operator() ); ?> />
				<?php printf(
					__( 'Lower. <em>Exceptions are the following roles:</em> %s.', 'members' ),
					join( ', ', $exceptions )
				); ?>
			</label>
		</li>
		<li>
			<label>
				<input type="radio" name="members_settings[comparison_operator]" value=">=" <?php checked( '>=', mrh_get_comparison_operator() ); ?> />
				<?php esc_html_e( 'Lower or equal.', 'members' ); ?>
			</label>
		</li>
	</ul>
<?php }
