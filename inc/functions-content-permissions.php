<?php
/**
 * Handles permissions for post content, post excerpts, and post comments.  This is based on whether a user
 * has permission to view a post according to the settings provided by the plugin.
 *
 * @package Members
 * @subpackage Functions
 */
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

# Enable the content permissions features.
add_action( 'after_setup_theme', 'members_enable_content_permissions', 0 );

/**
 * Conditional check to determine if a post any permissions rules assigned
 * to it.
 *
 * @since  2.0.0
 * @access public
 * @param  $post_id
 * @return bool
 */
function members_has_post_permissions( $post_id = '' ) {

	return members_has_post_roles( $post_id );
}

/**
 * Returns an array of the roles for a given post.
 *
 * @since  1.0.0
 * @access public
 * @param  int    $post_id
 * @return array
 */
function members_get_post_roles( $post_id ) {
	return get_post_meta( $post_id, '_members_access_role', false );
}

/**
 * Sanitizes a single `_members_access_role` meta value for storage.
 *
 * Registered meta uses `single => false`, so each value must be a string. Arrays are
 * rejected so REST saves never pass nested role lists into members_sanitize_role().
 *
 * @since  3.2.22
 * @access public
 * @param  mixed  $value  Meta value from the database or REST request.
 * @return string
 */
function members_sanitize_access_role_meta_value( $value ) {

	if ( is_array( $value ) || ! is_string( $value ) || '' === $value ) {
		return '';
	}

	$role = members_sanitize_role( $value );

	return '' !== $role ? $role : '';
}

/**
 * Sanitizes an array of `_members_access_role` values for REST and programmatic saves.
 *
 * @since  3.2.22
 * @access public
 * @param  mixed  $roles  Role slug list from a REST or form payload.
 * @return array
 */
function members_sanitize_access_role_meta_list( $roles ) {

	$sanitized = array();

	if ( ! is_array( $roles ) ) {
		return $sanitized;
	}

	foreach ( $roles as $role ) {
		if ( is_string( $role ) && '' !== $role ) {
			$role = members_sanitize_role( $role );

			if ( '' !== $role ) {
				$sanitized[] = $role;
			}
		}
	}

	return array_values( array_unique( $sanitized ) );
}

/**
 * Prevents empty `_members_access_role` rows from being stored.
 *
 * @since  3.2.22
 * @access public
 * @param  null|bool  $check       Short-circuit return value.
 * @param  int        $object_id   Post ID.
 * @param  string     $meta_key    Meta key.
 * @param  mixed      $meta_value  Meta value.
 * @return null|bool
 */
function members_skip_empty_access_role_post_meta( $check, $object_id, $meta_key, $meta_value ) {

	if ( '_members_access_role' !== $meta_key || ( is_string( $meta_value ) && '' !== $meta_value ) ) {
		return $check;
	}

	return true;
}

add_filter( 'add_post_metadata', 'members_skip_empty_access_role_post_meta', 10, 4 );
add_filter( 'update_post_metadata', 'members_skip_empty_access_role_post_meta', 10, 4 );

/**
 * Returns access roles for REST/block editor reads without writing to the database.
 *
 * @since  3.2.22
 * @access public
 * @param  int  $post_id  Post ID.
 * @return array
 */
function members_get_post_roles_for_rest( $post_id ) {

	$roles = members_get_post_roles( $post_id );

	if ( empty( $roles ) ) {
		$legacy = get_post_meta( $post_id, '_role', false );

		if ( ! empty( $legacy ) ) {
			$roles = array();

			foreach ( (array) $legacy as $role ) {
				if ( is_string( $role ) && '' !== $role ) {
					$roles[] = members_sanitize_role( $role );
				}
			}

			$roles = array_values( array_unique( $roles ) );
		}
	}

	return is_array( $roles ) ? $roles : array();
}

/**
 * Conditional check to determine if a post has roles assigned to it.
 *
 * @since  2.0.0
 * @access public
 * @param  int     $post_id
 * @return bool
 */
