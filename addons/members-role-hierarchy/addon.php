<?php

/**
 * Singleton class for setting up the plugin.
 *
 * @since  1.0.0
 * @access public
 */
final class Members_Role_Hierarchy_Plugin {

	/**
	 * Plugin directory path.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $dir = '';

	/**
	 * Plugin directory URI.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $uri = '';

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
			$instance->setup();
			$instance->includes();
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
	 * Magic method to output a string if trying to use the object as a string.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function __toString() {
		return 'members-role-hierarchy';
	}

	/**
	 * Sets up globals.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	private function setup() {

		// Main plugin directory path and URI.
		$this->dir = trailingslashit( plugin_dir_path( __FILE__ ) );
		$this->uri = trailingslashit( plugin_dir_url(  __FILE__ ) );
	}

	/**
	 * Loads files needed by the plugin.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	private function includes() {

		// Load function files.
		require_once( $this->dir . 'inc/functions-filters.php'  );
		require_once( $this->dir . 'inc/functions-options.php'  );
		require_once( $this->dir . 'inc/functions-position.php' );

		// Load admin files.
		if ( is_admin() ) {

			require_once( $this->dir . 'admin/functions-settings.php' );
			require_once( $this->dir . 'admin/class-edit-role.php' );
			require_once( $this->dir . 'admin/class-manage-roles.php' );
		}
	}

	/**
	 * Sets up main plugin actions and filters.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	private function setup_actions() {}
}

Members_Role_Hierarchy_Plugin::get_instance();
