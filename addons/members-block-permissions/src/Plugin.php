<?php
/**
 * Primary plugin class.
 *
 * Launches the plugin components and acts as a simple container.
 *
 * @package   MembersBlockPermissions
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-block-permissions
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\BlockPermissions;

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
	 * Stores the the `public/mix-manifest.json` data as an array.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $mix = [];

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

		if ( ! $this->mix ) {
			$file      = $this->path( 'public/mix-manifest.json' );
			$this->mix = (array) json_decode( file_get_contents( $file ), true );
		}

		// Make sure to trim any slashes from the front of the path.
		$path = '/' . ltrim( $path, '/' );

		if ( $this->mix && isset( $this->mix[ $path ] ) ) {
			$path = $this->mix[ $path ];
		}

		return $this->uri( 'public' . $path );
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
	 * Returns a plugin component.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $abstract
	 * @return object
	 */
	public function getComponent( $abstract ) {
		return $this->components[ $abstract ];
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