function members_has_post_roles( $post_id = '' ) {

	if ( ! $post_id )
		$post_id = get_the_ID();

	$roles = members_get_post_roles( $post_id );

	return ! empty( $roles );
}

/**
 * Whether the current user can manage content permissions for a specific post.
 *
 * The single definition of the "manager" policy used by the REST read strip, the REST
 * write gate, and the protected-posts result filter. Keep them in sync by changing it here.
 *
 * @since  3.2.25
 * @access public
 * @param  int  $post_id  Post ID.
 * @return bool
 */
function members_current_user_can_manage_post_content_permissions( $post_id ) {

	return current_user_can( 'restrict_content' ) && current_user_can( 'edit_post', $post_id );
}

/**
 * Whether Content Permissions is enabled for a post type.
 *
 * @since  3.2.22
 * @access public
 * @param  string  $post_type  Post type slug.
 * @return bool
 */
function members_is_content_permissions_enabled_for_post_type( $post_type ) {

	if ( empty( $post_type ) ) {
		return false;
	}

	$type = get_post_type_object( $post_type );

	if ( ! $type ) {
		return false;
	}

	// Attachments are off by default, but still honor the filter so a site can opt them in
	// (an early return here previously broke sites using members_enable_attachment_content_permissions).
	$enable = 'attachment' === $post_type ? false : $type->public;

	return apply_filters( "members_enable_{$post_type}_content_permissions", $enable );
}

/**
 * Post types that support the Content Permissions UI and REST meta fields.
 *
 * @since  3.2.22
 * @access public
 * @return array Post type slugs.
 */
function members_get_content_permissions_post_types() {

	$post_types = array();

	foreach ( get_post_types( array(), 'names' ) as $post_type ) {
		if ( members_is_content_permissions_enabled_for_post_type( $post_type ) ) {
			$post_types[] = $post_type;
		}
	}

	return $post_types;
}

/**
 * Resolves the post for Content Permissions UI (classic meta box and block editor).
 *
 * @since  3.2.22
 * @access public
 * @param  \WP_Post|null  $post  Known post object, if available.
 * @return \WP_Post|null
 */
function members_get_post_for_content_permissions( $post = null ) {

	if ( $post instanceof \WP_Post ) {
		return $post;
	}

	$post = get_post();

	if ( ! $post && ! empty( $_GET['post'] ) ) {
		$post = get_post( absint( $_GET['post'] ) );
	}

	return $post instanceof \WP_Post ? $post : null;
}

/**
 * Adds a single role to a post's access roles.
 *
 * @since  1.0.0
 * @access public
 * @param  int        $post_id
 * @param  string     $role
 * @return int|false
 */
function members_add_post_role( $post_id, $role ) {

	return add_post_meta( $post_id, '_members_access_role', $role, false );
}

/**
 * Removes a single role from a post's access roles.
 *
 * @since  1.0.0
 * @access public
 * @param  int        $post_id
 * @param  string     $role
 * @return bool
 */
function members_remove_post_role( $post_id, $role ) {

	return delete_post_meta( $post_id, '_members_access_role', $role );
}

/**
 * Returns stored role slugs that are not registered WordPress roles (e.g. deleted custom roles).
 *
 * @since  3.2.22
 * @access public
 * @param  int  $post_id  Post ID.
 * @return array
 */
function members_get_orphan_post_roles( $post_id ) {
	global $wp_roles;

	$roles   = members_get_post_roles( $post_id );
	$orphans = array();

	if ( empty( $roles ) || ! is_array( $roles ) ) {
		return $orphans;
	}

	foreach ( $roles as $role ) {
		if ( is_string( $role ) && '' !== $role && ! isset( $wp_roles->role_names[ $role ] ) ) {
			$orphans[] = members_sanitize_role( $role );
		}
	}

	return array_values( array_unique( $orphans ) );
}

