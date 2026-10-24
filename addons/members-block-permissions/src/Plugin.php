<?php
/**
 * Primary plugin class.
 *
 * Launches the plugin components and acts as a simple container.
 *
 * @package   MembersBlockPermissions
 * @author    The MemberPress Team 
 * @copyright 2019, The MemberPress Team
 * @link      https://members-plugin.com/
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */
namespace Members\BlockPermissions;

defined('ABSPATH') || exit;
/**
 * Plugin class.
 *
 * @since  1.0.0
 * @access public
 */
class Plugin {

	/**
	 * Holds an array of the plugin component objects.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $components = [];

	/**
	 * Registers the plugin components.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function __construct() {

		$this->registerDefaultComponents();
	}

	/**
	 * Bootstraps the components.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function boot() {

		// Bootstrap components.
		foreach ( $this->components as $component ) {
			$component->boot();
		}
	}

	/**
	 * Registers the default plugin components.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	protected function registerDefaultComponents() {

		$components = [
			Block::class,
			Editor::class,
			Integration::class
		];

		foreach ( $components as $component ) {
			$this->registerComponent( $component );
		}
	}

	/**
	 * Registers a plugin component.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $abstract
	 * @return void
	 */
	protected function registerComponent( $abstract ) {
		$this->components[ $abstract ] = new $abstract();
	}
}
