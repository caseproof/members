<?php
/**
 * Handles the new role screen.
 *
 * @package    Members
 * @subpackage Admin
 * @author     Justin Tadlock <justintadlock@gmail.com>
 * @copyright  Copyright (c) 2009 - 2018, Justin Tadlock
 * @link       https://themehybrid.com/plugins/members
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Members\Admin;

/**
 * Class that displays the new role screen and handles the form submissions for that page.
 *
 * @since  2.0.0
 * @access public
 */
final class Role_New {

	/**
	 * Holds the instances of this class.
	 *
	 * @since  2.0.0
	 * @access private
	 * @var    object
	 */
	private static $instance;

	/**
	 * Name of the page we've created.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    string
	 */
	public $page = '';

	/**
	 * Role that's being created.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    string
	 */
	public $role = '';

	/**
	 * Name of the role that's being created.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    string
	 */
	public $role_name = '';

	/**
	 * Array of the role's capabilities.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    array
	 */
	public $capabilities = array();

	/**
	 * Conditional to see if we're cloning a role.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    bool
	 */
	public $is_clone = false;

	/**
	 * Role that is being cloned.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    string
	 */
	public $clone_role = '';

	/**
	 * Sets up our initial actions.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function __construct() {

		// If the role manager is active.
		if ( members_role_manager_enabled() ) {
			add_action( 'admin_menu', array( $this, 'add_submenu_admin_page' ), 20 );
		}
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
	}

	/**
	 * Adds the roles page to the admin.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function add_admin_page() {

		$this->page = add_menu_page( 'Members', 'Members', 'create_roles', 'members', array( $this, 'page' ), 'data:image/svg+xml;base64,' . base64_encode( '<svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="users-cog" class="svg-inline--fa fa-users-cog fa-w-20" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path fill="currentColor" d="M610.5 341.3c2.6-14.1 2.6-28.5 0-42.6l25.8-14.9c3-1.7 4.3-5.2 3.3-8.5-6.7-21.6-18.2-41.2-33.2-57.4-2.3-2.5-6-3.1-9-1.4l-25.8 14.9c-10.9-9.3-23.4-16.5-36.9-21.3v-29.8c0-3.4-2.4-6.4-5.7-7.1-22.3-5-45-4.8-66.2 0-3.3.7-5.7 3.7-5.7 7.1v29.8c-13.5 4.8-26 12-36.9 21.3l-25.8-14.9c-2.9-1.7-6.7-1.1-9 1.4-15 16.2-26.5 35.8-33.2 57.4-1 3.3.4 6.8 3.3 8.5l25.8 14.9c-2.6 14.1-2.6 28.5 0 42.6l-25.8 14.9c-3 1.7-4.3 5.2-3.3 8.5 6.7 21.6 18.2 41.1 33.2 57.4 2.3 2.5 6 3.1 9 1.4l25.8-14.9c10.9 9.3 23.4 16.5 36.9 21.3v29.8c0 3.4 2.4 6.4 5.7 7.1 22.3 5 45 4.8 66.2 0 3.3-.7 5.7-3.7 5.7-7.1v-29.8c13.5-4.8 26-12 36.9-21.3l25.8 14.9c2.9 1.7 6.7 1.1 9-1.4 15-16.2 26.5-35.8 33.2-57.4 1-3.3-.4-6.8-3.3-8.5l-25.8-14.9zM496 368.5c-26.8 0-48.5-21.8-48.5-48.5s21.8-48.5 48.5-48.5 48.5 21.8 48.5 48.5-21.7 48.5-48.5 48.5zM96 224c35.3 0 64-28.7 64-64s-28.7-64-64-64-64 28.7-64 64 28.7 64 64 64zm224 32c1.9 0 3.7-.5 5.6-.6 8.3-21.7 20.5-42.1 36.3-59.2 7.4-8 17.9-12.6 28.9-12.6 6.9 0 13.7 1.8 19.6 5.3l7.9 4.6c.8-.5 1.6-.9 2.4-1.4 7-14.6 11.2-30.8 11.2-48 0-61.9-50.1-112-112-112S208 82.1 208 144c0 61.9 50.1 112 112 112zm105.2 194.5c-2.3-1.2-4.6-2.6-6.8-3.9-8.2 4.8-15.3 9.8-27.5 9.8-10.9 0-21.4-4.6-28.9-12.6-18.3-19.8-32.3-43.9-40.2-69.6-10.7-34.5 24.9-49.7 25.8-50.3-.1-2.6-.1-5.2 0-7.8l-7.9-4.6c-3.8-2.2-7-5-9.8-8.1-3.3.2-6.5.6-9.8.6-24.6 0-47.6-6-68.5-16h-8.3C179.6 288 128 339.6 128 403.2V432c0 26.5 21.5 48 48 48h255.4c-3.7-6-6.2-12.8-6.2-20.3v-9.2zM173.1 274.6C161.5 263.1 145.6 256 128 256H64c-35.3 0-64 28.7-64 64v32c0 17.7 14.3 32 32 32h65.9c6.3-47.4 34.9-87.3 75.2-109.4z"></path></svg>' ) );

		// We don't need to have a "Members" link in the submenu, so this removes it
		add_submenu_page( 'members', '', '', 'create_roles', 'members', array( $this, 'page' ) );
		remove_submenu_page( 'members', 'members' );

		// Let's roll if we have a page.
		if ( $this->page ) {

			add_action( "load-{$this->page}", array( $this, 'load'          ) );
			add_action( "load-{$this->page}", array( $this, 'add_help_tabs' ) );
		}
	}

	/**
	 * Adds the "Add New Role" submenu page to the admin.
	 *
	 * @since  3.0.0
	 * @access public
	 * @return void
	 */
	public function add_submenu_admin_page() {
		add_submenu_page( 'members', esc_html_x( 'Add New Role', 'admin screen', 'members' ), esc_html_x( 'Add New Role', 'admin screen', 'members' ), 'create_roles', 'members', array( $this, 'page' ) );
	}