/**
 * Sets a post's access roles given an array of roles.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $post_id
 * @param  array   $roles
 * @global object  $wp_roles
 * @return void
 */
function members_set_post_roles( $post_id, $roles ) {
	global $wp_roles;

	// Get the current roles.
	$current_roles = get_post_meta( $post_id, '_members_access_role', false );

	// Loop through new roles.
	foreach ( $roles as $role ) {

		// If new role is not already one of the current roles, add it.
		if ( ! in_array( $role, $current_roles ) )
			members_add_post_role( $post_id, $role );
	}

	// Loop through all WP roles.
	foreach ( $wp_roles->role_names as $role => $name ) {

		// If the WP role is one of the current roles but not a new role, remove it.
		if ( ! in_array( $role, $roles ) && in_array( $role, $current_roles ) )
			members_remove_post_role( $post_id, $role );
	}
}

/**
 * Deletes all of a post's access roles.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $post_id
 * @return bool
 */
function members_delete_post_roles( $post_id ) {

	return delete_post_meta( $post_id, '_members_access_role' );
}

/**
 * Adds required filters for the content permissions feature if it is active.
 *
 * @since  0.2.0
 * @access public
 * @global object  $wp_embed
 * @return void
 */
function members_enable_content_permissions() {
	global $wp_embed;

	// Only add filters if the content permissions feature is enabled and we're not in the admin.
	if ( members_content_permissions_enabled() && !is_admin() ) {

		// Filter the content and exerpts.
		add_filter( 'the_content',      'members_content_permissions_protect', 95 );
		add_filter( 'get_the_excerpt',  'members_content_permissions_protect', 95 );
		add_filter( 'the_excerpt',      'members_content_permissions_protect', 95 );
		add_filter( 'the_content_feed', 'members_content_permissions_protect', 95 );
		add_filter( 'get_comment_text', 'members_content_permissions_protect', 95 );

		// Filter the comments template to make sure comments aren't shown to users without access.
		add_filter( 'comments_template', 'members_content_permissions_comments', 95 );

		// Use WP formatting filters on the post error message.
		add_filter( 'members_post_error_message', array( $wp_embed, 'run_shortcode' ),   5 );
		add_filter( 'members_post_error_message', array( $wp_embed, 'autoembed'     ),   5 );
		add_filter( 'members_post_error_message',                   'wptexturize',       10 );
		add_filter( 'members_post_error_message',                   'convert_smilies',   15 );
		add_filter( 'members_post_error_message',                   'convert_chars',     20 );
		add_filter( 'members_post_error_message',                   'wpautop',           25 );
		add_filter( 'members_post_error_message',                   'do_shortcode',      30 );
		add_filter( 'members_post_error_message',                   'shortcode_unautop', 35 );
	}
}

/**
 * Denies/Allows access to view post content depending on whether a user has permission to
 * view the content.
 *
 * @since  0.1.0
 * @access public
 * @param  string  $content
 * @return string
 */
function members_content_permissions_protect( $content ) {

	$post_id = get_the_ID();

	return members_can_current_user_view_post( $post_id ) ? $content : members_get_post_error_message( $post_id );
}

/**
 * Disables the comments template if a user doesn't have permission to view the post the
 * comments are associated with.
 *
 * @since  0.1.0
 * @param  string  $template
 * @return string
 */
function members_content_permissions_comments( $template ) {

	// Check if the current user has permission to view the comments' post.
	if ( ! members_can_current_user_view_post( get_the_ID() ) ) {

		// Look for a 'comments-no-access.php' template in the parent and child theme.
		$has_template = locate_template( array( 'comments-no-access.php' ) );

		// If the template was found, use it.  Otherwise, fall back to the Members comments.php template.
		$template = $has_template ? $has_template : members_plugin()->dir . 'templates/comments.php';

		// Allow devs to overwrite the comments template.
		$template = apply_filters( 'members_comments_template', $template );
	}

	// Return the comments template filename.
	return $template;
}

