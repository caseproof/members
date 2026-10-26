<?php
/**
 * Content restriction abilities.
 *
 * @package    Members
 * @subpackage Abilities
 * @since      3.3.0
 */
namespace Members\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * See and change which roles can view posts, pages and custom post types (Members' Content
 * Permissions). Writes go through the same helpers as the editor's Content Permissions box.
 *
 * @since 3.3.0
 */
final class Content_Abilities {

	/**
	 * Most posts one bulk call can change.
	 */
	const BULK_MAX = 50;

	/**
	 * Ability definitions keyed by name (without the prefix).
	 *
	 * @return array
	 */
	public static function definitions() {
		$group   = __( 'Members', 'members' );
		$view    = array( 'view-content', __( 'View content restrictions', 'members' ) );
		$protect = array( 'protect-content', __( 'Protect content', 'members' ) );
		$message = array(
			'type'        => 'string',
			'description' => __( 'Message shown to people who can\'t see it (HTML allowed). Omit to keep the current one; empty uses the default from Members > Settings.', 'members' ),
		);

		return array(
			'get-content-access'      => Ability::finish(
				Ability::args(
					__( 'Get content access', 'members' ),
					__( 'Who can see a post, page or custom post: the roles it\'s restricted to (including a restriction inherited from a parent page) and its no-access message.', 'members' ),
					Ability::object_schema( array( 'post_id' => Ability::post_property() ), array( 'post_id' ) ),
					array( __CLASS__, 'get_content_access' ),
					true,
					static function ( $input ) {
						return isset( $input['post_id'] ) && current_user_can( 'edit_post', (int) $input['post_id'] );
					},
					$group
				),
				$view[0],
				$view[1],
				array( __( 'Who can see my Pricing page?', 'members' ) )
			),
			'list-restricted-content' => Ability::finish(
				Ability::args(
					__( 'List restricted content', 'members' ),
					__( 'List content that\'s restricted to certain roles, optionally only for one role or post type (paged).', 'members' ),
					Ability::object_schema(
						array(
							'role'      => Ability::role_property( __( 'Only content restricted to this role.', 'members' ) ),
							'post_type' => array(
								'type'        => 'string',
								'description' => __( 'Only this post type, e.g. "page".', 'members' ),
							),
							'per_page'  => array(
								'type'    => 'integer',
								'minimum' => 1,
								'maximum' => 100,
							),
							'page'      => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
						)
					),
					array( __CLASS__, 'list_restricted_content' ),
					true,
					Ability::can( 'restrict_content' ),
					$group
				),
				$view[0],
				$view[1],
				array( __( 'What content is only for Subscribers?', 'members' ) )
			),
			'list-restrictable-types' => Ability::finish(
				Ability::args(
					__( 'List restrictable content types', 'members' ),
					__( 'Which content types (posts, pages, custom post types) can be restricted, and whether Content Permissions is turned on.', 'members' ),
					Ability::object_schema( array() ),
					array( __CLASS__, 'list_restrictable_types' ),
					true,
					Ability::can( 'restrict_content' ),
					$group
				),
				$view[0],
				$view[1],
				array( __( 'Can I restrict my products?', 'members' ) )
			),
			'restrict-content'        => Ability::finish(
				Ability::args(
					__( 'Restrict content', 'members' ),
					__( 'Restrict a post, page or custom post so only the given roles (plus its author and editors) can see it, and optionally set its no-access message. Replaces any roles it had.', 'members' ),
					Ability::object_schema(
						array(
							'post_id' => Ability::post_property(),
							'roles'   => Ability::roles_property( __( 'Roles that may see it.', 'members' ) ),
							'message' => $message,
						),
						array( 'post_id', 'roles' )
					),
					array( __CLASS__, 'restrict_content' ),
					false,
					array( __CLASS__, 'can_manage_post' ),
					$group
				),
				$protect[0],
				$protect[1],
				array( __( 'Make the Members Area page visible to Subscribers only.', 'members' ) )
			),
			'unrestrict-content'      => Ability::finish(
				Ability::args(
					__( 'Unrestrict content', 'members' ),
					__( 'Remove a post\'s role restriction (and its custom no-access message), so anyone who can see the site can see it.', 'members' ),
					Ability::object_schema( array( 'post_id' => Ability::post_property() ), array( 'post_id' ) ),
					array( __CLASS__, 'unrestrict_content' ),
					false,
					array( __CLASS__, 'can_manage_post' ),
					$group,
					true
				),
				$protect[0],
				$protect[1],
				array( __( 'Open the Pricing page up to everyone.', 'members' ) )
			),
			'bulk-restrict-content'   => Ability::finish(
				Ability::args(
					__( 'Bulk restrict content', 'members' ),
					sprintf(
						/* translators: %d: maximum posts. */
						__( 'Restrict or unrestrict up to %d posts at once, picked by ID or by a query (post type, category, tag, search). Always shows the list first and asks for a yes. Posts you can\'t edit are skipped.', 'members' ),
						self::BULK_MAX
					),
					Ability::object_schema(
						array(
							'action'    => array(
								'type'        => 'string',
								'enum'        => array( 'restrict', 'unrestrict' ),
								'description' => __( '"restrict" (default) or "unrestrict".', 'members' ),
							),
							'roles'     => Ability::roles_property( __( 'Roles that may see them (for "restrict").', 'members' ) ),
							'message'   => $message,
							'post_ids'  => array(
								'type'  => 'array',
								'items' => array(
									'type'    => 'integer',
									'minimum' => 1,
								),
							),
							'post_type' => array(
								'type'        => 'string',
								'description' => __( 'Post type to pick from, e.g. "post". Defaults to "post" when using category, tag or search.', 'members' ),
							),
							'category'  => array(
								'type'        => 'string',
								'description' => __( 'Category slug.', 'members' ),
							),
							'tag'       => array(
								'type'        => 'string',
								'description' => __( 'Tag slug.', 'members' ),
							),
							'search'    => array(
								'type'        => 'string',
								'description' => __( 'Search text.', 'members' ),
							),
						)
					),
					array( __CLASS__, 'bulk_restrict_content' ),
					false,
					Ability::can( 'restrict_content' ),
					$group,
					true
				),
				$protect[0],
				$protect[1],
				array( __( 'Restrict the posts in my Premium category to Subscribers.', 'members' ) )
			),
		);
	}

