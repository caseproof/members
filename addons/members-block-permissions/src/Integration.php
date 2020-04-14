<?php
/**
 * Integration Class.
 *
 * Integrates the plugin with the Members plugin.
 *
 * @package   MembersBlockPermissions
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-block-permissions
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\BlockPermissions;

use function members_register_cap;

/**
 * Integration component class.
 *
 * @since  1.0.0
 * @access public
 */
class Integration {

	/**
	 * Bootstraps the component.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function boot() {
		add_action( 'members_register_caps', [ $this, 'registerCaps' ] );
	}

	/**
	 * Registers our custom capability with the Members plugin.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function registerCaps() {

		if ( function_exists( 'members_register_cap' ) ) {

			members_register_cap( 'assign_block_permissions', [
				'label'       => __( 'Assign Block Permissions', 'members' ),
				'description' => __( 'Allows users to assign block permissions inside of the block editor.', 'members' ),
				'group'       => 'type-wp_block'
			] );
		}
	}
}