/**
 * Gets the error message to display for users who do not have access to view the given post.
 * The function first checks to see if a custom error message has been written for the
 * specific post.  If not, it loads the error message set on the plugins settings page.
 *
 * @since  0.2.0
 * @access public
 * @param  int     $post_id
 * @return string
 */
function members_get_post_error_message( $post_id ) {

	// Get the error message for the specific post.
	$message = members_get_post_access_message( $post_id );

	// Use default error message if we don't have one for the post.
	if ( ! $message )
		$message = members_get_setting( 'content_permissions_error' );

	// Return the error message.
	return apply_filters( 'members_post_error_message', sprintf( '<div class="members-access-error">%s</div>', $message ) );
}

/**
 * Returns the post access message.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $post_id
 * @return string
 */
function members_get_post_access_message( $post_id ) {

	return get_post_meta( $post_id, '_members_access_error', true );
}

/**
 * Sets the post access message.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $post_id
 * @param  string  $message
 * @return bool
 */
function members_set_post_access_message( $post_id, $message ) {

	return update_post_meta( $post_id, '_members_access_error', $message );
}

/**
 * Deletes the post access message.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $post_id
 * @return bool
 */
function members_delete_post_access_message( $post_id ) {

	return delete_post_meta( $post_id, '_members_access_error' );
}

/**
 * Converts the meta values of the old '_role' post meta key to the newer '_members_access_role' meta
 * key.  The reason for this change is to avoid any potential conflicts with other plugins/themes.  We're
 * now using a meta key that is extremely specific to the Members plugin.
 *
 * @since  0.2.0
 * @access public
 * @param  int         $post_id
 * @return array|bool
 */
function members_convert_old_post_meta( $post_id ) {

	// Check if there are any meta values for the '_role' meta key.
	$old_roles = get_post_meta( $post_id, '_role', false );

	// If roles were found, let's convert them.
	if ( !empty( $old_roles ) ) {

		// Delete the old '_role' post meta.
		delete_post_meta( $post_id, '_role' );

		// Check if there are any roles for the '_members_access_role' meta key.
		$new_roles = get_post_meta( $post_id, '_members_access_role', false );

		// If new roles were found, don't do any conversion.
		if ( empty( $new_roles ) ) {

			// Loop through the old meta values for '_role' and add them to the new '_members_access_role' meta key.
			foreach ( $old_roles as $role )
				add_post_meta( $post_id, '_members_access_role', $role, false );

			// Return the array of roles.
			return $old_roles;
		}
	}

	// Return false if we get to this point.
	return false;
}

/**
 * Filters protected posts from being returned in the REST API.
 *
 * @since 3.2.11
 * @access public
 * @param array     $posts  The array of posts.
 * @param WP_Query  $query  The WP_Query object.
 * @return array
 */
function members_filter_protected_posts_for_rest( $posts, $query ) {

	if ( ! members_content_permissions_enabled() || ! members_is_hidden_protected_posts_enabled() ) {
		return $posts;
	}

	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST || ! is_array( $posts ) || empty( $posts ) ) {
		return $posts;
	}

	$removed = 0;

	foreach ( $posts as $key => $post ) {
		if ( members_can_current_user_view_post( $post->ID ) ) {
			continue;
		}

		// Permission managers may load protected posts they can edit (block editor list/detail).
		if ( members_current_user_can_manage_post_content_permissions( $post->ID ) ) {
			continue;
		}

		unset( $posts[ $key ] );
		$removed++;
	}

	// Recompute the query metadata so the REST pagination headers (X-WP-Total /
	// X-WP-TotalPages) reflect the filtered result set rather than the raw count.
	// This is a defense-in-depth backstop for any posts not already excluded at
	// the SQL level by members_exclude_protected_posts_from_rest_query().
	if ( $removed > 0 && $query instanceof \WP_Query ) {

		$query->found_posts = max( 0, (int) $query->found_posts - $removed );

		$per_page = (int) $query->get( 'posts_per_page' );

		if ( $per_page > 0 ) {
			$query->max_num_pages = (int) ceil( $query->found_posts / $per_page );
		}
	}

	return array_values( $posts );
}

