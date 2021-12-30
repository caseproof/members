<?php
/**
 * Editor Class.
 *
 * Handles block editor functionality.
 *
 * @package   MembersBlockPermissions
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-block-permissions
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\BlockPermissions;

/**
 * Editor component class.
 *
 * @since  1.0.0
 * @access public
 */
class Editor {

	/**
	 * Bootstraps the component.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function boot() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue'], 1 );
	}

	/**
	 * Enqueues the editor assets.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function enqueue() {

		wp_enqueue_script(
			'members-block-permissions-editor',
			plugin()->asset( 'js/editor.js' ),
			[
				'lodash',
				'wp-block-editor',
				'wp-compose',
				'wp-components',
				'wp-element',
				'wp-hooks'
			],
			null,
			true
		);

		wp_localize_script(
			'members-block-permissions-editor',
			'membersBlockPermissions',
			$this->jsonData()
		);

		wp_enqueue_style(
			'members-block-permissions-editor',
			plugin()->asset( 'css/editor.css' ),
		 	[],
			null
		);
	}

	/**
	 * Returns an array of the data that is passed to the script via JSON.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return array
	 */
	private function jsonData() {

		$labels = [
			'controls' => [],
			'notices'  => []
		];

		$labels['panel'] =  __( 'Permissions', 'members' );

		$labels['controls']['cap'] = [
			'label' => __( 'Capability', 'members' )
		];

		$labels['controls']['condition'] = [
			'label' => __( 'Condition', 'members' ),
			'options' => [
				'default' => __( 'Show block to everyone',   'members' ),
				'show'    => __( 'Show block to selected',   'members' ),
				'hide'    => __( 'Hide block from selected', 'members' )
			]
		];

		$labels['controls']['message'] = [
			'label' => __( 'Error Message', 'members' ),
			'help'  => __( 'Optionally display an error message for users who cannot see this block.', 'members' )
		];

		$labels['controls']['roles'] = [
			'label' => __( 'User Roles', 'members' )
		];

		$labels['controls']['type'] = [
			'label' => __( 'Type', 'members' ),
			'options' => [
				'userStatus' 		=> __( 'User Status', 'members' ),
				'role'       		=> __( 'User Role',   'members' ),
				'cap'        		=> __( 'Capability',  'members' ),
				'paidMembership'	=> __( 'Paid Membership',  'members' ),
				'contentRule'		=> __( 'Content Protection Rule',  'members' )
			]
		];

		$labels['controls']['userStatus'] = [
			'label' => __( 'User Status', 'members' ),
			'options' => [
				'loggedIn'  => __( 'Logged In',  'members' ),
				'loggedOut' => __( 'Logged Out', 'members' )
			]
		];

		$labels['notices']['notAllowed'] = __( 'Your user account does not have access to assign permissions to this block.', 'members' );
		$labels['paidMembership'] = __( 'To protect this block by paid membership or centrally with a content protection rule, add MemberPress.', 'members' );
		$labels['contentRule'] = __( 'To protect this block by paid membership or centrally with a content protection rule, add MemberPress.', 'members' );

		$data = [
			'roles'                    => [],
			'labels'                   => $labels,
			'userCanAssignPermissions' => current_user_can( 'assign_block_permissions' )
		];

		$_roles = wp_roles()->roles;
		ksort( $_roles );

		foreach ( $_roles as $role => $args ) {
			$data['roles'][] = [
				'name'  => $role,
				'label' => $args['name']
			];
		}

		return $data;
	}
}
