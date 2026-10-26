<?php
/**
 * User role abilities.
 *
 * @package    Members
 * @subpackage Abilities
 * @since      3.3.0
 */
namespace Members\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * See users' roles, explain why someone can or can't do something, and change users' roles.
 *
 * @since 3.3.0
 */
final class User_Abilities {

	/**
	 * Ability definitions keyed by name (without the prefix).
	 *
	 * @return array
	 */
	public static function definitions() {
		$group  = __( 'Members', 'members' );
		$view   = array( 'view-users', __( 'View users\' roles', 'members' ) );
		$manage = array( 'manage-users', __( 'Manage users\' roles', 'members' ) );

		return array(
			'get-user-roles'     => Ability::finish(
				Ability::args(
					__( 'Get user roles', 'members' ),
					__( 'Get a user\'s roles and every capability they end up with (after Members\' explicit-deny rule).', 'members' ),
					Ability::object_schema( array( 'user' => Ability::user_property() ), array( 'user' ) ),
					array( __CLASS__, 'get_user_roles' ),
					true,
					Ability::can( 'list_users' ),
					$group
				),
				$view[0],
				$view[1],
				array( __( 'What roles does jane@example.com have?', 'members' ) )
			),
			'list-users-by-role' => Ability::finish(
				Ability::args(
					__( 'List users by role', 'members' ),
					__( 'List the users who have a role (paged): ID, username, display name, email and all their roles.', 'members' ),
					Ability::object_schema(
						array(
							'role'     => Ability::role_property( __( 'Role slug.', 'members' ) ),
							'per_page' => array(
								'type'    => 'integer',
								'minimum' => 1,
								'maximum' => 100,
							),
							'page'     => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
						),
						array( 'role' )
					),
					array( __CLASS__, 'list_users_by_role' ),
					true,
					Ability::can( 'list_users' ),
					$group
				),
				$view[0],
				$view[1],
				array( __( 'Who are my Editors?', 'members' ) )
			),
			'check-user-access'  => Ability::finish(
				Ability::args(
					__( 'Check user access', 'members' ),
					__( 'Explain in plain words why a user can or can\'t do something (a capability, e.g. "edit_pages") or see a piece of content (a post ID). Shows which roles grant or deny it and any content restriction, including ones inherited from a parent page.', 'members' ),
					Ability::object_schema(
						array(
							'user'       => Ability::user_property(),
							'capability' => array(
								'type'        => 'string',
								'description' => __( 'Capability to check, e.g. "edit_pages".', 'members' ),
							),
							'post_id'    => Ability::post_property(),
						),
						array( 'user' )
					),
					array( __CLASS__, 'check_user_access' ),
					true,
					Ability::can( 'list_users' ),
					$group
				),
				$view[0],
				$view[1],
				array(
					__( 'Why can\'t Jane see the Members Area page?', 'members' ),
					__( 'Can Bob publish posts?', 'members' ),
				)
			),
			'set-user-roles'     => Ability::finish(
				Ability::args(
					__( 'Set user roles', 'members' ),
					__( 'Change a user\'s roles. Pass "roles" to set the exact list, or "add" and/or "remove". Only roles you are allowed to assign are touched. Refuses changes that would lock you out.', 'members' ),
					Ability::object_schema(
						array(
							'user'   => Ability::user_property(),
							'roles'  => Ability::roles_property( __( 'Exact roles the user should end up with.', 'members' ) ),
							'add'    => Ability::roles_property( __( 'Roles to add.', 'members' ) ),
							'remove' => Ability::roles_property( __( 'Roles to remove.', 'members' ) ),
						),
						array( 'user' )
					),
					array( __CLASS__, 'set_user_roles' ),
					false,
					static function ( $input ) {
						$user = isset( $input['user'] ) ? Ability::find_user( $input['user'] ) : null;
						return current_user_can( 'promote_users' ) && $user instanceof \WP_User && current_user_can( 'edit_user', $user->ID );
					},
					$group,
					true
				),
				$manage[0],
				$manage[1],
				array( __( 'Make jane@example.com an Editor.', 'members' ) )
			),
		);
	}