/**
 * Whether the REST protected-post SQL exclusion should run for a query.
 *
 * Only paginated collection queries expose X-WP-Total / X-WP-TotalPages headers.
 * Single-item lookups and unbounded queries are skipped to avoid unnecessary work.
 *
 * @since 3.2.23
 * @access public
 * @param  WP_Query  $query  The WP_Query object.
 * @return bool
 */
function members_should_apply_rest_protected_posts_sql( $query ) {

	if ( ! $query instanceof \WP_Query ) {
		return false;
	}

	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
		return false;
	}

	// Single-item lookups do not expose collection pagination headers.
	if ( $query->get( 'p' ) || $query->get( 'page_id' ) || $query->get( 'name' ) || $query->get( 'pagename' ) ) {
		return false;
	}

	$per_page = (int) $query->get( 'posts_per_page' );

	return $per_page > 0;
}

/**
 * Whether a user can view a post that itself carries content-permission role meta.
 *
 * Mirrors the role, author, edit_post, and restrict_content checks from
 * members_can_user_view_post() — including its filter — but is safe to run in bulk on
 * read requests: it never triggers the legacy `_role` meta conversion (which deletes and
 * rewrites postmeta), and it guards against unregistered post types. Only meaningful for
 * restriction roots; it does not walk ancestors.
 *
 * @since 3.2.25
 * @access public
 * @param  int  $user_id  User ID (0 for logged-out visitors).
 * @param  int  $post_id  Post ID of a restriction root.
 * @return bool
 */
function members_rest_user_can_view_restricted_post( $user_id, $post_id ) {

	$post = get_post( $post_id );

	if ( ! $post instanceof \WP_Post ) {
		return true;
	}

	// Read-only lookup: resolves legacy `_role` values without converting them.
	$roles = members_get_post_roles_for_rest( $post_id );

	if ( empty( $roles ) ) {
		$can_view = true;
	} elseif ( ! $user_id ) {
		$can_view = false;
	} elseif ( (int) $post->post_author === (int) $user_id || user_can( $user_id, 'restrict_content' ) ) {
		$can_view = true;
	} else {
		$type     = get_post_type_object( $post->post_type );
		$can_view = $type && ! empty( $type->cap->edit_post ) && user_can( $user_id, $type->cap->edit_post, $post_id );

		if ( ! $can_view ) {
			$can_view = members_user_has_role( $user_id, $roles );
		}
	}

	/** This filter is documented in inc/template.php */
	return apply_filters( 'members_can_user_view_post', $can_view, $user_id, $post_id );
}

/**
 * Whether a post is hidden from the current user for REST reads.
 *
 * Single source of truth shared by the collection exclusion, the single-item 404, and the
 * comment filters, so those decisions can never disagree (an earlier per-request ancestor-walk
 * helper diverged from the collection set and could embed a WP_Error into a comment collection).
 * Read-only and scoped to the post's own type for efficiency.
 *
 * @since 3.2.25
 * @access public
 * @param  int  $post_id  Post ID.
 * @return bool
 */
function members_is_post_hidden_from_current_user_in_rest( $post_id ) {

	$post_id = (int) $post_id;

	if ( ! $post_id ) {
		return false;
	}

	$post_type = get_post_type( $post_id );
	$scope     = $post_type ? array( $post_type ) : array();

	return in_array( $post_id, members_get_hidden_protected_post_ids( $scope ), true );
}

