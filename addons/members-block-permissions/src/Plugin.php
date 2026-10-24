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
	 * Stores the plugin directory path.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $path;

	/**
	 * Stores the plugin directory URI.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $uri;

	/**
	 * Holds an array of the plugin component objects.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $components = [];

	/**
	 * Sets up the object properties.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $path  Plugin directory path.
	 * @param  string  $uri   Plugin directory URI.
	 * @return void
	 */
	public function __construct( $path, $uri ) {

		$this->path = untrailingslashit( $path );
		$this->uri  = untrailingslashit( $uri );

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
	 * Returns the plugin path.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $file
	 * @return string
	 */
	public function path( $file = '' ) {

		$file = ltrim( $file, '/' );

		return $file ? $this->path . "/{$file}" : $this->path;
	}

	/**
	 * Returns the plugin URI.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $file
	 * @return string
	 */
	public function uri( $file = '' ) {

		$file = ltrim( $file, '/' );

		return $file ? $this->uri . "/{$file}" : $this->uri;
	}

	/**
	 * Helper function for outputting an asset URL in the plugin.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $path  A relative path/file to append to the `public` folder.
	 * @return string
	 */
	function asset( $path ) {

		return $this->uri( 'public/' . ltrim( $path, '/' ) );
	}

	/**
	 * Version of a built script, from the `*.asset.php` file `wp-scripts` writes
	 * next to it, for cache busting.
	 *
	 * @since  3.2.28
	 * @access public
	 * @param  string  $name  Entry name, e.g. `editor`.
	 * @return string|null
	 */
	function assetVersion( $name ) {

		$file = $this->path( "public/js/{$name}.asset.php" );

		if ( ! file_exists( $file ) ) {
			return null;
		}

		$asset = include $file;

		return isset( $asset['version'] ) ? $asset['version'] : null;
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
