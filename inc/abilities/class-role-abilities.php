<?php
/**
 * Role and capability abilities.
 *
 * @package    Members
 * @subpackage Abilities
 * @since      3.3.0
 */
namespace Members\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * View roles and capabilities; create, clone, change and delete roles.
 *
 * @since 3.3.0
 */
final class Role_Abilities {

	/**
	 * Caps that hand out real power. Granting one to a role that has users asks "are you sure?".
	 */
	const POWERFUL_CAPS = array(
		'manage_options',
		'promote_users',
		'edit_users',
		'create_users',
		'delete_users',
		'list_roles',
		'create_roles',
		'edit_roles',
		'delete_roles',
		'restrict_content',
		'install_plugins',
		'activate_plugins',
		'edit_plugins',
		'delete_plugins',
		'update_plugins',
		'install_themes',
		'switch_themes',
		'edit_themes',
		'edit_files',
		'unfiltered_html',
		'update_core',
		'import',
		'export',
	);

	/**
	 * Ability definitions keyed by name (without the prefix).
	 *
	 * @return array
	 */
	public static function definitions() {
		$group  = __( 'Members', 'members' );
		$view   = array( 'view-roles', __( 'View roles & capabilities', 'members' ) );
		$manage = array( 'manage-roles', __( 'Manage roles', 'members' ) );
		$role   = Ability::role_property( __( 'Role slug, e.g. "editor". Use list-roles to see them.', 'members' ) );

		return array(
			'list-roles'        => Ability::finish(
				Ability::args(
					__( 'List roles', 'members' ),
					__( 'List every role on the site with its slug, name, number of users, and how many capabilities it grants and denies.', 'members' ),
					Ability::object_schema( array() ),
					array( __CLASS__, 'list_roles' ),
					true,
					Ability::can( 'list_roles' ),
					$group
				),
				$view[0],
				$view[1],
				array(
					__( 'What roles does my site have?', 'members' ),
					__( 'Which roles have no users?', 'members' ),
				)
			),
			'get-role'          => Ability::finish(
				Ability::args(
					__( 'Get role', 'members' ),
					__( 'Get one role: its name, user count, and the capabilities it grants and explicitly denies.', 'members' ),
					Ability::object_schema( array( 'role' => $role ), array( 'role' ) ),
					array( __CLASS__, 'get_role' ),
					true,
					Ability::can( 'list_roles' ),
					$group
				),
				$view[0],
				$view[1],
				array( __( 'What can Editors do?', 'members' ) )
			),
			'list-capabilities' => Ability::finish(
				Ability::args(
					__( 'List capabilities', 'members' ),
					__( 'List every capability, grouped the way the Members role editor groups them (General, Posts, Pages, Users, plugins…), with human-readable labels and which roles grant each one.', 'members' ),
					Ability::object_schema(
						array(
							'group' => array(
								'type'        => 'string',
								'description' => __( 'Only this group, e.g. "general", "type-post", "user". Omit for all.', 'members' ),
							),
						)
					),
					array( __CLASS__, 'list_capabilities' ),
					true,
					Ability::can( 'list_roles' ),
					$group
				),
				$view[0],
				$view[1],
				array( __( 'Which roles can publish pages?', 'members' ) )
			),
			'create-role'       => Ability::finish(
				Ability::args(
					__( 'Create role', 'members' ),
					__( 'Create a new role, optionally granting and denying capabilities. Use clone-role to start from an existing role.', 'members' ),
					Ability::object_schema(
						array(
							'name'  => array(
								'type'        => 'string',
								'description' => __( 'Role name shown in the admin, e.g. "Course Manager".', 'members' ),
							),
							'role'  => Ability::role_property( __( 'Role slug. Made from the name when left out.', 'members' ) ),
							'grant' => Ability::caps_property( __( 'Capabilities to grant.', 'members' ) ),
							'deny'  => Ability::caps_property( __( 'Capabilities to explicitly deny.', 'members' ) ),
						),
						array( 'name' )
					),
					array( __CLASS__, 'create_role' ),
					false,
					Ability::can( 'create_roles' ),
					$group
				),
				$manage[0],
				$manage[1],
				array( __( 'Create a "Course Manager" role that can edit pages but not publish them.', 'members' ) )
			),
			'clone-role'        => Ability::finish(
				Ability::args(
					__( 'Clone role', 'members' ),
					__( 'Create a new role with the same capabilities as an existing one.', 'members' ),
					Ability::object_schema(
						array(
							'from' => Ability::role_property( __( 'Role to copy.', 'members' ) ),
							'name' => array(
								'type'        => 'string',
								'description' => __( 'New role name.', 'members' ),
							),
							'role' => Ability::role_property( __( 'New role slug. Made from the name when left out.', 'members' ) ),
						),
						array( 'from', 'name' )
					),
					array( __CLASS__, 'clone_role' ),
					false,
					Ability::can( 'create_roles' ),
					$group
				),
				$manage[0],
				$manage[1],
				array( __( 'Copy the Editor role as "Senior Editor".', 'members' ) )
			),
			'update-role-caps'  => Ability::finish(
				Ability::args(
					__( 'Change role capabilities', 'members' ),
					__( 'Grant, deny or remove capabilities on a role, and optionally rename it. "remove" clears a capability so it is neither granted nor denied. Refuses changes that would lock you out of the site.', 'members' ),
					Ability::object_schema(
						array(
							'role'   => $role,
							'grant'  => Ability::caps_property( __( 'Capabilities to grant.', 'members' ) ),
							'deny'   => Ability::caps_property( __( 'Capabilities to explicitly deny.', 'members' ) ),
							'remove' => Ability::caps_property( __( 'Capabilities to clear.', 'members' ) ),
							'name'   => array(
								'type'        => 'string',
								'description' => __( 'New role name. Omit to keep it.', 'members' ),
							),
						),
						array( 'role' )
					),
					array( __CLASS__, 'update_role_caps' ),
					false,
					static function ( $input ) {
						return current_user_can( 'edit_roles' ) && isset( $input['role'] ) && members_is_role_editable( (string) $input['role'] );
					},
					$group,
					true
				),
				$manage[0],
				$manage[1],
				array(
					__( 'Let Authors edit other people\'s posts.', 'members' ),
					__( 'Stop Contributors from deleting their posts.', 'members' ),
				)
			),
			'delete-role'       => Ability::finish(
				Ability::args(
					__( 'Delete role', 'members' ),
					__( 'Permanently delete a role. Users who only had this role are moved to the site\'s default role. The default role can\'t be deleted.', 'members' ),
					Ability::object_schema( array( 'role' => $role ), array( 'role' ) ),
					array( __CLASS__, 'delete_role' ),
					false,
					static function ( $input ) {
						return current_user_can( 'delete_roles' ) && isset( $input['role'] ) && members_is_role_editable( (string) $input['role'] );
					},
					$group,
					true
				),
				$manage[0],
				$manage[1],
				array( __( 'Delete the old "Beta Tester" role.', 'members' ) )
			),
		);
	}