/**
 * Returns the post IDs the current user cannot view, for REST collection exclusion.
 *
 * Earlier releases mirrored members_can_user_view_post() directly in SQL, walking the
 * post hierarchy with deeply nested correlated subqueries. On large sites that caused
 * severe database load, and it could exceed MySQL's join/subquery limits so the whole
 * query failed and returned zero posts. Instead we resolve the set of posts that carry
 * a role restriction, evaluate each with the vetted PHP permission logic (side-effect
 * free — see members_rest_user_can_view_restricted_post()), then expand to descendants
 * that inherit an unsatisfied restriction.
 *
 * Work is scoped to the queried post type(s): roots of those types are always checked,
 * while roots of other types are checked only when they have children — a queried-type
 * post can inherit a restriction from an ancestor of another type, but a childless root
 * of another type can never affect this query. The returned list contains only IDs of
 * the queried types, so the NOT IN clause stays as small as possible.
 *
 * @since 3.2.24
 * @access public
 * @param  WP_Query  $query  The query being filtered.
 * @return int[]  Post IDs to exclude from the collection (may be empty).
 */
function members_get_rest_hidden_post_ids( $query ) {

	// Queried post types scope the work. 'any' (or an unset type) means no scoping.
	$post_types = array_filter( array_map( 'strval', (array) $query->get( 'post_type' ) ) );

	if ( in_array( 'any', $post_types, true ) ) {
		$post_types = array();
	}

	return members_get_hidden_protected_post_ids( $post_types );
}

/**
 * Resolves the protected post IDs the current user cannot view.
 *
 * See members_get_rest_hidden_post_ids() for the strategy. Callers without a WP_Query
 * (e.g. REST comment queries) may pass an explicit post type list, or none for all types.
 *
 * @since 3.2.25
 * @access public
 * @param  array  $post_types  Post type slugs to scope the result to; empty for all.
 * @return int[]  Post IDs the current user cannot view (may be empty).
 */
function members_get_hidden_protected_post_ids( $post_types = array() ) {

	global $wpdb;

	$post_types = array_values( array_filter( array_map( 'strval', (array) $post_types ) ) );

	sort( $post_types );

	// The result depends only on the current user and the queried types, so compute it once
	// per request per combination. REST clients and the block editor commonly run several
	// collection queries in a single request.
	static $cache = array();

	$cache_key = get_current_blog_id() . ':' . get_current_user_id() . ':' . implode( ',', $post_types );

	if ( isset( $cache[ $cache_key ] ) ) {
		return $cache[ $cache_key ];
	}

	// Every post carrying role meta, with its type. Cross-type inheritance (a post whose
	// ancestor is another post type) means other-type roots cannot be ignored outright.
	$roots = $wpdb->get_results(
		"SELECT DISTINCT p.ID, p.post_type
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
		WHERE pm.meta_key IN ( '_members_access_role', '_role' )"
	);

	// No posts carry a restriction, so nothing is hidden.
	if ( empty( $roots ) ) {
		return $cache[ $cache_key ] = array();
	}

	$type_of = array();
	$direct  = array();
	$foreign = array();

	foreach ( $roots as $root ) {
		$root_id = (int) $root->ID;

		$type_of[ $root_id ] = $root->post_type;

		if ( empty( $post_types ) || in_array( $root->post_type, $post_types, true ) ) {
			$direct[] = $root_id;
		} else {
			$foreign[] = $root_id;
		}
	}

	// Other-type roots only matter as inheritance sources, so drop the childless ones
	// before paying for permission checks. This keeps the common case — one queried type,
	// restrictions concentrated on that type — as cheap as a type-scoped lookup.
	$seeds = $direct;

	if ( ! empty( $foreign ) ) {
		$placeholders = implode( ', ', array_fill( 0, count( $foreign ), '%d' ) );

		$with_children = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_parent FROM {$wpdb->posts}
				WHERE post_parent IN ( {$placeholders} ) AND post_type != 'revision'",
				$foreign
			)
		);

		$seeds = array_merge( $seeds, array_map( 'intval', (array) $with_children ) );
	}

	if ( empty( $seeds ) ) {
		return $cache[ $cache_key ] = array();
	}

	$user_id = get_current_user_id();
	$hidden  = array();

	// Chunked so cache priming stays bounded on sites with very large restricted sets.
	foreach ( array_chunk( $seeds, 500 ) as $chunk ) {

		_prime_post_caches( $chunk, false, true );

		foreach ( $chunk as $seed_id ) {
			if ( ! members_rest_user_can_view_restricted_post( $user_id, $seed_id ) ) {
				$hidden[ $seed_id ] = $seed_id;
			}
		}
	}

	if ( empty( $hidden ) ) {
		return $cache[ $cache_key ] = array();
	}

	// Expand to descendants that inherit an unsatisfied restriction. A descendant that
	// carries its own restriction is governed independently, so descent stops at any post
	// that is itself a restriction root. Revisions link to their post via post_parent but
	// never appear in REST collections, so walking them would only bloat the list.
	$root_lookup = $type_of;
	$parents     = array_values( $hidden );
	$guard       = 0;

	while ( ! empty( $parents ) && $guard++ < 100 ) {

		$placeholders = implode( ', ', array_fill( 0, count( $parents ), '%d' ) );

		$children = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_type FROM {$wpdb->posts}
				WHERE post_parent IN ( {$placeholders} ) AND post_type != 'revision'",
				$parents
			)
		);

		$parents = array();

		foreach ( (array) $children as $child ) {

			$child_id = (int) $child->ID;

			// Already hidden, or a root that is governed on its own terms.
			if ( isset( $hidden[ $child_id ] ) || isset( $root_lookup[ $child_id ] ) ) {
				continue;
			}

			$type_of[ $child_id ] = $child->post_type;
			$hidden[ $child_id ]  = $child_id;
			$parents[]            = $child_id;
		}
	}

	// Only IDs of the queried types can appear in this query; the walk may pass through
	// other types to reach them, but those intermediate IDs have no business in NOT IN.
	if ( ! empty( $post_types ) ) {
		$hidden = array_filter(
			$hidden,
			function ( $hidden_id ) use ( $type_of, $post_types ) {
				return isset( $type_of[ $hidden_id ] ) && in_array( $type_of[ $hidden_id ], $post_types, true );
			}
		);
	}

	return $cache[ $cache_key ] = array_values( $hidden );
}

