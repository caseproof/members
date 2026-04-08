<?php
/**
 * Application container for Admin Menus add-on.
 *
 * @package    Members
 * @subpackage AddOns
 */

namespace Members\AddOns\AdminMenus;

defined( 'ABSPATH' ) || exit;

/**
 * App class.
 *
 * @since 1.0.0
 */
class App {

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	public $dir = '';

	/**
	 * Filter namespace prefix.
	 *
	 * @var string
	 */
	public $namespace = '';

	/**
	 * Constructor.
	 *
	 * @param array $args Config.
	 */
	public function __construct( array $args = array() ) {
		foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$this->$key = $args[ $key ];
			}
		}
	}
}
