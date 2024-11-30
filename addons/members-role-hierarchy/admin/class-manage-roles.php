<?php
/**
 * Class for handling extras on the Manage Roles screen.
 *
 * @package   MembersRoleHierarchy
 * @author    The MemberPress Team 
 * @copyright Copyright (c) 2017, The MemberPress Team
 * @link      https://members-plugin.com/
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/**
 * Role management class.
 *
 * @since  1.0.0
 * @access public
 */
final class MRH_Manage_Roles {

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
	 * Sets up our initial actions.
	 *
	 * @since  1.0.0
	 * @access public
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

		add_action( 'members_load_manage_roles', array( $this, 'load' ) );
	}

	/**
	 * Runs on the manage roles screen load.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function load() {

		add_filter( 'members_manage_roles_columns', array( $this, 'columns' ) );

		add_filter( 'members_manage_roles_column_position', array( $this, 'column_position' ), 10, 2 );

		add_action( 'admin_head', array( $this, 'print_styles' ) );
	}

	/**
	 * Adds custom columns.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array   $columns
	 * @return array
	 */
	public function columns( $columns ) {

		$columns['position'] = esc_html__( 'Position', 'members' );

		return $columns;
	}

	/**
	 * Handles the output for custom "position" column.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $output
	 * @param  string  $role
	 * @return string
	 */
	public function column_position( $output, $role ) {

		return mrh_get_role_position( $role );
	}

	/**
	 * Prints custom styles to the header.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function print_styles() { ?>

		<style type="text/css">
			@media only screen and ( min-width: 783px ) {
				.members_page_roles .column-position {
					width: 100px;
					text-align: center;
				}
			}
		</style>
	<?php }
}

MRH_Manage_Roles::get_instance();