/**
 * Excludes protected posts from REST API collection queries so pagination stays accurate.
 *
 * Filtering the results after the query runs (see members_filter_protected_posts_for_rest())
 * hides the post bodies but leaves the row count intact, so the X-WP-Total / X-WP-TotalPages
 * headers and per-page "empty array" responses can be used as a side channel to infer the
 * existence of hidden posts. Excluding the row IDs in the query itself keeps the counts
 * accurate and closes that side channel.
 *
 * The excluded IDs are resolved in PHP via members_get_rest_hidden_post_ids(), which reuses
 * members_can_user_view_post(); custom filters on that function are therefore honored. Use
 * the members_rest_hidden_post_ids filter to adjust the excluded set directly.
 *
 * @since 3.2.23
 * @access public
 * @param  string    $where  The WHERE clause of the query.
 * @param  WP_Query  $query  The WP_Query object.
 * @return string
 */
function members_exclude_protected_posts_from_rest_query( $where, $query ) {

	global $wpdb;

	if ( ! members_content_permissions_enabled() || ! members_is_hidden_protected_posts_enabled() ) {
		return $where;
	}

	if ( ! members_should_apply_rest_protected_posts_sql( $query ) ) {
		return $where;
	}

	// Permission managers may legitimately load protected posts (e.g. in the block
	// editor). The posts_results filter still vets each one per-post.
	if ( current_user_can( 'restrict_content' ) ) {
		return $where;
	}

	$hidden = members_get_rest_hidden_post_ids( $query );

	/**
	 * Filters the post IDs excluded from REST collections for the current user.
	 *
	 * @since 3.2.24
	 *
	 * @param int[]    $hidden  Post IDs to exclude.
	 * @param WP_Query $query   The WP_Query object.
	 */
	$hidden = apply_filters( 'members_rest_hidden_post_ids', $hidden, $query );

	$hidden = array_filter( array_map( 'intval', (array) $hidden ) );

	if ( ! empty( $hidden ) ) {
		$where .= " AND {$wpdb->posts}.ID NOT IN ( " . implode( ', ', $hidden ) . ' )';
	}

	/**
	 * Filters the SQL WHERE clause used to exclude protected posts from REST collections.
	 *
	 * @since 3.2.23
	 *
	 * @param string   $where  The WHERE clause of the query.
	 * @param WP_Query $query  The WP_Query object.
	 */
	return apply_filters( 'members_rest_protected_posts_where', $where, $query );
}

