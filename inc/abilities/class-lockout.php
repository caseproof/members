<?php
/**
 * Self-lockout guard for AI changes.
 *
 * @package    Members
 * @subpackage Abilities
 * @since      3.3.0
 */
namespace Members\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * The one change the AI is never allowed to make: one that leaves the person driving it unable
 * to get into the site or undo it. Before saving, we work out what the current user could still
 * do afterwards and refuse if they'd lose any of {@see self::ESSENTIAL_CAPS} they have now, or
 * lose wp-admin access under the Admin Access add-on.
 *
 * Super admins on multisite can't be locked out of a site, so they're never refused.
 *
 * @since 3.3.0
 */
final class Lockout {

	/**
	 * `read` gets them into wp-admin, `manage_options` is admin power, and `list_roles` +
	 * `edit_roles` let them undo the change on the Roles screen.
	 */
	const ESSENTIAL_CAPS = array( 'read', 'manage_options', 'list_roles', 'edit_roles' );

	/**
	 * Check a change against the current user.
	 *
	 * @param array $change What would change. Any of:
	 *                      - `role_caps`     role => full capability map after the change.
	 *                      - `deleted_roles` role slugs that would be deleted.
	 *                      - `user_roles`    user ID => roles after the change.
	 *                      - `explicit_deny` new value of the "explicitly deny caps" setting.
	 *                      - `admin_access`  roles with wp-admin access under Admin Access, or
	 *                                        null when the add-on would be off.
	 * @return \WP_Error|null Null when it's safe.
	 */
	public static function check( array $change ) {
		$user = wp_get_current_user();

		if ( ! $user->exists() || ( is_multisite() && is_super_admin( $user->ID ) ) ) {
			return null;
		}

		$before = self::caps_for( $user, array() );
		$after  = self::caps_for( $user, $change );
		$lost   = array();

		foreach ( self::ESSENTIAL_CAPS as $cap ) {
			if ( ! empty( $before[ $cap ] ) && empty( $after[ $cap ] ) ) {
				$lost[] = $cap;
			}
		}

		if ( self::has_admin_access( $user, array() ) && ! self::has_admin_access( $user, $change ) ) {
			$lost[] = 'wp-admin access';
		}

		if ( ! $lost ) {
			return null;
		}

		return new \WP_Error(
			'members_lockout',
			sprintf(
				/* translators: %s: what the user would lose, e.g. "manage_options, edit_roles". */
				__( 'Refused: this would lock you out. After this change you would lose %s, so you couldn\'t get back in or undo it. Make this change in wp-admin if you really mean it.', 'members' ),
				implode( ', ', $lost )
			),
			array( 'lost' => $lost )
		);
	}

	/**
	 * Roles the user would have after the change.
	 *
	 * @param \WP_User $user   User.
	 * @param array    $change Change (see check()).
	 * @return string[]
	 */
	private static function roles_for( \WP_User $user, array $change ) {
		$roles = isset( $change['user_roles'][ $user->ID ] ) ? (array) $change['user_roles'][ $user->ID ] : (array) $user->roles;

		if ( ! empty( $change['deleted_roles'] ) ) {
			$had   = $roles;
			$roles = array_values( array_diff( $roles, (array) $change['deleted_roles'] ) );

			// members_delete_role() moves users left with no role to the default role.
			if ( $had && ! $roles ) {
				$default = get_option( 'default_role' );
				if ( $default && ! in_array( $default, (array) $change['deleted_roles'], true ) ) {
					$roles = array( $default );
				}
			}
		}

		return array_values( array_unique( array_filter( $roles, 'is_string' ) ) );
	}

	/**
	 * The user's capabilities after the change, worked out the way WP_User and Members do it:
	 * role caps merged in order, then the user's own caps, then Members' explicit deny.
	 *
	 * @param \WP_User $user   User.
	 * @param array    $change Change (see check()).
	 * @return array cap => bool
	 */
	private static function caps_for( \WP_User $user, array $change ) {
		$roles   = self::roles_for( $user, $change );
		$allcaps = array();
		$denied  = array();

		foreach ( $roles as $role ) {
			if ( isset( $change['role_caps'][ $role ] ) ) {
				$caps = (array) $change['role_caps'][ $role ];
			} else {
				$role_obj = get_role( $role );
				$caps     = $role_obj ? (array) $role_obj->capabilities : array();
			}

			$allcaps = array_merge( $allcaps, $caps );
			$denied  = array_merge( $denied, array_keys( $caps, false, true ) );
		}

		// Caps granted to the user directly (not through a role).
		foreach ( (array) $user->caps as $cap => $grant ) {
			if ( ! get_role( $cap ) ) {
				$allcaps[ $cap ] = $grant;
			}
		}

		$explicit = array_key_exists( 'explicit_deny', $change ) ? (bool) $change['explicit_deny'] : members_explicitly_deny_caps();

		if ( $explicit && count( $roles ) > 1 ) {
			foreach ( $denied as $cap ) {
				$allcaps[ $cap ] = false;
			}
		}

		return $allcaps;
	}

	/**
	 * Whether the user could get into wp-admin under the Admin Access add-on.
	 *
	 * @param \WP_User $user   User.
	 * @param array    $change Change (see check()).
	 * @return bool
	 */
	private static function has_admin_access( \WP_User $user, array $change ) {
		if ( array_key_exists( 'admin_access', $change ) ) {
			$allowed = $change['admin_access'];
		} else {
			$allowed = self::current_admin_access_roles();
		}

		// Add-on off: everyone who can log in gets in.
		if ( null === $allowed ) {
			return true;
		}

		// Administrators always keep access (the add-on's permanent roles).
		$allowed = array_merge( array( 'administrator' ), (array) $allowed );

		return (bool) array_intersect( self::roles_for( $user, $change ), $allowed );
	}

	/**
	 * Roles with wp-admin access right now, or null when Admin Access is off.
	 *
	 * @return string[]|null
	 */
	public static function current_admin_access_roles() {
		if ( ! function_exists( 'Members\AddOns\AdminAccess\get_roles_with_access' ) ) {
			return null;
		}

		return \Members\AddOns\AdminAccess\get_roles_with_access();
	}

	/**
	 * Admin Access roles if it were switched on now with its saved settings.
	 *
	 * @return string[]
	 */
	public static function admin_access_roles_if_enabled() {
		$saved = get_option( 'members_admin_access_settings', array() );

		// Its default is every role.
		if ( ! is_array( $saved ) || ! array_key_exists( 'roles', $saved ) ) {
			return array_keys( wp_roles()->role_names );
		}

		return (array) $saved['roles'];
	}
}
