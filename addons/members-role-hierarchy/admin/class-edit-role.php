<?php
/**
 * Adds the role position meta box on the edit/new role screen and handles the save
 * callback functionality.
 *
 * @package   MembersRoleHierarchy
 * @author    The MemberPress Team 
 * @copyright Copyright (c) 2017, The MemberPress Team
 * @link      https://members-plugin.com/
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/**
 * Edit role class.
 *
 * @since  1.0.0
 * @access public
 */
final class MRH_Edit_Role {

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
			$instance = new self;
			$instance->setup_actions();
		}

		return $instance;
	}

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
	private function setup_actions() {

		// Call when screen is loaded.
		add_action( 'members_load_role_edit', array( $this, 'load' ) );
		add_action( 'members_load_role_new',  array( $this, 'load' ) );

		// Save role position.
		add_action( 'members_role_updated', array( $this, 'save' ) );
		add_action( 'members_role_added',   array( $this, 'save' ) );
	}

	/**
	 * Runs on the role new/edit screen load.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function load() {

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
	}

	/**
	 * Adds custom meta boxes to the edit role screen.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $screen_id
	 * @param  string  $role
	 * @return void
	 */
	public function add_meta_boxes( $screen_id, $role = '' ) {

		// If role isn't editable, bail.
		if ( $role && ! members_is_role_editable( $role ) )
			return;

		// Add the meta box.
		add_meta_box(
			'mrh_role_position',
			esc_html__( 'Role Position', 'members' ),
			array( $this, 'meta_box' ),
			$screen_id,
			'side',
			'default'
		);
	}

	/**
	 * Outputs the role position meta box.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  object  $role
	 * @return void
	 */
	public function meta_box( $role ) {

		$position = $role ? mrh_get_role_position( $role->name ) : mrh_get_default_role_position();

		wp_nonce_field( 'role_position', 'mrh_role_position_nonce' ); ?>

		<label>
			<input type="number" name="mrh_role_position" value="<?php echo esc_attr( $position ); ?>" />
		</label>

		<p class="description">
			<?php esc_html_e( "Set the role's position in the hierarchy.", 'members' ); ?>
		</p>
	<?php }

	/**
	 * Saves the role position.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $role
	 * @return void
	 */
	public function save( $role ) {

		// Verify the nonce before proceeding.
		if ( ! isset( $_POST['mrh_role_position_nonce'] ) || ! wp_verify_nonce( $_POST['mrh_role_position_nonce'], 'role_position' ) ) {
			return;
		}

		// Get the current role to edit.
		$role = members_sanitize_role( $role );

		// If the role doesn't exist, bail.
		if ( ! $role )
			return;

		// Get the posted position.
		$new_position = isset( $_POST['mrh_role_position'] ) ? intval( $_POST['mrh_role_position'] ) : 0;

		// Get the role's current position.
		$old_position = mrh_get_role_position( $role );

		// If the posted position doesn't matche the old position, update it.
		if ( $new_position != $old_position )
			mrh_set_role_position( $role, $new_position );
	}
}

MRH_Edit_Role::get_instance();
