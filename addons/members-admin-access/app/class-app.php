<?php
/**
 * Primary class for setting up the plugin.
 *
 * @package   MembersAdminAccess
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-admin-access
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Members\AddOns\AdminAccess;

/**
 * Application class.
 *
 * @since  1.0.0
 * @access public
 */
class App {

	/**
	 * Houses the plugin directory path.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $dir = '';

	/**
	 * Namespace used for filter hooks and such.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $namespace = '';

	/**
	 * Takes in a configuration array and assigns the keys to the class properties.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function __construct( array $args = [] ) {

		foreach ( array_keys( get_object_vars( $this ) ) as $key ) {

			if ( isset( $args[ $key ] ) )
				$this->$key = $args[ $key ];
		}
	}
}