	/**
	 * Checks posted data on load and performs actions if needed.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function load() {

		// Are we cloning a role?
		$this->is_clone = isset( $_GET['clone'] ) && members_role_exists( $_GET['clone'] );

		if ( $this->is_clone ) {

			// Override the default new role caps.
			add_filter( 'members_new_role_default_caps', array( $this, 'clone_default_caps' ), 15 );

			// Set the clone role.
			$this->clone_role = members_sanitize_role( $_GET['clone'] );
		}

		// Check if the current user can create roles and the form has been submitted.
		if ( current_user_can( 'create_roles' ) && isset( $_POST['members_new_role_nonce'] ) ) {

			// Verify the nonce.
			check_admin_referer( 'new_role', 'members_new_role_nonce' );

			// Set up some variables.
			$this->capabilities = array();
			$new_caps           = array();
			$is_duplicate       = false;

			// Get all the capabilities.
			$_m_caps = members_get_capabilities();

			// Add all caps from the cap groups.
			foreach ( members_get_cap_groups() as $group )
				$_m_caps = array_merge( $_m_caps, $group->caps );

			// Make sure we have a unique array of caps.
			$_m_caps = array_unique( $_m_caps );

			// Check if any capabilities were selected.
			if ( isset( $_POST['grant-caps'] ) || isset( $_POST['deny-caps'] ) ) {

				$grant_caps = ! empty( $_POST['grant-caps'] ) ? members_remove_hidden_caps( array_unique( $_POST['grant-caps'] ) ) : array();
				$deny_caps  = ! empty( $_POST['deny-caps'] )  ? members_remove_hidden_caps( array_unique( $_POST['deny-caps']  ) ) : array();

				foreach ( $_m_caps as $cap ) {

					if ( in_array( $cap, $grant_caps ) )
						$new_caps[ $cap ] = true;

					else if ( in_array( $cap, $deny_caps ) )
						$new_caps[ $cap ] = false;
				}
			}

			$grant_new_caps = ! empty( $_POST['grant-new-caps'] ) ? members_remove_hidden_caps( array_unique( $_POST['grant-new-caps'] ) ) : array();
			$deny_new_caps  = ! empty( $_POST['deny-new-caps'] )  ? members_remove_hidden_caps( array_unique( $_POST['deny-new-caps']  ) ) : array();

			foreach ( $grant_new_caps as $grant_new_cap ) {

				$_cap = members_sanitize_cap( $grant_new_cap );

				if ( ! in_array( $_cap, $_m_caps ) )
					$new_caps[ $_cap ] = true;
			}

			foreach ( $deny_new_caps as $deny_new_cap ) {

				$_cap = members_sanitize_cap( $deny_new_cap );

				if ( ! in_array( $_cap, $_m_caps ) )
					$new_caps[ $_cap ] = false;
			}

			// Sanitize the new role name/label. We just want to strip any tags here.
			if ( ! empty( $_POST['role_name'] ) )
				$this->role_name = wp_strip_all_tags( wp_unslash( $_POST['role_name'] ) );

			// Sanitize the new role, removing any unwanted characters.
			if ( ! empty( $_POST['role'] ) )
				$this->role = members_sanitize_role( $_POST['role'] );

			else if ( $this->role_name )
				$this->role = members_sanitize_role( $this->role_name );

			// Is duplicate?
			if ( members_role_exists( $this->role ) )
				$is_duplicate = true;

			// Add a new role with the data input.
			if ( $this->role && $this->role_name && ! $is_duplicate ) {

				add_role( $this->role, $this->role_name, $new_caps );

				// Action hook for when a role is added.
				do_action( 'members_role_added', $this->role );

				// If the current user can edit roles, redirect to edit role screen.
				if ( current_user_can( 'edit_roles' ) ) {
					wp_redirect( esc_url_raw( add_query_arg( 'message', 'role_added', members_get_edit_role_url( $this->role ) ) ) );
 					exit;
				}

				// Add role added message.
				add_settings_error( 'members_role_new', 'role_added', sprintf( esc_html__( 'The %s role has been created.', 'members' ), $this->role_name ), 'updated' );
			}

			// If there are new caps, let's assign them.
			if ( ! empty( $new_caps ) )
				$this->capabilities = $new_caps;

			// Add error if there's no role.
			if ( ! $this->role )
				add_settings_error( 'members_role_new', 'no_role', esc_html__( 'You must enter a valid role.', 'members' ) );

			// Add error if this is a duplicate role.
			if ( $is_duplicate )
				add_settings_error( 'members_role_new', 'duplicate_role', sprintf( esc_html__( 'The %s role already exists.', 'members' ), $this->role ) );

			// Add error if there's no role name.
			if ( ! $this->role_name )
				add_settings_error( 'members_role_new', 'no_role_name', esc_html__( 'You must enter a valid role name.', 'members' ) );
		}

		// If we don't have caps yet, get the new role default caps.
		if ( empty( $this->capabilities ) )
			$this->capabilities = members_new_role_default_caps();

		// Load page hook.
		do_action( 'members_load_role_new' );

		// Hook for adding in meta boxes.
		do_action( 'add_meta_boxes_' . get_current_screen()->id, '' );
		do_action( 'add_meta_boxes',   get_current_screen()->id, '' );

		// Add layout screen option.
		add_screen_option( 'layout_columns', array( 'max' => 2, 'default' => 2 ) );

		// Load scripts/styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Adds help tabs.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function add_help_tabs() {

		// Get the current screen.
		$screen = get_current_screen();

		// Add help tabs.
		$screen->add_help_tab( members_get_edit_role_help_overview_args()   );
		$screen->add_help_tab( members_get_edit_role_help_role_name_args()  );
		$screen->add_help_tab( members_get_edit_role_help_edit_caps_args()  );
		$screen->add_help_tab( members_get_edit_role_help_custom_cap_args() );

		// Set the help sidebar.
		$screen->set_help_sidebar( members_get_help_sidebar_text() );
	}

	/**
	 * Enqueue scripts/styles.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function enqueue() {

		wp_enqueue_style(  'members-admin'     );
		wp_enqueue_script( 'members-edit-role' );
	}

	/**
	 * Outputs the page.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function page() { ?>

		<div class="wrap">

			<h1><?php ! $this->is_clone ? esc_html_e( 'Add New Role', 'members' ) : esc_html_e( 'Clone Role', 'members' ); ?></h1>

			<?php settings_errors( 'members_role_new' ); ?>

			<div id="poststuff">

				<form name="form0" method="post" action="<?php echo esc_url( members_get_new_role_url() ); ?>">

					<?php wp_nonce_field( 'new_role', 'members_new_role_nonce' ); ?>

					<div id="post-body" class="metabox-holder columns-<?php echo 1 == get_current_screen()->get_columns() ? 1 : 2; ?>">

						<div id="post-body-content">

							<div id="titlediv" class="members-title-div">

								<div id="titlewrap">
									<span class="screen-reader-text"><?php esc_html_e( 'Role Name', 'members' ); ?></span>
									<input type="text" name="role_name" value="<?php echo ! $this->role && $this->clone_role ? esc_attr( sprintf( __( '%s Clone', 'members' ), members_get_role( $this->clone_role )->get( 'label' ) ) ) : esc_attr( $this->role_name ); ?>" placeholder="<?php esc_attr_e( 'Enter role name', 'members' ); ?>" />
								</div><!-- #titlewrap -->

								<div class="inside">
									<div id="edit-slug-box">
										<strong><?php esc_html_e( 'Role:', 'members' ); ?></strong> <span class="role-slug"><?php echo ! $this->role && $this->clone_role ? esc_attr( "{$this->clone_role}_clone" ) : esc_attr( $this->role ); ?></span> <!-- edit box -->
										<input type="text" name="role" value="<?php echo members_sanitize_role( $this->role ); ?>" />
										<button type="button" class="role-edit-button button button-small closed"><?php esc_html_e( 'Edit', 'members' ); ?></button>
									</div>
								</div><!-- .inside -->

							</div><!-- .members-title-div -->

							<?php $cap_tabs = new Cap_Tabs( '', $this->capabilities ); ?>
							<?php $cap_tabs->display(); ?>

						</div><!-- #post-body-content -->

						<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
						<?php wp_nonce_field( 'meta-box-order',  'meta-box-order-nonce', false ); ?>

						<div id="postbox-container-1" class="postbox-container side">

							<?php do_meta_boxes( get_current_screen()->id, 'side', '' ); ?>

						</div><!-- .post-box-container -->

					</div><!-- #post-body -->
				</form>

			</div><!-- #poststuff -->

		</div><!-- .wrap -->

	<?php }

	/**
	 * Filters the new role default caps in the case that we're cloning a role.
	 *
	 * @since  2.0.0
	 * @access public
	 * @param  array  $capabilities
	 * @param  array
	 */
	public function clone_default_caps( $capabilities ) {

		if ( $this->is_clone ) {

			$role = get_role( $this->clone_role );

			if ( $role && isset( $role->capabilities ) && is_array( $role->capabilities ) )
				$capabilities = $role->capabilities;
		}

		return $capabilities;
	}

	/**
	 * Returns the instance.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return object
	 */
	public static function get_instance() {

		if ( ! self::$instance )
			self::$instance = new self;

		return self::$instance;
	}
}

Role_New::get_instance();