	/**
	 * Same rule as the editor's Content Permissions box.
	 *
	 * @param array $input Input.
	 * @return bool
	 */
	public static function can_manage_post( $input ) {
		return isset( $input['post_id'] ) && members_current_user_can_manage_post_content_permissions( (int) $input['post_id'] );
	}

	/**
	 * A post's effective restriction: its own roles, or the nearest restricted ancestor's.
	 *
	 * @param \WP_Post $post Post.
	 * @return array{roles: string[], inherited_from: int, message: string}
	 */
	public static function restriction( \WP_Post $post ) {
		$current = $post;
		$seen    = array();

		while ( $current instanceof \WP_Post && ! isset( $seen[ $current->ID ] ) && count( $seen ) < 100 ) {
			$seen[ $current->ID ] = true;
			$roles                = self::stored_roles( $current->ID );

			if ( $roles ) {
				return array(
					'roles'          => $roles,
					'inherited_from' => $current->ID === $post->ID ? 0 : $current->ID,
					'message'        => (string) members_get_post_access_message( $current->ID ),
				);
			}

			$current = $current->post_parent ? get_post( $current->post_parent ) : null;
		}

		return array(
			'roles'          => array(),
			'inherited_from' => 0,
			'message'        => (string) members_get_post_access_message( $post->ID ),
		);
	}