# Exclude protected posts from REST API queries at the SQL level so pagination headers stay accurate.
add_filter( 'posts_where', 'members_exclude_protected_posts_from_rest_query', 10, 2 );

# Filter protected posts from being returned in the REST API.
add_filter( 'posts_results', 'members_filter_protected_posts_for_rest', 10, 2 );

/**
 * Excludes comments on protected posts from REST comment collections.
 *
 * The front end blocks the whole comment template on protected posts, but the REST
 * comments controller queries with WP_Comment_Query — untouched by the posts_where /
 * posts_results filters — and renders bodies through `comment_text`, not the
 * `get_comment_text` filter this plugin protects. Without this, GET /wp/v2/comments?post=N
 * returns the full discussion of a role-protected post to anonymous visitors.
 *
 * @since 3.2.25
 * @access public
 * @param  array            $args     WP_Comment_Query arguments.
 * @param  \WP_REST_Request $request  REST request object.
 * @return array
 */
function members_exclude_protected_post_comments_from_rest( $args, $request ) {

	if ( ! members_content_permissions_enabled() || ! members_is_hidden_protected_posts_enabled() || current_user_can( 'restrict_content' ) ) {
		return $args;
	}

	$hidden = members_get_hidden_protected_post_ids();

	if ( empty( $hidden ) ) {
		return $args;
	}

	$not_in = isset( $args['post__not_in'] ) ? array_map( 'intval', (array) $args['post__not_in'] ) : array();

	$args['post__not_in'] = array_values( array_unique( array_merge( $not_in, $hidden ) ) );

	return $args;
}

# Exclude comments on protected posts from REST comment collections.
add_filter( 'rest_comment_query', 'members_exclude_protected_post_comments_from_rest', 10, 2 );

/**
 * Denies single-comment REST reads for comments on posts the user cannot view.
 *
 * Collections are excluded by members_exclude_protected_post_comments_from_rest(); this
 * covers GET /wp/v2/comments/<id> and acts as defense in depth for embedded responses.
 *
 * @since 3.2.25
 * @access public
 * @param  \WP_REST_Response|mixed  $response  REST response object.
 * @param  \WP_Comment              $comment   Comment object.
 * @param  \WP_REST_Request         $request   REST request object.
 * @return \WP_REST_Response|\WP_Error|mixed
 */
function members_hide_protected_post_comment_in_rest( $response, $comment, $request ) {

	if ( ! members_content_permissions_enabled() || ! members_is_hidden_protected_posts_enabled() || ! $comment instanceof \WP_Comment ) {
		return $response;
	}

	$post_id = (int) $comment->comment_post_ID;

	if ( ! $post_id || members_current_user_can_manage_post_content_permissions( $post_id ) ) {
		return $response;
	}

	// Same source of truth as the collection filter, so a comment that passed rest_comment_query
	// is never turned into a WP_Error here — which prepare_response_for_collection() would
	// otherwise embed as a garbled collection item.
	if ( ! members_is_post_hidden_from_current_user_in_rest( $post_id ) ) {
		return $response;
	}

	return new \WP_Error(
		'rest_cannot_read',
		__( 'Sorry, you are not allowed to read this comment.', 'members' ),
		array( 'status' => rest_authorization_required_code() )
	);
}

# Deny single-comment REST reads on protected posts.
add_filter( 'rest_prepare_comment', 'members_hide_protected_post_comment_in_rest', 10, 3 );
