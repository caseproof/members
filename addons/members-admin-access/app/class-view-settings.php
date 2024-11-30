<?php
/**
 * Outputs a custom settings view under "Admin Access" on the Members plugin
 * settings page.
 *
 * @package   MembersAdminAccess
 * @author    The MemberPress Team 
 * @copyright Copyright (c) 2018, The MemberPress Team
 * @link      https://members-plugin.com/-admin-access
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Members\AddOns\AdminAccess;

use Members\Admin\View;

/**
 * Sets up and handles the general settings view.
 *
 * @since  1.0.0
 * @access public
 */
class View_Settings extends View {

	/**
	 * Registers the plugin settings.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	function register_settings() {

		// Register the setting.
		register_setting( 'members_admin_access_settings', 'members_admin_access_settings', array( $this, 'validate_settings' ) );

		/* === Settings Sections === */

		add_settings_section( 'general', esc_html__( 'Admin Access', 'members' ), array( $this, 'section_general' ), app()->namespace . '/settings' );

		/* === Settings Fields === */

		add_settings_field( 'select_roles', esc_html__( 'Select Roles', 'members' ), array( $this, 'field_select_roles' ), app()->namespace . '/settings', 'general' );
		add_settings_field( 'redirect',     esc_html__( 'Redirect',     'members' ), array( $this, 'field_redirect'     ), app()->namespace . '/settings', 'general' );
		add_settings_field( 'toolbar',      esc_html__( 'Toolbar',      'members' ), array( $this, 'field_toolbar'      ), app()->namespace . '/settings', 'general' );
	}

	/**
	 * Validates the plugin settings.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array  $input
	 * @return array
	 */
	function validate_settings( $settings ) {

		// Validate selected roles.
		//
		// Note that it's possible for `$settings['roles']` to not be set
		// when no roles at all are selected.

		if ( empty( $settings['roles'] ) ) {
			$settings['roles'] = array();
		}

		foreach ( $settings['roles'] as $key => $role ) {

			if ( ! members_role_exists( $role ) )
				unset( $settings['roles'][ $key ] );
		}

		// Escape URLs.
		$settings['redirect_url'] = esc_url_raw( $settings['redirect_url'] );

		if ( ! $settings['redirect_url'] )
			$settings['redirect_url'] = esc_url_raw( home_url() );

		// Handle checkboxes.
		$settings['disable_toolbar'] = ! empty( $settings['disable_toolbar'] ) ? true : false;

		return apply_filters( app()->namespace . '/validate_settings', $settings );
	}

	/**
	 * Role/Caps section callback.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function section_general() { ?>

		<p class="description">
			<?php esc_html_e( 'Control admin access by user role.', 'members' ); ?>
		</p>
	<?php }

	/**
	 * Outputs the field for selecting roles.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function field_select_roles() { ?>

		<p class="description">
			<?php esc_html_e( 'Select which roles should have admin access.', 'members' ); ?>
		</p>

		<div class="wp-tab-panel">

		<ul>
			<?php foreach ( members_get_roles() as $role ) :

				$disabled = in_array( $role->name, get_roles_with_permanent_access() ); ?>

				<li>
					<label>
						<?php if ( ! $disabled ) : ?>

							<input type="checkbox" name="members_admin_access_settings[roles][]" value="<?php echo esc_attr( $role->name ); ?>" <?php checked( role_has_access( $role->name ) ); ?> />

						<?php else : ?>

							<input readonly="readonly" disabled="disabled" type="checkbox" name="members_admin_access_settings[roles][]" value="<?php echo esc_attr( $role->name ); ?>" <?php checked( role_has_access( $role->name ) ); ?> />

						<?php endif; ?>

						<?php echo esc_html( $role->label ); ?>
					</label>
				</li>

			<?php endforeach; ?>
		</ul>

		</div><!-- .wp-tab-panel -->
	<?php }

	/**
	 * Outputs the redirect URL field.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function field_redirect() { ?>

		<p>
			<label>
				<?php esc_html_e( 'Redirect users without access to:', 'members' ); ?>

				<input type="text" name="members_admin_access_settings[redirect_url]" value="<?php echo esc_attr( get_redirect_url() ); ?>" />
			</label>
		</p>
	<?php }

	/**
	 * The toolbar field callback.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function field_toolbar() { ?>

		<p>
			<label>
				<input type="checkbox" name="members_admin_access_settings[disable_toolbar]" value="1" <?php checked( disable_toolbar() ); ?> />

				<?php esc_html_e( 'Disable toolbar on the front end for users without admin access.', 'members' ); ?>
			</label>
		</p>
	<?php }

	/**
	 * Renders the settings page.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function template() { ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'members_admin_access_settings' ); ?>
			<?php do_settings_sections( app()->namespace . '/settings' ); ?>
			<?php submit_button( esc_attr__( 'Update Settings', 'members' ), 'primary' ); ?>
		</form>

	<?php }
}