	/**
	 * Who can see a post.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function get_content_access( array $input ) {
		$post = self::post( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$restriction = self::restriction( $post );

		return array(
			'post'                        => Ability::post_summary( $post ),
			'restricted'                  => (bool) $restriction['roles'],
			'roles'                       => $restriction['roles'],
			'inherited_from'              => $restriction['inherited_from'] ? Ability::post_summary( get_post( $restriction['inherited_from'] ) ) : null,
			'message'                     => $restriction['message'],
			'default_message'             => (string) members_get_setting( 'content_permissions_error' ),
			'content_permissions_enabled' => members_content_permissions_enabled(),
			'post_type_restrictable'      => members_is_content_permissions_enabled_for_post_type( $post->post_type ),
			'note'                        => __( 'The author, anyone who can edit it, and anyone with the restrict_content capability can always see restricted content.', 'members' ),
		);
	}

	/**
	 * Restricted content.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function list_restricted_content( array $input ) {
		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : 20;
		$page     = isset( $input['page'] ) ? (int) $input['page'] : 1;
		$role     = '';

		if ( ! empty( $input['role'] ) ) {
			$role  = (string) $input['role'];
			$error = Ability::unknown_roles_error( array( $role ) );
			if ( $error ) {
				return $error;
			}
		}

		// `_role` is the old-style restriction from Members versions before 1.0.
		$meta = array( 'relation' => 'OR' );
		foreach ( array( '_members_access_role', '_role' ) as $key ) {
			$meta[] = $role
				? array(
					'key'   => $key,
					'value' => $role,
				)
				: array(
					'key'     => $key,
					'compare' => 'EXISTS',
				);
		}

		$query = new \WP_Query(
			array(
				'post_type'           => ! empty( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : members_get_content_permissions_post_types(),
				'post_status'         => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'meta_query'          => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'posts_per_page'      => $per_page,
				'paged'               => $page,
				'orderby'             => 'title',
				'order'               => 'ASC',
				'ignore_sticky_posts' => true,
				'suppress_filters'    => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$row          = Ability::post_summary( $post );
			$row['roles'] = self::stored_roles( $post->ID );
			$items[]      = $row;
		}

		return array(
			'content'     => $items,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'note'        => __( 'Child pages of these inherit their restriction unless they have their own.', 'members' ),
		);
	}

	/**
	 * Restrictable content types.
	 *
	 * @return array
	 */
	public static function list_restrictable_types() {
		$types = array();

		foreach ( members_get_content_permissions_post_types() as $type ) {
			$object  = get_post_type_object( $type );
			$types[] = array(
				'post_type' => $type,
				'label'     => $object ? $object->labels->name : $type,
			);
		}

		return array(
			'content_permissions_enabled' => members_content_permissions_enabled(),
			'post_types'                  => $types,
		);
	}

