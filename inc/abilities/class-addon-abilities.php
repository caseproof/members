<?php
/**
 * Abilities for the add-ons that ship with Members.
 *
 * @package    Members
 * @subpackage Abilities
 * @since      3.3.0
 */
namespace Members\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * One switch per add-on, listed under that add-on's name on the AI (MCP) tab. Only registered
 * while the add-on is on. Add-ons that only add capabilities (Privacy Caps, Category & Tag
 * Caps, Core Create Caps, the plugin integrations) need none: their caps already show up in
 * list-capabilities.
 *
 * @since 3.3.0
 */
final class Addon_Abilities {

	/**
	 * Ability definitions for the add-ons that are on.
	 *
	 * @return array
	 */
	public static function definitions() {
		$active = (array) get_option( 'members_active_addons', array() );
		$out    = array();

		if ( in_array( 'members-admin-access', $active, true ) && function_exists( 'Members\AddOns\AdminAccess\get_setting' ) ) {
			$out = array_merge( $out, self::admin_access() );
		}

		if ( in_array( 'members-role-hierarchy', $active, true ) && function_exists( 'mrh_get_role_positions' ) ) {
			$out = array_merge( $out, self::role_hierarchy() );
		}

		if ( in_array( 'members-role-levels', $active, true ) && self::load_role_levels() ) {
			$out = array_merge( $out, self::role_levels() );
		}

		if ( in_array( 'members-block-permissions', $active, true ) ) {
			$out = array_merge( $out, self::block_permissions() );
		}

		return $out;
	}

	/*
	 * Admin Access.
	 */

	/**
	 * Admin Access abilities.
	 *
	 * @return array
	 */
	private static function admin_access() {
		$group  = __( 'Members - Admin Access', 'members' );
		$family = array( 'admin-access', __( 'Admin access settings', 'members' ) );

		return array(
			'get-admin-access'    => Ability::finish(
				Ability::args(
					__( 'Get admin access', 'members' ),
					__( 'Which roles can get into wp-admin, where everyone else is sent, and whether the toolbar is hidden for them. Administrators always have access.', 'members' ),
					Ability::object_schema( array() ),
					array( __CLASS__, 'get_admin_access' ),
					true,
					Ability::can( Ability::settings_cap() ),
					$group
				),
				$family[0],
				$family[1],
				array( __( 'Who can get into wp-admin?', 'members' ) )
			),
			'update-admin-access' => Ability::finish(
				Ability::args(
					__( 'Update admin access', 'members' ),
					__( 'Change which roles can get into wp-admin, where blocked users are sent, and whether the toolbar is hidden for them. Refuses changes that would lock you out.', 'members' ),
					Ability::object_schema(
						array(
							'roles'           => Ability::roles_property( __( 'Exact roles that may get into wp-admin (administrators always can).', 'members' ) ),
							'redirect_url'    => array(
								'type'        => 'string',
								'description' => __( 'Where blocked users are sent. Defaults to the home page.', 'members' ),
							),
							'disable_toolbar' => array(
								'type'        => 'boolean',
								'description' => __( 'Hide the toolbar for users without access.', 'members' ),
							),
						)
					),
					array( __CLASS__, 'update_admin_access' ),
					false,
					Ability::can( Ability::settings_cap() ),
					$group,
					true
				),
				$family[0],
				$family[1],
				array( __( 'Keep Subscribers out of wp-admin.', 'members' ) )
			),
		);
	}

	/**
	 * Current Admin Access settings.
	 *
	 * @return array
	 */
	public static function get_admin_access() {
		return array(
			'roles'           => array_values( (array) \Members\AddOns\AdminAccess\get_setting( 'roles' ) ),
			'always_allowed'  => array_values( (array) \Members\AddOns\AdminAccess\get_roles_with_permanent_access() ),
			'redirect_url'    => (string) \Members\AddOns\AdminAccess\get_setting( 'redirect_url' ),
			'disable_toolbar' => (bool) \Members\AddOns\AdminAccess\get_setting( 'disable_toolbar' ),
		);
	}

