<?php
/**
 * Adds the "Level" meta box to the edit/new role screen.
 *
 * @package    MembersRoleLevels
 * @subpackage Admin
 * @author     The MemberPress Team 
 * @copyright  Copyright (c) 2015, The MemberPress Team
 * @link       https://members-plugin.com/
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/**
 * Role level meta box.
 *
 * @since  1.0.0
 * @access public
 */
final class Members_Role_Levels_Meta_Box_Level {

	/**
	 * Constructor method.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Sets up actions and filters.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	private function setup() {

		// Add actions on page load.
		add_action( 'members_load_role_edit', array( $this, 'load' ) );
		add_action( 'members_load_role_new',  array( $this, 'load' ) );

		// Update role levels.
		add_action( 'members_role_updated', array( $this, 'update_role_level' ) );
		add_action( 'members_role_added',   array( $this, 'update_role_level' ) );
	}

	/**
	 * Add actions/filters on page load.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function load() {

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
	}

	/**
	 * Adds custom meta boxes.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $screen_id
	 * @return void
	 */
	public function add_meta_boxes( $screen_id ) {

		// Add the meta box.
		add_meta_box( 'mrl_role_level', esc_html__( 'Level', 'members' ), array( $this, 'meta_box' ), $screen_id, 'side', 'default' );
	}

	/**
	 * Outputs the role level meta box.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  object  $role
	 * @return void
	 */
	public function meta_box( $role ) {

		// If the role isn't editable, the field should be read-only.
		$is_editable = $role ? members_is_role_editable( $role->name ) : true;
		$readonly    = $is_editable ? '' : ' disabled="disabled" readonly="readonly"';

		// Get the role level.
		$role_level = mrl_get_role_level( $role );

		// If there is no role level, check if cloning or error.
		if ( ! $role_level ) {

			// If there was a posted level (error).
			if ( isset( $_POST['mrl-role-level'] ) && mrl_is_valid_level( $_POST['mrl-role-level'] ) )
				$role_level = $_POST['mrl-role-level'];

			// If we're cloning a new role.
			else if ( isset( $_GET['page'] ) && 'role-new' === $_GET['page'] && ! empty( $_GET['clone'] ) )
				$role_level = mrl_get_role_level( members_sanitize_role( $_GET['clone'] ) );
		}

		// If still no level, set it to `level_0`.
		$role_level = $role_level ? $role_level : 'level_0';

		wp_nonce_field( 'role_level', 'mrl_role_level_nonce' ); ?>

		<p>
			<select class="widefat" name="mrl-role-level"<?php echo $readonly; ?>>

				<?php foreach ( mrl_get_role_levels() as $level => $label ) : ?>

					<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $level, $role_level ); ?>><?php echo esc_html( $label ); ?></option>

				<?php endforeach; ?>

			</select>
		</p>
	<?php }

	/**
	 * Updates the role level when a new role is added or an existing role is updated.  Note
	 * that in order to properly update the `user_level` field of users, we need to run
	 * `WP_User::update_user_level_from_caps()`, which can be a heavy function if the role
	 * as a lot of users because each user of the role needs to be updated.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $role
	 * @return void
	 */
	public function update_role_level( $role ) {

		// Verify the nonce before proceeding.
		if ( isset( $_POST['mrl_role_level_nonce'] ) && wp_verify_nonce( $_POST['mrl_role_level_nonce'], 'role_level' ) ) {

			// Get the current role object to edit.
			$role = get_role( members_sanitize_role( $role ) );

			// If the role doesn't exist, bail.
			if ( is_null( $role ) )
				return;

			// Get the posted level.
			$new_level = isset( $_POST['mrl-role-level'] ) ? $_POST['mrl-role-level'] : '';

			// Make sure the posted level is in the whitelisted array of levels.
			if ( ! mrl_is_valid_level( $new_level ) )
				return;

			// Get the role's current level.
			$role_level = mrl_get_role_level( $role );

			// If the posted level doesn't match the role level, update it.
			if ( $new_level !== $role_level )
				mrl_set_role_level( $role, $new_level );
		}
	}

	/**
	 * Returns the instance.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return object
	 */
	public static function get_instance() {

		static $instance = null;

		if ( is_null( $instance ) ) {
			$instance = new Members_Role_Levels_Meta_Box_Level;
			$instance->setup();
		}

		return $instance;
	}
}

Members_Role_Levels_Meta_Box_Level::get_instance();
