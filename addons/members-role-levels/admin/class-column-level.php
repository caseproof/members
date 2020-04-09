<?php
/**
 * Adds the "Level" column on the manage roles screen.
 *
 * @package    MembersRoleLevels
 * @subpackage Admin
 * @author     Justin Tadlock <justin@justintadlock.com>
 * @copyright  Copyright (c) 2015, Justin Tadlock
 * @link       http://themehybrid.com/plugins/members-role-levels
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/**
 * Manage role levels class.
 *
 * @since  1.0.0
 * @access public
 */
final class Members_Role_Levels_Column_Level {

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

		add_action( 'members_load_manage_roles', array( $this, 'load' ) );
	}

	/**
	 * Executes on page load.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function load() {

		// Print custom styles.
		add_action( 'admin_head', array( $this, 'print_styles' ) );

		// Add custom columns.
		add_filter( 'members_manage_roles_columns', array( $this, 'columns' ) );

		// Output custom column content.
		add_filter( 'members_manage_roles_column_level', array( $this, 'column_level' ), 10, 2 );
	}

	/**
	 * Prints styles to the header.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function print_styles() { ?>
		<style type="text/css">@media only screen and (min-width: 783px) {
			.members_page_roles .column-level { width: 100px; text-align: center; }
		}</style>
	<?php }

	/**
	 * Adds the custom "Level" column.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function columns( $columns ) {

		$columns['level'] = esc_html__( 'Level', 'members' );

		return $columns;
	}

	/**
	 * Returns the content for the level column.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function column_level( $out, $role ) {

		$level = mrl_get_role_level( get_role( $role ) );

		return $level ? mrl_remove_level_prefix( $level ) : '&ndash;';
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
			$instance = new Members_Role_Levels_Column_Level;
			$instance->setup();
		}

		return $instance;
	}
}

Members_Role_Levels_Column_Level::get_instance();
