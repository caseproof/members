<?php
/**
 * Block Class.
 *
 * Handles front-end output of blocks.
 *
 * @package   MembersBlockPermissions
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-block-permissions
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\BlockPermissions;

use WP_User;

/**
 * Block component class.
 *
 * @since  1.0.0
 * @access public
 */
class Block {

	/**
	 * Bootstraps the component.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function boot() {
		add_filter( 'render_block', [ $this, 'renderBlock' ], PHP_INT_MAX, 2 );
	}

	/**
	 * Short-circuits block rendering on the front end if the user doesn't
	 * have permission view the block.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string|null  $content  Returning anything other than null will short-circuit the block.
	 * @param  array        $block       The block data.
	 * @return mixed
	 */
	public function renderBlock( $content, $block ) {

		// Check if we have nested blocks (e.g. columns)
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				$this->renderBlock( $content, $inner_block );
			}
		}

		// Bail if we're in the admin or there are no block attributes.
		if ( is_admin() || ! isset( $block['attrs'] ) ) {
			return $content;
		}

		// Bail if there isn't a condition set.
		if ( ! isset( $block['attrs']['blockPermissionsCondition'] ) || ! $block['attrs']['blockPermissionsCondition'] ) {
			return $content;
		}

		// Bail if there isn't a type set.
		if ( ! isset( $block['attrs']['blockPermissionsType'] ) || ! $block['attrs']['blockPermissionsType'] ) {
			return $content;
		}

		// Gets the permissions type.
		$type = $block['attrs']['blockPermissionsType'];

		// Assume that we will render this block by default.
		$maybe_render = true;

		// Check the permission type to determine whether we will render
		// the block.
		if ( 'user-status' === $type ) {
			$maybe_render = $this->checkUserStatus( $block );
		} elseif ( 'role' === $type ) {
			$maybe_render = $this->checkRole( $block );
		} elseif ( 'cap' === $type ) {
			$maybe_render = $this->checkCap( $block );
		}

		// If the block should not be rendered.
		if ( ! $maybe_render ) {
			$content = $this->get_block_replacement_content( $block );
		}

		return $content;
	}

	protected function get_block_replacement_content( $block ) {
		// Set to an empty string by default, which will short-
		// circuit the block output.
		$replacement_content = '';

		// Get the error message.
		$message = isset( $block['attrs']['blockPermissionsMessage'] )
		           ? $block['attrs']['blockPermissionsMessage']
			   : '';

		// Allow devs to overwrite the message.
   		$message = apply_filters(
   			'members/block/permissions/error/message',
			$message,
			$block
		);

		// Check if there's an error message and use it if so.
		if ( $message ) {

			$class = apply_filters(
				'members/block/permissions/error/class',
				[ 'block-permissions-error' ],
				$block
			);

			$replacement_content = sprintf(
				'<div class="%s">%s</div>',
				esc_attr( join( ' ', $class ) ),
				wpautop( $message )
			);

			$replacement_content = apply_filters(
				'members/block/permissions/error',
				$replacement_content,
				$block
			);
		}

		return $replacement_content;
	}

	/**
	 * Determines whether to render the block based on user status.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  array   $block  The block data.
	 * @return bool
	 */
	protected function checkUserStatus( $block ) {

		$maybe_render = true;
		$user_status  = false;

		if ( isset( $block['attrs']['blockPermissionsUserStatus'] ) && $block['attrs']['blockPermissionsUserStatus'] ) {
			$user_status = $block['attrs']['blockPermissionsUserStatus'];
		}

		// Bail if we don't have a user status.
		if ( ! $user_status ) {
			return $maybe_render;
		}

		$condition = $block['attrs']['blockPermissionsCondition'];

		if ( '=' === $condition ) {

			$maybe_render = false;

			if ( 'logged-in' === $user_status && is_user_logged_in() ) {
				$maybe_render = true;
			} elseif ( 'logged-out' === $user_status && ! is_user_logged_in() ) {
				$maybe_render = true;
			}

		} elseif ( '!=' === $condition ) {

			$maybe_render = true;

			if ( 'logged-in' === $user_status && is_user_logged_in() ) {
				$maybe_render = false;
			} elseif ( 'logged-out' === $user_status && ! is_user_logged_in() ) {
				$maybe_render = false;
			}
		}

		return $maybe_render;
	}

	/**
	 * Determines whether to render the block based on user role.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  array   $block  The block data.
	 * @return bool
	 */
	protected function checkRole( $block ) {

		$maybe_render = true;
		$roles        = false;

		if ( isset( $block['attrs']['blockPermissionsRoles'] ) && is_array( $block['attrs']['blockPermissionsRoles'] ) && $block['attrs']['blockPermissionsRoles'] ) {
			$roles = $block['attrs']['blockPermissionsRoles'];
		}

		// Bail if we don't have any roles.
		if ( ! $roles ) {
			return $maybe_render;
		}

		$condition = $block['attrs']['blockPermissionsCondition'];

		if ( '=' === $condition ) {

			$maybe_render = false;

			if ( is_user_logged_in() ) {
				$user = new WP_User( get_current_user_id() );

				foreach ( (array) $user->roles as $role ) {

					if ( in_array( $role, $roles ) ) {
						$maybe_render = true;
						break;
					}
				}
			}

		} elseif ( '!=' === $condition ) {

			$maybe_render = true;

			if ( is_user_logged_in() ) {
				$user = new WP_User( get_current_user_id() );

				foreach ( (array) $user->roles as $role ) {

					if ( in_array( $role, $roles ) ) {
						$maybe_render = false;
						break;
					}
				}
			}
		}

		return $maybe_render;
	}

	/**
	 * Determines whether to render the block based on capability.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  array   $block  The block data.
	 * @return bool
	 */
	protected function checkCap( $block ) {

		$maybe_render = true;
		$cap          = false;

		if ( isset( $block['attrs']['blockPermissionsCap'] ) && $block['attrs']['blockPermissionsCap'] ) {
			$cap = $block['attrs']['blockPermissionsCap'];
		}

		// Bail if we have no capability.
		if ( ! $cap ) {
			return $maybe_render;
		}

		$condition = $block['attrs']['blockPermissionsCondition'];

		if ( '=' === $condition ) {
			$maybe_render = current_user_can( $cap );
		} elseif ( '!=' === $condition ) {
			$maybe_render = ! current_user_can( $cap );
		}

		return $maybe_render;
	}
}