	/**
	 * Change Admin Access settings.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function update_admin_access( array $input ) {
		$previous = self::get_admin_access();
		$settings = array(
			'roles'           => $previous['roles'],
			'redirect_url'    => $previous['redirect_url'],
			'disable_toolbar' => $previous['disable_toolbar'],
		);

		if ( isset( $input['roles'] ) ) {
			$roles = members_sanitize_access_role_meta_list( (array) $input['roles'] );
			$error = Ability::unknown_roles_error( $roles );
			if ( $error ) {
				return $error;
			}
			$settings['roles'] = $roles;
		}

		if ( isset( $input['redirect_url'] ) ) {
			$settings['redirect_url'] = esc_url_raw( (string) $input['redirect_url'] );
			if ( ! $settings['redirect_url'] ) {
				$settings['redirect_url'] = esc_url_raw( home_url() );
			}
		}

		if ( isset( $input['disable_toolbar'] ) ) {
			$settings['disable_toolbar'] = (bool) $input['disable_toolbar'];
		}

		if ( $settings === array_intersect_key( $previous, $settings ) ) {
			return new \WP_Error( 'members_nothing_to_change', __( 'Nothing to change.', 'members' ) );
		}

		$lockout = Lockout::check( array( 'admin_access' => $settings['roles'] ) );
		if ( $lockout ) {
			return $lockout;
		}

		$losing = array_diff( $previous['roles'], $settings['roles'], $previous['always_allowed'] );
		$ask    = Confirmation::check(
			Ability::PREFIX . 'update-admin-access',
			$input,
			$losing
				/* translators: %s: roles. */
				? sprintf( __( 'These roles will be kept out of wp-admin: %s.', 'members' ), implode( ', ', array_map( array( Ability::class, 'role_label' ), $losing ) ) )
				: __( 'This changes who can get into wp-admin and what blocked users see.', 'members' ),
			array(
				'before' => $previous,
				'after'  => $settings,
			)
		);
		if ( $ask ) {
			return $ask;
		}

		// Same filter the add-on's settings screen runs on save.
		update_option( 'members_admin_access_settings', apply_filters( \Members\AddOns\AdminAccess\app()->namespace . '/validate_settings', $settings ) );

		$result             = self::get_admin_access();
		$result['summary']  = __( 'Updated admin access.', 'members' );
		$result['previous'] = $previous;

		return $result;
	}

	/*
	 * Role Hierarchy.
	 */

	/**
	 * Role Hierarchy abilities.
	 *
	 * @return array
	 */
	private static function role_hierarchy() {
		$group  = __( 'Members - Role Hierarchy', 'members' );
		$family = array( 'role-hierarchy', __( 'Role positions', 'members' ) );

		return array(
			'get-role-hierarchy' => Ability::finish(
				Ability::args(
					__( 'Get role hierarchy', 'members' ),
					__( 'Every role\'s position. Users can only edit and assign roles below their own position (the highest roles can edit their own level too).', 'members' ),
					Ability::object_schema( array() ),
					array( __CLASS__, 'get_role_hierarchy' ),
					true,
					Ability::can( 'list_roles' ),
					$group
				),
				$family[0],
				$family[1],
				array( __( 'How are my roles ranked?', 'members' ) )
			),
			'set-role-position'  => Ability::finish(
				Ability::args(
					__( 'Set role position', 'members' ),
					__( 'Move a role up or down the hierarchy by setting its position number (higher is more senior; Administrator is 100 by default).', 'members' ),
					Ability::object_schema(
						array(
							'role'     => Ability::role_property( __( 'Role slug.', 'members' ) ),
							'position' => array(
								'type'    => 'integer',
								'minimum' => 0,
							),
						),
						array( 'role', 'position' )
					),
					array( __CLASS__, 'set_role_position' ),
					false,
					static function ( $input ) {
						return current_user_can( 'edit_roles' ) && isset( $input['role'] ) && members_is_role_editable( (string) $input['role'] );
					},
					$group,
					true
				),
				$family[0],
				$family[1],
				array( __( 'Put Shop Manager just below Editor.', 'members' ) )
			),
		);
	}

	/**
	 * Role positions, highest first.
	 *
	 * @return array
	 */
	public static function get_role_hierarchy() {
		$roles = array();

		foreach ( array_keys( wp_roles()->role_names ) as $role ) {
			$roles[] = array(
				'role'     => $role,
				'name'     => Ability::role_label( $role ),
				'position' => (int) mrh_get_role_position( $role ),
			);
		}

		usort(
			$roles,
			static function ( $a, $b ) {
				return $b['position'] - $a['position'];
			}
		);

		return array( 'roles' => $roles );
	}

	/**
	 * Set a role's position.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function set_role_position( array $input ) {
		$role = (string) $input['role'];

		$error = Ability::unknown_roles_error( array( $role ) );
		if ( $error ) {
			return $error;
		}

		$old = (int) mrh_get_role_position( $role );
		$new = (int) $input['position'];

		if ( $old === $new ) {
			return new \WP_Error( 'members_nothing_to_change', __( 'That role is already at that position.', 'members' ) );
		}

		$ask = Confirmation::check(
			Ability::PREFIX . 'set-role-position',
			$input,
			sprintf(
				/* translators: 1: role name, 2: old position, 3: new position. */
				__( 'Moving %1$s from %2$d to %3$d changes which roles its users can edit and assign, and who can edit them.', 'members' ),
				Ability::role_label( $role ),
				$old,
				$new
			),
			array(
				'role'   => $role,
				'before' => $old,
				'after'  => $new,
			)
		);
		if ( $ask ) {
			return $ask;
		}

		mrh_set_role_position( $role, $new );

		$result             = self::get_role_hierarchy();
		$result['summary']  = sprintf(
			/* translators: 1: role name, 2: position. */
			__( 'Moved %1$s to position %2$d.', 'members' ),
			Ability::role_label( $role ),
			$new
		);
		$result['previous'] = array( 'position' => $old );

		return $result;
	}

	/*
	 * Role Levels.
	 */

	/**
	 * Load the Role Levels helpers (only loaded in wp-admin by the add-on).
	 *
	 * @return bool
	 */
	private static function load_role_levels() {
		if ( ! function_exists( 'mrl_set_role_level' ) ) {
			$file = members_plugin()->dir . 'addons/members-role-levels/admin/functions-helpers.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}

		return function_exists( 'mrl_set_role_level' );
	}

	/**
	 * Role Levels abilities.
	 *
	 * @return array
	 */
	private static function role_levels() {
		$group  = __( 'Members - Role Levels', 'members' );
		$family = array( 'role-levels', __( 'Role levels', 'members' ) );
		$role   = Ability::role_property( __( 'Role slug.', 'members' ) );

		return array(
			'get-role-level' => Ability::finish(
				Ability::args(
					__( 'Get role level', 'members' ),
					__( 'A role\'s old-style WordPress user level (0-10). Some older plugins and the post author drop-down still use these.', 'members' ),
					Ability::object_schema( array( 'role' => $role ), array( 'role' ) ),
					array( __CLASS__, 'get_role_level' ),
					true,
					Ability::can( 'list_roles' ),
					$group
				),
				$family[0],
				$family[1],
				array( __( 'What user level does Author have?', 'members' ) )
			),
			'set-role-level' => Ability::finish(
				Ability::args(
					__( 'Set role level', 'members' ),
					__( 'Set a role\'s old-style WordPress user level (0-10). Updates every user with the role.', 'members' ),
					Ability::object_schema(
						array(
							'role'  => $role,
							'level' => array(
								'type'    => 'integer',
								'minimum' => 0,
								'maximum' => 10,
							),
						),
						array( 'role', 'level' )
					),
					array( __CLASS__, 'set_role_level' ),
					false,
					static function ( $input ) {
						return current_user_can( 'edit_roles' ) && isset( $input['role'] ) && members_is_role_editable( (string) $input['role'] );
					},
					$group
				),
				$family[0],
				$family[1],
				array( __( 'Give Course Manager level 2 so they show in the author list.', 'members' ) )
			),
		);
	}

	/**
	 * A role's level.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function get_role_level( array $input ) {
		$role  = (string) $input['role'];
		$error = Ability::unknown_roles_error( array( $role ) );
		if ( $error ) {
			return $error;
		}

		$level = mrl_get_role_level( $role );

		return array(
			'role'  => $role,
			'level' => '' === $level ? 0 : (int) mrl_remove_level_prefix( $level ),
		);
	}

	/**
	 * Set a role's level.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function set_role_level( array $input ) {
		$previous = self::get_role_level( $input );
		if ( is_wp_error( $previous ) ) {
			return $previous;
		}

		$role  = (string) $input['role'];
		$level = (int) $input['level'];

		mrl_set_role_level( $role, mrl_add_level_prefix( $level ) );

		$result             = self::get_role_level( $input );
		$result['summary']  = sprintf(
			/* translators: 1: role name, 2: level. */
			__( 'Set %1$s to level %2$d.', 'members' ),
			Ability::role_label( $role ),
			$level
		);
		$result['previous'] = array( 'level' => $previous['level'] );

		return $result;
	}

	/*
	 * Block Permissions.
	 */

	/**
	 * Block Permissions abilities.
	 *
	 * @return array
	 */
	private static function block_permissions() {
		$group  = __( 'Members - Block Permissions', 'members' );
		$family = array( 'block-permissions', __( 'Block permissions', 'members' ) );
		$can    = static function ( $input ) {
			return current_user_can( 'assign_block_permissions' ) && isset( $input['post_id'] ) && current_user_can( 'edit_post', (int) $input['post_id'] );
		};

		return array(
			'get-block-restrictions' => Ability::finish(
				Ability::args(
					__( 'Get block restrictions', 'members' ),
					__( 'List the blocks in a post with their path (e.g. "2" or "2.0" for a nested block), a short text preview, and who each block is shown to or hidden from.', 'members' ),
					Ability::object_schema( array( 'post_id' => Ability::post_property() ), array( 'post_id' ) ),
					array( __CLASS__, 'get_block_restrictions' ),
					true,
					$can,
					$group
				),
				$family[0],
				$family[1],
				array( __( 'Which blocks on the Pricing page are hidden from anyone?', 'members' ) )
			),
			'restrict-block'         => Ability::finish(
				Ability::args(
					__( 'Restrict block', 'members' ),
					__( 'Show a single block only to, or hide it from, logged-in/out users, certain roles, or users with a capability. Use condition "everyone" to remove a block\'s restriction. Saves a revision.', 'members' ),
					Ability::object_schema(
						array(
							'post_id'     => Ability::post_property(),
							'block'       => array(
								'type'        => 'string',
								'description' => __( 'Block path from get-block-restrictions, e.g. "3" or "3.1".', 'members' ),
							),
							'condition'   => array(
								'type'        => 'string',
								'enum'        => array( 'show', 'hide', 'everyone' ),
								'description' => __( '"show" = only show to the selected users, "hide" = hide from them, "everyone" = remove the restriction.', 'members' ),
							),
							'type'        => array(
								'type' => 'string',
								'enum' => array( 'user-status', 'role', 'cap' ),
							),
							'user_status' => array(
								'type' => 'string',
								'enum' => array( 'logged-in', 'logged-out' ),
							),
							'roles'       => Ability::roles_property( __( 'Roles, for type "role".', 'members' ) ),
							'cap'         => array(
								'type'        => 'string',
								'description' => __( 'Capability, for type "cap".', 'members' ),
							),
							'message'     => array(
								'type'        => 'string',
								'description' => __( 'Optional message shown instead of the block.', 'members' ),
							),
						),
						array( 'post_id', 'block', 'condition' )
					),
					array( __CLASS__, 'restrict_block' ),
					false,
					$can,
					$group
				),
				$family[0],
				$family[1],
				array( __( 'Hide the discount code block on my Pricing page from logged-out visitors.', 'members' ) )
			),
		);
	}

	/**
	 * Blocks in a post and their restrictions.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function get_block_restrictions( array $input ) {
		$post = get_post( (int) $input['post_id'] );
		if ( ! $post ) {
			return new \WP_Error( 'members_no_post', __( 'No post found with that ID.', 'members' ) );
		}

		if ( ! has_blocks( $post->post_content ) ) {
			return new \WP_Error( 'members_no_blocks', __( 'This post doesn\'t use blocks.', 'members' ) );
		}

		$rows = array();
		self::walk_blocks( parse_blocks( $post->post_content ), '', $rows );

		return array(
			'post'   => Ability::post_summary( $post ),
			'blocks' => $rows,
		);
	}

	/**
	 * Restrict one block.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function restrict_block( array $input ) {
		$post = get_post( (int) $input['post_id'] );
		if ( ! $post || ! has_blocks( $post->post_content ) ) {
			return new \WP_Error( 'members_no_blocks', __( 'No block post found with that ID.', 'members' ) );
		}

		$blocks = parse_blocks( $post->post_content );
		$path   = array_map( 'intval', explode( '.', (string) $input['block'] ) );
		$block  = &self::find_block( $blocks, $path );

		if ( null === $block || empty( $block['blockName'] ) ) {
			return new \WP_Error( 'members_no_block', __( 'No block at that path. Use get-block-restrictions to see the paths.', 'members' ) );
		}

		$attrs    = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$previous = self::block_permissions_of( $attrs );

		foreach ( array( 'blockPermissionsCondition', 'blockPermissionsType', 'blockPermissionsUserStatus', 'blockPermissionsRoles', 'blockPermissionsCap', 'blockPermissionsMessage' ) as $key ) {
			unset( $attrs[ $key ] );
		}

		if ( 'everyone' !== $input['condition'] ) {
			$type = isset( $input['type'] ) ? (string) $input['type'] : '';

			// Stored the way the block editor control saves them: "=" shows, "!=" hides.
			$attrs['blockPermissionsCondition'] = 'show' === $input['condition'] ? '=' : '!=';
			$attrs['blockPermissionsType']      = $type;

			if ( 'user-status' === $type ) {
				if ( empty( $input['user_status'] ) ) {
					return new \WP_Error( 'members_missing_user_status', __( 'Pass user_status ("logged-in" or "logged-out").', 'members' ) );
				}
				$attrs['blockPermissionsUserStatus'] = (string) $input['user_status'];
			} elseif ( 'role' === $type ) {
				$roles = members_sanitize_access_role_meta_list( isset( $input['roles'] ) ? (array) $input['roles'] : array() );
				$error = $roles ? Ability::unknown_roles_error( $roles ) : new \WP_Error( 'members_no_roles', __( 'Pass at least one role.', 'members' ) );
				if ( $error ) {
					return $error;
				}
				$attrs['blockPermissionsRoles'] = $roles;
			} elseif ( 'cap' === $type ) {
				$cap = isset( $input['cap'] ) ? members_sanitize_cap( (string) $input['cap'] ) : '';
				if ( ! $cap ) {
					return new \WP_Error( 'members_missing_cap', __( 'Pass a capability.', 'members' ) );
				}
				$attrs['blockPermissionsCap'] = $cap;
			} else {
				return new \WP_Error( 'members_missing_type', __( 'Pass type: "user-status", "role" or "cap".', 'members' ) );
			}

			if ( ! empty( $input['message'] ) ) {
				$attrs['blockPermissionsMessage'] = wp_kses_post( (string) $input['message'] );
			}
		}

		$block['attrs'] = $attrs;
		unset( $block );

		$saved = wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => wp_slash( serialize_blocks( $blocks ) ),
			),
			true
		);

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$result             = self::get_block_restrictions( $input );
		$result['summary']  = sprintf(
			/* translators: 1: block path, 2: post title. */
			__( 'Updated block %1$s on "%2$s".', 'members' ),
			(string) $input['block'],
			get_the_title( $post )
		);
		$result['previous'] = $previous;

		return $result;
	}

	/**
	 * Flatten blocks into rows.
	 *
	 * @param array  $blocks Parsed blocks.
	 * @param string $prefix Path prefix.
	 * @param array  $rows   Rows, appended to.
	 * @return void
	 */
	private static function walk_blocks( array $blocks, $prefix, array &$rows ) {
		foreach ( $blocks as $i => $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$path   = '' === $prefix ? (string) $i : $prefix . '.' . $i;
			$attrs  = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$rows[] = array(
				'path'        => $path,
				'block'       => $block['blockName'],
				'preview'     => wp_html_excerpt( trim( wp_strip_all_tags( (string) $block['innerHTML'] ) ), 80, '…' ),
				'permissions' => self::block_permissions_of( $attrs ),
			);

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::walk_blocks( $block['innerBlocks'], $path, $rows );
			}
		}
	}

	/**
	 * Block Permissions attributes in plain terms.
	 *
	 * @param array $attrs Block attributes.
	 * @return array|null Null when the block isn't restricted.
	 */
	private static function block_permissions_of( array $attrs ) {
		if ( empty( $attrs['blockPermissionsCondition'] ) || empty( $attrs['blockPermissionsType'] ) ) {
			return null;
		}

		$condition = (string) $attrs['blockPermissionsCondition'];

		return array(
			'condition'   => '=' === $condition ? 'show' : ( '!=' === $condition ? 'hide' : $condition ),
			'type'        => (string) $attrs['blockPermissionsType'],
			'user_status' => isset( $attrs['blockPermissionsUserStatus'] ) ? (string) $attrs['blockPermissionsUserStatus'] : '',
			'roles'       => isset( $attrs['blockPermissionsRoles'] ) ? array_values( (array) $attrs['blockPermissionsRoles'] ) : array(),
			'cap'         => isset( $attrs['blockPermissionsCap'] ) ? (string) $attrs['blockPermissionsCap'] : '',
			'message'     => isset( $attrs['blockPermissionsMessage'] ) ? (string) $attrs['blockPermissionsMessage'] : '',
		);
	}

	/**
	 * Reference to the block at a path, skipping freeform whitespace "blocks" the way
	 * walk_blocks() numbers them (by array index).
	 *
	 * @param array $blocks Blocks.
	 * @param int[] $path   Indexes.
	 * @return array|null
	 */
	private static function &find_block( array &$blocks, array $path ) {
		$null  = null;
		$index = array_shift( $path );

		if ( ! isset( $blocks[ $index ] ) ) {
			return $null;
		}

		if ( ! $path ) {
			return $blocks[ $index ];
		}

		if ( empty( $blocks[ $index ]['innerBlocks'] ) ) {
			return $null;
		}

		return self::find_block( $blocks[ $index ]['innerBlocks'], $path );
	}
}