	/**
	 * List roles.
	 *
	 * @return array
	 */
	public static function list_roles() {
		$roles = array();

		foreach ( wp_roles()->role_objects as $slug => $role_obj ) {
			$roles[] = self::role_row( $slug );
		}

		return array(
			'roles'        => $roles,
			'default_role' => get_option( 'default_role' ),
			'multi_roles'  => members_multiple_user_roles_enabled(),
		);
	}

	/**
	 * Get one role.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function get_role( array $input ) {
		$role = (string) $input['role'];

		if ( ! get_role( $role ) ) {
			return Ability::unknown_roles_error( array( $role ) );
		}

		$row = self::role_row( $role );
		$caps = self::role_caps( $role );

		$row['granted'] = array_keys( $caps, true, true );
		$row['denied']  = array_keys( $caps, false, true );

		return $row;
	}

	/**
	 * List capabilities by group.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public static function list_capabilities( array $input ) {
		$only   = isset( $input['group'] ) ? (string) $input['group'] : '';
		$roles  = array_keys( wp_roles()->role_names );
		$groups = array();

		foreach ( members_get_cap_groups() as $group ) {
			if ( ( $only && $group->name !== $only ) || ( ! $only && 'all' === $group->name ) ) {
				continue;
			}

			$caps = array();

			foreach ( members_remove_hidden_caps( (array) $group->caps ) as $cap ) {
				$cap_obj = members_get_cap( $cap );
				$granted = array();

				foreach ( $roles as $role ) {
					$role_obj = get_role( $role );
					if ( $role_obj && ! empty( $role_obj->capabilities[ $cap ] ) ) {
						$granted[] = $role;
					}
				}

				$caps[] = array(
					'cap'       => $cap,
					'label'     => $cap_obj && $cap_obj->label ? $cap_obj->label : $cap,
					'roles'     => $granted,
				);
			}

			if ( $caps ) {
				$groups[] = array(
					'group' => $group->name,
					'label' => $group->label,
					'caps'  => $caps,
				);
			}
		}

		return array( 'groups' => $groups );
	}

	/**
	 * Create a role.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function create_role( array $input ) {
		$caps = array();

		foreach ( isset( $input['grant'] ) ? (array) $input['grant'] : array() as $cap ) {
			$caps[ (string) $cap ] = true;
		}

		foreach ( isset( $input['deny'] ) ? (array) $input['deny'] : array() as $cap ) {
			$caps[ (string) $cap ] = false;
		}

		$role = members_create_role( isset( $input['role'] ) ? (string) $input['role'] : '', (string) $input['name'], $caps );

		if ( is_wp_error( $role ) ) {
			return $role;
		}

		$result            = self::get_role( array( 'role' => $role ) );
		$result['summary'] = sprintf(
			/* translators: %s: role name. */
			__( 'Created the %s role.', 'members' ),
			(string) $input['name']
		);

		return $result;
	}

	/**
	 * Clone a role.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function clone_role( array $input ) {
		$from = (string) $input['from'];

		if ( ! get_role( $from ) ) {
			return Ability::unknown_roles_error( array( $from ) );
		}

		// Raw caps, so old user levels come along too (members_create_role() drops hidden caps).
		$role = members_create_role( isset( $input['role'] ) ? (string) $input['role'] : '', (string) $input['name'], (array) get_role( $from )->capabilities );

		if ( is_wp_error( $role ) ) {
			return $role;
		}

		$result            = self::get_role( array( 'role' => $role ) );
		$result['summary'] = sprintf(
			/* translators: 1: new role name, 2: copied role name. */
			__( 'Created %1$s as a copy of %2$s.', 'members' ),
			(string) $input['name'],
			Ability::role_label( $from )
		);

		return $result;
	}

	/**
	 * Grant, deny or remove capabilities.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function update_role_caps( array $input ) {
		$role = (string) $input['role'];

		if ( ! get_role( $role ) ) {
			return Ability::unknown_roles_error( array( $role ) );
		}

		$grant  = array_values( array_filter( array_map( 'members_sanitize_cap', isset( $input['grant'] ) ? (array) $input['grant'] : array() ) ) );
		$deny   = array_values( array_filter( array_map( 'members_sanitize_cap', isset( $input['deny'] ) ? (array) $input['deny'] : array() ) ) );
		$remove = array_values( array_filter( array_map( 'members_sanitize_cap', isset( $input['remove'] ) ? (array) $input['remove'] : array() ) ) );
		$name   = isset( $input['name'] ) ? trim( wp_strip_all_tags( (string) $input['name'] ) ) : '';

		if ( ! $grant && ! $deny && ! $remove && '' === $name ) {
			return new \WP_Error( 'members_nothing_to_change', __( 'Nothing to change: pass grant, deny, remove and/or name.', 'members' ) );
		}

		$before = self::role_caps( $role );
		$after  = array_map( 'boolval', (array) get_role( $role )->capabilities );

		// Same precedence as members_update_role_caps(): remove, then deny, then grant.
		foreach ( array_diff( $grant, $deny, $remove ) as $cap ) {
			$after[ $cap ] = true;
		}
		foreach ( array_diff( $deny, $remove ) as $cap ) {
			$after[ $cap ] = false;
		}
		foreach ( $remove as $cap ) {
			unset( $after[ $cap ] );
		}

		$raw = array_map( 'boolval', (array) get_role( $role )->capabilities );
		ksort( $raw );
		ksort( $after );

		if ( $after === $raw && ( '' === $name || Ability::role_label( $role ) === $name ) ) {
			return new \WP_Error( 'members_nothing_to_change', __( 'Nothing to change: the role already looks like that.', 'members' ) );
		}

		$lockout = Lockout::check( array( 'role_caps' => array( $role => $after ) ) );
		if ( $lockout ) {
			return $lockout;
		}

		$users     = members_get_role_user_count( $role );
		$taken     = array_values( array_merge( array_intersect( $deny, array_keys( $before, true, true ) ), array_intersect( $remove, array_keys( $before, true, true ) ) ) );
		$powerful  = array_values( array_intersect( $grant, self::POWERFUL_CAPS ) );
		$reasons   = array();

		if ( 'administrator' === $role ) {
			$reasons[] = __( 'This is the Administrator role, usually the most important role on the site.', 'members' );
		}
		if ( $users && $taken ) {
			/* translators: 1: number of users, 2: capability list. */
			$reasons[] = sprintf( _n( '%1$d user with this role will lose: %2$s.', '%1$d users with this role will lose: %2$s.', $users, 'members' ), $users, implode( ', ', $taken ) );
		}
		if ( $users && $powerful ) {
			/* translators: 1: number of users, 2: capability list. */
			$reasons[] = sprintf( _n( '%1$d user with this role will gain powerful capabilities: %2$s.', '%1$d users with this role will gain powerful capabilities: %2$s.', $users, 'members' ), $users, implode( ', ', $powerful ) );
		}

		if ( $reasons ) {
			$ask = Confirmation::check(
				Ability::PREFIX . 'update-role-caps',
				$input,
				implode( ' ', $reasons ),
				array(
					'role'   => $role,
					'users'  => $users,
					'grant'  => $grant,
					'deny'   => $deny,
					'remove' => $remove,
				)
			);
			if ( $ask ) {
				return $ask;
			}
		}

		$updated = members_update_role_caps( $role, $grant, $deny, $remove, $name );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$result             = self::get_role( array( 'role' => $role ) );
		$result['summary']  = sprintf(
			/* translators: %s: role name. */
			__( 'Updated the %s role.', 'members' ),
			Ability::role_label( $role )
		);
		$result['previous'] = array(
			'granted' => array_keys( $before, true, true ),
			'denied'  => array_keys( $before, false, true ),
		);

		return $result;
	}

	/**
	 * Delete a role.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function delete_role( array $input ) {
		$role = (string) $input['role'];

		if ( ! get_role( $role ) ) {
			return Ability::unknown_roles_error( array( $role ) );
		}

		$default = get_option( 'default_role' );

		if ( $role === $default ) {
			return new \WP_Error( 'members_default_role', __( 'This is the site\'s default role for new users, so it can\'t be deleted. Change the default role in Settings > General first.', 'members' ) );
		}

		$lockout = Lockout::check( array( 'deleted_roles' => array( $role ) ) );
		if ( $lockout ) {
			return $lockout;
		}

		$users = members_get_role_user_count( $role );
		$label = Ability::role_label( $role );
		$ask   = Confirmation::check(
			Ability::PREFIX . 'delete-role',
			$input,
			sprintf(
				/* translators: 1: role name, 2: number of users, 3: default role name. */
				_n( 'This permanently deletes the %1$s role and can\'t be undone. %2$d user has it; anyone left with no role moves to %3$s.', 'This permanently deletes the %1$s role and can\'t be undone. %2$d users have it; anyone left with no role moves to %3$s.', $users, 'members' ),
				$label,
				$users,
				Ability::role_label( $default )
			),
			array(
				'role'  => $role,
				'users' => $users,
			)
		);
		if ( $ask ) {
			return $ask;
		}

		$previous = self::role_caps( $role );

		members_delete_role( $role );

		return array(
			'deleted'  => $role,
			'summary'  => sprintf(
				/* translators: %s: role name. */
				__( 'Deleted the %s role.', 'members' ),
				$label
			),
			'previous' => array(
				'name' => $label,
				'caps' => $previous,
			),
		);
	}

	/**
	 * Summary row for a role.
	 *
	 * @param string $role Role slug.
	 * @return array
	 */
	private static function role_row( $role ) {
		$caps = self::role_caps( $role );

		return array(
			'role'         => $role,
			'name'         => Ability::role_label( $role ),
			'users'        => (int) members_get_role_user_count( $role ),
			'granted_caps' => count( array_keys( $caps, true, true ) ),
			'denied_caps'  => count( array_keys( $caps, false, true ) ),
			'editable'     => members_is_role_editable( $role ),
			'core_role'    => members_is_wordpress_role( $role ),
		);
	}

	/**
	 * A role's capabilities, without old user levels or hidden caps.
	 *
	 * @param string $role Role slug.
	 * @return array cap => bool
	 */
	private static function role_caps( $role ) {
		$role_obj = get_role( $role );

		if ( ! $role_obj ) {
			return array();
		}

		$caps = array();

		foreach ( (array) $role_obj->capabilities as $cap => $grant ) {
			$caps[ $cap ] = (bool) $grant;
		}

		$keep = members_remove_hidden_caps( members_remove_old_levels( array_keys( $caps ) ) );

		return array_intersect_key( $caps, array_flip( $keep ) );
	}
}