	/**
	 * Restrict a post.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function restrict_content( array $input ) {
		$post = self::post( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$roles = members_sanitize_access_role_meta_list( (array) $input['roles'] );

		if ( ! $roles ) {
			return new \WP_Error( 'members_no_roles', __( 'Pass at least one role. To remove a restriction, use unrestrict-content.', 'members' ) );
		}

		$error = Ability::unknown_roles_error( $roles );
		if ( $error ) {
			return $error;
		}

		$previous = self::snapshot( $post->ID );

		self::apply( $post->ID, $roles, array_key_exists( 'message', $input ) ? (string) $input['message'] : null );

		$result             = self::get_content_access( array( 'post_id' => $post->ID ) );
		$result['summary']  = sprintf(
			/* translators: 1: post title, 2: roles. */
			__( 'Restricted "%1$s" to %2$s.', 'members' ),
			get_the_title( $post ),
			implode( ', ', array_map( array( Ability::class, 'role_label' ), $roles ) )
		);
		$result['previous'] = $previous;

		if ( ! members_content_permissions_enabled() ) {
			$result['warning'] = __( 'Saved, but Content Permissions is turned off in Members > Settings, so the restriction won\'t apply until it\'s turned on.', 'members' );
		}

		return $result;
	}

	/**
	 * Unrestrict a post.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function unrestrict_content( array $input ) {
		$post = self::post( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$previous = self::snapshot( $post->ID );

		if ( ! $previous['roles'] ) {
			$inherited = self::restriction( $post );
			if ( $inherited['inherited_from'] ) {
				return new \WP_Error(
					'members_inherited_restriction',
					sprintf(
						/* translators: 1: parent title, 2: parent ID. */
						__( 'This has no restriction of its own: it inherits one from "%1$s" (ID %2$d). Unrestrict that instead.', 'members' ),
						get_the_title( $inherited['inherited_from'] ),
						$inherited['inherited_from']
					)
				);
			}

			return new \WP_Error( 'members_not_restricted', __( 'This content isn\'t restricted.', 'members' ) );
		}

		$ask = Confirmation::check(
			Ability::PREFIX . 'unrestrict-content',
			$input,
			sprintf(
				/* translators: 1: post title, 2: roles. */
				__( '"%1$s" is only visible to %2$s right now. Unrestricting it makes it visible to anyone who can see the site.', 'members' ),
				get_the_title( $post ),
				implode( ', ', array_map( array( Ability::class, 'role_label' ), $previous['roles'] ) )
			),
			array(
				'post'  => Ability::post_summary( $post ),
				'roles' => $previous['roles'],
			)
		);
		if ( $ask ) {
			return $ask;
		}

		self::apply( $post->ID, array(), '' );

		$result             = self::get_content_access( array( 'post_id' => $post->ID ) );
		$result['summary']  = sprintf(
			/* translators: %s: post title. */
			__( 'Removed the restriction on "%s".', 'members' ),
			get_the_title( $post )
		);
		$result['previous'] = $previous;

		return $result;
	}

	/**
	 * Restrict or unrestrict many posts.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function bulk_restrict_content( array $input ) {
		$action = isset( $input['action'] ) && 'unrestrict' === $input['action'] ? 'unrestrict' : 'restrict';
		$roles  = members_sanitize_access_role_meta_list( isset( $input['roles'] ) ? (array) $input['roles'] : array() );

		if ( 'restrict' === $action ) {
			if ( ! $roles ) {
				return new \WP_Error( 'members_no_roles', __( 'Pass at least one role to restrict to.', 'members' ) );
			}

			$error = Ability::unknown_roles_error( $roles );
			if ( $error ) {
				return $error;
			}
		}

		$posts = self::pick_posts( $input );
		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

		$skipped = array();
		$targets = array();

		foreach ( $posts as $post ) {
			if ( members_current_user_can_manage_post_content_permissions( $post->ID ) ) {
				$targets[] = $post;
			} else {
				$skipped[] = $post->ID;
			}
		}

		if ( ! $targets ) {
			return new \WP_Error( 'members_no_posts', __( 'No content you\'re allowed to change matched.', 'members' ) );
		}

		$labels = implode( ', ', array_map( array( Ability::class, 'role_label' ), $roles ) );
		$ask    = Confirmation::check(
			Ability::PREFIX . 'bulk-restrict-content',
			$input,
			'restrict' === $action
				/* translators: 1: number of items, 2: roles. */
				? sprintf( _n( 'This restricts %1$d item so only %2$s can see it. Any roles it has now are replaced.', 'This restricts %1$d items so only %2$s can see them. Any roles they have now are replaced.', count( $targets ), 'members' ), count( $targets ), $labels )
				/* translators: %d: number of items. */
				: sprintf( _n( 'This removes the restriction from %d item, making it visible to anyone who can see the site.', 'This removes the restriction from %d items, making them visible to anyone who can see the site.', count( $targets ), 'members' ), count( $targets ) ),
			array(
				'action'  => $action,
				'roles'   => $roles,
				'content' => array_map( array( Ability::class, 'post_summary' ), $targets ),
				'skipped' => $skipped,
			)
		);
		if ( $ask ) {
			return $ask;
		}

		$previous = array();
		$message  = array_key_exists( 'message', $input ) ? (string) $input['message'] : null;

		foreach ( $targets as $post ) {
			$previous[ $post->ID ] = self::snapshot( $post->ID );

			if ( 'restrict' === $action ) {
				self::apply( $post->ID, $roles, $message );
			} else {
				self::apply( $post->ID, array(), '' );
			}
		}

		return array(
			'changed'  => wp_list_pluck( array_map( array( Ability::class, 'post_summary' ), $targets ), 'id' ),
			'skipped'  => $skipped,
			'summary'  => 'restrict' === $action
				/* translators: 1: number of items, 2: roles. */
				? sprintf( _n( 'Restricted %1$d item to %2$s.', 'Restricted %1$d items to %2$s.', count( $targets ), 'members' ), count( $targets ), $labels )
				/* translators: %d: number of items. */
				: sprintf( _n( 'Unrestricted %d item.', 'Unrestricted %d items.', count( $targets ), 'members' ), count( $targets ) ),
			'previous' => $previous,
		);
	}

	/**
	 * Save a restriction through the same helper as the meta box and block editor panel, or
	 * clear it completely.
	 *
	 * @param int         $post_id Post ID.
	 * @param string[]    $roles   Roles; empty clears the restriction.
	 * @param string|null $message New message, '' to clear, null to keep.
	 * @return void
	 */
	private static function apply( $post_id, array $roles, $message ) {
		if ( null === $message ) {
			$message = (string) members_get_post_access_message( $post_id );
		}

		if ( $roles ) {
			members_save_post_content_permissions( $post_id, $roles, $message );
			return;
		}

		// Unrestricting means visible to everyone. The shared helper (like the editor UI) keeps
		// roles that no longer exist, but those still hide the post, so clear every role here,
		// including an old-style `_role` restriction.
		members_convert_old_post_meta( $post_id );
		members_delete_post_roles( $post_id );
		members_delete_post_access_message( $post_id );
	}

	/**
	 * Roles a post is restricted to, including an old-style `_role` restriction from Members
	 * versions before 1.0 that hasn't been migrated yet. Read-only.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	private static function stored_roles( $post_id ) {
		$roles = members_get_post_roles( $post_id );

		if ( ! $roles ) {
			$roles = get_post_meta( $post_id, '_role', false );
		}

		return array_values( array_filter( (array) $roles, 'is_string' ) );
	}

	/**
	 * Current roles and message, for undo.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private static function snapshot( $post_id ) {
		return array(
			'roles'   => self::stored_roles( $post_id ),
			'message' => (string) members_get_post_access_message( $post_id ),
		);
	}

	/**
	 * Load the post from input, refusing types that can't be restricted.
	 *
	 * @param array $input Input.
	 * @return \WP_Post|\WP_Error
	 */
	private static function post( array $input ) {
		$post = get_post( (int) $input['post_id'] );

		if ( ! $post ) {
			return new \WP_Error( 'members_no_post', __( 'No post found with that ID.', 'members' ) );
		}

		if ( ! members_is_content_permissions_enabled_for_post_type( $post->post_type ) ) {
			return new \WP_Error( 'members_type_not_restrictable', __( 'This content type can\'t be restricted with Members.', 'members' ) );
		}

		return $post;
	}

	/**
	 * Posts picked by a bulk call.
	 *
	 * @param array $input Input.
	 * @return \WP_Post[]|\WP_Error
	 */
	private static function pick_posts( array $input ) {
		$args = array(
			'post_status'         => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page'      => self::BULK_MAX + 1,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'suppress_filters'    => true,
		);

		if ( ! empty( $input['post_ids'] ) ) {
			$args['post__in']  = array_map( 'intval', (array) $input['post_ids'] );
			$args['post_type'] = members_get_content_permissions_post_types();
			$args['orderby']   = 'post__in';
		} elseif ( ! empty( $input['category'] ) || ! empty( $input['tag'] ) || ! empty( $input['search'] ) || ! empty( $input['post_type'] ) ) {
			$args['post_type'] = ! empty( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post';
			if ( ! empty( $input['category'] ) ) {
				$args['category_name'] = sanitize_title( $input['category'] );
			}
			if ( ! empty( $input['tag'] ) ) {
				$args['tag'] = sanitize_title( $input['tag'] );
			}
			if ( ! empty( $input['search'] ) ) {
				$args['s'] = (string) $input['search'];
			}

			if ( ! members_is_content_permissions_enabled_for_post_type( $args['post_type'] ) ) {
				return new \WP_Error( 'members_type_not_restrictable', __( 'That content type can\'t be restricted with Members.', 'members' ) );
			}
		} else {
			return new \WP_Error( 'members_no_selection', __( 'Pick content with post_ids, or with post_type, category, tag and/or search.', 'members' ) );
		}

		$posts = get_posts( $args );

		if ( count( $posts ) > self::BULK_MAX ) {
			return new \WP_Error(
				'members_too_many',
				sprintf(
					/* translators: %d: maximum posts. */
					__( 'More than %d items match. Narrow it down, or do it in batches by ID.', 'members' ),
					self::BULK_MAX
				)
			);
		}

		if ( ! $posts ) {
			return new \WP_Error( 'members_no_posts', __( 'No content matched.', 'members' ) );
		}

		return $posts;
	}
}