	/**
	 * A user's roles and capabilities.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function get_user_roles( array $input ) {
		$user = Ability::find_user( $input['user'] );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$caps = array();
		foreach ( $user->get_role_caps() as $cap => $grant ) {
			if ( ! get_role( $cap ) ) {
				$caps[ $cap ] = $grant;
			}
		}

		// Members' explicit-deny rule (see members_user_has_cap_filter()).
		if ( members_explicitly_deny_caps() && 1 < count( $user->roles ) ) {
			foreach ( $user->roles as $role ) {
				$role_obj = get_role( $role );
				foreach ( $role_obj ? array_keys( (array) $role_obj->capabilities, false, true ) : array() as $cap ) {
					$caps[ $cap ] = false;
				}
			}
		}

		$keep = members_remove_old_levels( array_keys( $caps ) );
		$caps = array_intersect_key( $caps, array_flip( $keep ) );

		return array(
			'user'    => self::user_row( $user ),
			'granted' => array_values( array_keys( $caps, true ) ),
			'denied'  => array_values( array_keys( $caps, false ) ),
		);
	}

	/**
	 * Users with a role.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function list_users_by_role( array $input ) {
		$role = (string) $input['role'];

		$error = Ability::unknown_roles_error( array( $role ) );
		if ( $error ) {
			return $error;
		}

		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : 20;
		$page     = isset( $input['page'] ) ? (int) $input['page'] : 1;
		$query    = new \WP_User_Query(
			array(
				'role'        => $role,
				'number'      => $per_page,
				'paged'       => $page,
				'orderby'     => 'display_name',
				'count_total' => true,
			)
		);

		return array(
			'users'       => array_map( array( __CLASS__, 'user_row' ), $query->get_results() ),
			'total'       => (int) $query->get_total(),
			'total_pages' => (int) ceil( $query->get_total() / max( 1, $per_page ) ),
		);
	}

	/**
	 * Explain a user's access.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function check_user_access( array $input ) {
		$user = Ability::find_user( $input['user'] );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( empty( $input['capability'] ) && empty( $input['post_id'] ) ) {
			return new \WP_Error( 'members_nothing_to_check', __( 'Pass a capability or a post_id to check.', 'members' ) );
		}

		$result = array( 'user' => self::user_row( $user ) );

		if ( ! empty( $input['capability'] ) ) {
			$result['capability'] = self::explain_cap( $user, members_sanitize_cap( (string) $input['capability'] ) );
		}

		if ( ! empty( $input['post_id'] ) ) {
			$post = get_post( (int) $input['post_id'] );

			if ( ! $post ) {
				return new \WP_Error( 'members_no_post', __( 'No post found with that ID.', 'members' ) );
			}

			$result['content'] = self::explain_post( $user, $post );
		}

		return $result;
	}

	/**
	 * Change a user's roles.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function set_user_roles( array $input ) {
		$user = Ability::find_user( $input['user'] );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$current = array_values( (array) $user->roles );

		if ( isset( $input['roles'] ) ) {
			$roles = members_sanitize_access_role_meta_list( (array) $input['roles'] );
		} elseif ( isset( $input['add'] ) || isset( $input['remove'] ) ) {
			$add    = members_sanitize_access_role_meta_list( isset( $input['add'] ) ? (array) $input['add'] : array() );
			$remove = members_sanitize_access_role_meta_list( isset( $input['remove'] ) ? (array) $input['remove'] : array() );
			$roles  = array_values( array_diff( array_unique( array_merge( $current, $add ) ), $remove ) );
		} else {
			return new \WP_Error( 'members_nothing_to_change', __( 'Pass roles, or add and/or remove.', 'members' ) );
		}

		$error = Ability::unknown_roles_error( $roles );
		if ( $error ) {
			return $error;
		}

		// Roles this user may not assign or take away would be skipped silently; say so instead.
		$changing     = array_merge( array_diff( $roles, $current ), array_diff( $current, $roles ) );
		$not_editable = array_values( array_filter( $changing, static function ( $role ) {
			return ! members_is_role_editable( $role );
		} ) );

		if ( $not_editable ) {
			return new \WP_Error(
				'members_role_not_editable',
				sprintf(
					/* translators: %s: role slugs. */
					__( 'You aren\'t allowed to assign or remove these roles: %s.', 'members' ),
					implode( ', ', $not_editable )
				)
			);
		}

		if ( 1 < count( $roles ) && ! members_multiple_user_roles_enabled() ) {
			return new \WP_Error( 'members_single_role', __( 'Multiple roles per user is turned off in Members > Settings, so a user can only have one role.', 'members' ) );
		}

		if ( ! array_diff( $roles, $current ) && ! array_diff( $current, $roles ) ) {
			return new \WP_Error( 'members_nothing_to_change', __( 'The user already has exactly those roles.', 'members' ) );
		}

		$lockout = Lockout::check( array( 'user_roles' => array( $user->ID => $roles ) ) );
		if ( $lockout ) {
			return $lockout;
		}

		$labels = static function ( $list ) {
			return $list ? implode( ', ', array_map( array( Ability::class, 'role_label' ), $list ) ) : __( '(no role)', 'members' );
		};

		$ask = Confirmation::check(
			Ability::PREFIX . 'set-user-roles',
			$input,
			sprintf(
				/* translators: 1: user name, 2: current roles, 3: new roles. */
				__( 'This changes %1$s\'s roles from %2$s to %3$s, which changes what they can do on the site.', 'members' ),
				$user->display_name,
				$labels( $current ),
				$labels( $roles )
			),
			array(
				'user'   => $user->user_login,
				'before' => $current,
				'after'  => $roles,
			)
		);
		if ( $ask ) {
			return $ask;
		}

		members_set_user_roles( $user, $roles );

		$user = get_user_by( 'id', $user->ID );

		return array(
			'user'     => self::user_row( $user ),
			'summary'  => sprintf(
				/* translators: 1: user name, 2: roles. */
				__( '%1$s now has: %2$s.', 'members' ),
				$user->display_name,
				$labels( array_values( (array) $user->roles ) )
			),
			'previous' => array( 'roles' => $current ),
		);
	}

	/**
	 * Explain a capability for a user.
	 *
	 * @param \WP_User $user User.
	 * @param string   $cap  Capability.
	 * @return array
	 */
	private static function explain_cap( \WP_User $user, $cap ) {
		$granted_by = array();
		$denied_by  = array();

		foreach ( (array) $user->roles as $role ) {
			$role_obj = get_role( $role );
			if ( $role_obj && isset( $role_obj->capabilities[ $cap ] ) ) {
				if ( $role_obj->capabilities[ $cap ] ) {
					$granted_by[] = $role;
				} else {
					$denied_by[] = $role;
				}
			}
		}

		$direct = isset( $user->caps[ $cap ] ) && ! get_role( $cap ) ? (bool) $user->caps[ $cap ] : null;
		$can    = user_can( $user, $cap );

		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			$why = __( 'They are a network super admin, so they can do everything.', 'members' );
		} elseif ( $can && $granted_by ) {
			/* translators: %s: roles. */
			$why = sprintf( __( 'Granted by their role(s): %s.', 'members' ), implode( ', ', array_map( array( Ability::class, 'role_label' ), $granted_by ) ) );
		} elseif ( $can ) {
			$why = __( 'Allowed, but not by a role directly: it\'s granted to the user themselves, or another plugin or WordPress itself maps it to something they have.', 'members' );
		} elseif ( $denied_by && $granted_by && members_explicitly_deny_caps() ) {
			/* translators: 1: denying roles, 2: granting roles. */
			$why = sprintf( __( 'Denied. %2$s grants it, but %1$s explicitly denies it, and Members makes a deny win when a user has several roles.', 'members' ), implode( ', ', array_map( array( Ability::class, 'role_label' ), $denied_by ) ), implode( ', ', array_map( array( Ability::class, 'role_label' ), $granted_by ) ) );
		} elseif ( $denied_by ) {
			/* translators: %s: roles. */
			$why = sprintf( __( 'Denied: explicitly denied by their role(s): %s.', 'members' ), implode( ', ', array_map( array( Ability::class, 'role_label' ), $denied_by ) ) );
		} elseif ( false === $direct ) {
			$why = __( 'Denied directly on the user\'s account (not by a role).', 'members' );
		} else {
			$why = __( 'None of their roles grant it.', 'members' );
		}

		return array(
			'capability' => $cap,
			'allowed'    => $can,
			'why'        => $why,
			'granted_by' => $granted_by,
			'denied_by'  => $denied_by,
		);
	}

	/**
	 * Explain whether a user can see a post.
	 *
	 * @param \WP_User $user User.
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	private static function explain_post( \WP_User $user, \WP_Post $post ) {
		$restriction = Content_Abilities::restriction( $post );
		$can         = members_can_user_view_post( $user->ID, $post->ID );
		$type        = get_post_type_object( $post->post_type );

		if ( ! members_content_permissions_enabled() ) {
			$why = __( 'Content Permissions is turned off in Members > Settings, so Members doesn\'t restrict any content.', 'members' );
		} elseif ( ! $restriction['roles'] ) {
			$why = $can
				? __( 'This content isn\'t restricted by Members, so anyone who can see the site can see it.', 'members' )
				: __( 'Members doesn\'t restrict this content, but another plugin is blocking it.', 'members' );
		} else {
			$where = $restriction['inherited_from']
				/* translators: %s: parent post title. */
				? sprintf( __( 'It inherits a restriction from "%s".', 'members' ), get_the_title( $restriction['inherited_from'] ) )
				: __( 'It is restricted.', 'members' );
			$allowed = implode( ', ', array_map( array( Ability::class, 'role_label' ), $restriction['roles'] ) );

			if ( (int) $post->post_author === $user->ID ) {
				$why = $where . ' ' . __( 'They wrote it, so they can always see it.', 'members' );
			} elseif ( user_can( $user, 'restrict_content' ) || ( $type && user_can( $user, $type->cap->edit_post, $post->ID ) ) ) {
				$why = $where . ' ' . __( 'They can edit it (or manage content permissions), so they can always see it.', 'members' );
			} elseif ( $can ) {
				/* translators: %s: roles. */
				$why = $where . ' ' . sprintf( __( 'They have one of the allowed roles (%s).', 'members' ), $allowed );
			} else {
				/* translators: %s: roles. */
				$why = $where . ' ' . sprintf( __( 'Only these roles can see it: %s. They don\'t have any of them.', 'members' ), $allowed );
			}
		}

		return array(
			'post'        => Ability::post_summary( $post ),
			'can_view'    => $can,
			'why'         => $why,
			'restriction' => $restriction,
		);
	}

	/**
	 * Summary row for a user.
	 *
	 * @param \WP_User $user User.
	 * @return array
	 */
	public static function user_row( \WP_User $user ) {
		return array(
			'id'           => $user->ID,
			'username'     => $user->user_login,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'roles'        => array_values( (array) $user->roles ),
		);
	}
}
