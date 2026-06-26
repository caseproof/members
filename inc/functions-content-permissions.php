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
 * Whether Content Permissions is enabled for a post type.
 *
 * @since  3.2.22
 * @access public
 * @param  string  $post_type  Post type slug.
 * @return bool
 */
function members_is_content_permissions_enabled_for_post_type( $post_type ) {

	if ( empty( $post_type ) || 'attachment' === $post_type ) {
		return false;
	}

	$type = get_post_type_object( $post_type );

	if ( ! $type ) {
		return false;
	}

	$enable = $type->public;

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
		if ( current_user_can( 'restrict_content' ) && current_user_can( 'edit_post', $post->ID ) ) {
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
 * Maximum ancestor depth considered when mirroring hierarchical permissions in SQL.
 *
 * @since 3.2.23
 * @access private
 * @var int
 */
$GLOBALS['members_rest_protected_posts_ancestor_depth'] = 15;

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
 * Returns a SQL expression for the post ID at a given ancestor depth.
 *
 * Depth 0 is the current row, depth 1 is post_parent, and so on.
 *
 * @since 3.2.23
 * @access public
 * @param  int  $depth  Ancestor depth.
 * @return string
 */
function members_sql_post_id_at_depth( $depth ) {

	global $wpdb;

	if ( 0 === $depth ) {
		return "{$wpdb->posts}.ID";
	}

	$id = "{$wpdb->posts}.post_parent";

	for ( $i = 2; $i <= $depth; $i++ ) {
		$id = "(SELECT p_depth_{$i}.post_parent FROM {$wpdb->posts} p_depth_{$i} WHERE p_depth_{$i}.ID = {$id} LIMIT 1)";
	}

	return $id;
}

/**
 * Returns SQL that is true when a post has content-permission role meta.
 *
 * @since 3.2.23
 * @access public
 * @param  string  $post_id_sql  SQL expression for a post ID column.
 * @return string
 */
function members_sql_post_has_access_role_meta( $post_id_sql ) {

	global $wpdb;

	return "EXISTS (
		SELECT 1 FROM {$wpdb->postmeta} pm_roles
		WHERE pm_roles.post_id = {$post_id_sql}
		AND pm_roles.meta_key IN ( '_members_access_role', '_role' )
	)";
}

/**
 * Returns SQL that is true when a restricted post is viewable for the given roles.
 *
 * Mirrors the role, post-author, and ancestor-author checks from members_can_user_view_post().
 * Does not cover edit_post / restrict_content bypasses; those are applied separately.
 *
 * @since 3.2.23
 * @access public
 * @param  string  $post_id_sql  SQL expression for a post ID column.
 * @param  array   $roles        Current user role slugs.
 * @param  int     $user_id      Current user ID.
 * @return string
 */
function members_sql_post_access_roles_satisfied( $post_id_sql, $roles, $user_id ) {

	global $wpdb;

	$parts = array();

	if ( ! empty( $roles ) ) {
		$placeholders = implode( ', ', array_fill( 0, count( $roles ), '%s' ) );

		// WordPress $wpdb->prepare() accepts a roles array when placeholder count matches.
		$parts[] = $wpdb->prepare(
			"EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm_allow
				WHERE pm_allow.post_id = {$post_id_sql}
				AND pm_allow.meta_key IN ( '_members_access_role', '_role' )
				AND pm_allow.meta_value IN ( {$placeholders} )
			)",
			$roles
		);
	}

	if ( $user_id ) {
		$parts[] = $wpdb->prepare(
			"EXISTS (
				SELECT 1 FROM {$wpdb->posts} p_author
				WHERE p_author.ID = {$post_id_sql}
				AND p_author.post_author = %d
			)",
			$user_id
		);
	}

	if ( empty( $parts ) ) {
		return '0=1';
	}

	return '(' . implode( ' OR ', $parts ) . ')';
}

/**
 * Returns SQL that is true when any ancestor carries content-permission role meta.
 *
 * Used for logged-out users where no role can satisfy a restriction.
 *
 * @since 3.2.23
 * @access public
 * @return string
 */
function members_sql_any_ancestor_has_access_role_meta() {

	global $wpdb;

	$depth_limit = (int) $GLOBALS['members_rest_protected_posts_ancestor_depth'];
	$checks      = array();

	for ( $depth = 1; $depth <= $depth_limit; $depth++ ) {
		$ancestor_id = members_sql_post_id_at_depth( $depth );
		$checks[]    = "(
			{$ancestor_id} > 0
			AND " . members_sql_post_has_access_role_meta( $ancestor_id ) . '
		)';
	}

	return '(' . implode( ' OR ', $checks ) . ')';
}

/**
 * Returns SQL that is true when a post inherits an unsatisfied restriction from an ancestor.
 *
 * Walks up the parent chain and applies the first ancestor that carries role meta,
 * matching members_can_user_view_post() hierarchical checks.
 *
 * @since 3.2.23
 * @access public
 * @param  array  $roles    Current user role slugs.
 * @param  int    $user_id  Current user ID.
 * @return string
 */
function members_sql_inherited_restriction_unsatisfied( $roles, $user_id ) {

	global $wpdb;

	$depth_limit = (int) $GLOBALS['members_rest_protected_posts_ancestor_depth'];
	$self_id       = "{$wpdb->posts}.ID";
	$no_self_meta  = 'NOT ' . members_sql_post_has_access_role_meta( $self_id );
	$cases         = array();

	for ( $depth = 1; $depth <= $depth_limit; $depth++ ) {
		$ancestor_id = members_sql_post_id_at_depth( $depth );
		$parts       = array( $no_self_meta );

		for ( $intermediate = 1; $intermediate < $depth; $intermediate++ ) {
			$parts[] = 'NOT ' . members_sql_post_has_access_role_meta( members_sql_post_id_at_depth( $intermediate ) );
		}

		$parts[] = "{$ancestor_id} > 0";
		$parts[] = members_sql_post_has_access_role_meta( $ancestor_id );
		$parts[] = 'NOT (' . members_sql_post_access_roles_satisfied( $ancestor_id, $roles, $user_id ) . ')';

		$cases[] = '(' . implode( ' AND ', $parts ) . ')';
	}

	return '(' . implode( ' OR ', $cases ) . ')';
}

/**
 * Returns SQL that is true when the current user can edit others' posts of the row type.
 *
 * Approximates the edit_post bypass from members_can_user_view_post() for REST collections.
 *
 * @since 3.2.23
 * @access public
 * @param  int  $user_id  Current user ID.
 * @return string
 */
function members_sql_user_can_edit_others_post_types( $user_id ) {

	global $wpdb;

	if ( ! $user_id ) {
		return '0=1';
	}

	$parts = array();

	foreach ( get_post_types( array(), 'objects' ) as $post_type ) {

		if ( empty( $post_type->cap->edit_others_posts ) ) {
			continue;
		}

		if ( user_can( $user_id, $post_type->cap->edit_others_posts ) ) {
			$parts[] = $wpdb->prepare( "{$wpdb->posts}.post_type = %s", $post_type->name );
		}
	}

	if ( empty( $parts ) ) {
		return '0=1';
	}

	return '(' . implode( ' OR ', $parts ) . ')';
}

/**
 * Builds the SQL fragment that excludes posts the current user cannot view.
 *
 * @since 3.2.23
 * @access public
 * @param  array  $roles    Current user role slugs.
 * @param  int    $user_id  Current user ID.
 * @return string
 */
function members_build_rest_protected_posts_exclusion_sql( $roles, $user_id ) {

	global $wpdb;

	$post_id   = "{$wpdb->posts}.ID";
	$has_roles = members_sql_post_has_access_role_meta( $post_id );

	if ( empty( $roles ) ) {

		// Logged-out visitors cannot satisfy any role restriction on the post or an ancestor.
		$restricted = "(
			{$has_roles}
			OR (
				NOT {$has_roles}
				AND " . members_sql_any_ancestor_has_access_role_meta() . '
			)
		)';

		return " AND NOT ({$restricted})";
	}

	$roles_unsatisfied = 'NOT (' . members_sql_post_access_roles_satisfied( $post_id, $roles, $user_id ) . ')';

	$direct_restricted = "(
		{$has_roles}
		AND {$roles_unsatisfied}
	)";

	$inherited_restricted = members_sql_inherited_restriction_unsatisfied( $roles, $user_id );

	$edit_bypass = members_sql_user_can_edit_others_post_types( $user_id );

	$restricted = "(
		({$direct_restricted} OR {$inherited_restricted})
		AND NOT ({$edit_bypass})
	)";

	return " AND NOT ({$restricted})";
}

/**
 * Excludes protected posts from REST API queries at the SQL level.
 *
 * Filtering the results after the query runs (see
 * members_filter_protected_posts_for_rest()) hides the post bodies but leaves
 * the row count intact, so the X-WP-Total / X-WP-TotalPages headers and the
 * per-page "empty array" responses can be used as a side channel to infer the
 * existence and contents of hidden posts. Excluding the rows in the SQL query
 * itself keeps the counts accurate and closes that side channel.
 *
 * SQL exclusion mirrors members_can_user_view_post() for direct and inherited
 * (parent) restrictions, including post-author bypasses. Custom filters on
 * members_can_user_view_post do not apply here; use members_rest_protected_posts_where.
 *
 * @since 3.2.23
 * @access public
 * @param string    $where  The WHERE clause of the query.
 * @param WP_Query  $query  The WP_Query object.
 * @return string
 */
function members_exclude_protected_posts_from_rest_query( $where, $query ) {

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

	$user_id = is_user_logged_in() ? get_current_user_id() : 0;
	$roles   = is_user_logged_in() ? (array) wp_get_current_user()->roles : array();

	$where .= members_build_rest_protected_posts_exclusion_sql( $roles, $user_id );

	/**
	 * Filters the SQL WHERE clause used to exclude protected posts from REST collections.
	 *
	 * Custom code filtering members_can_user_view_post does not automatically affect
	 * this SQL exclusion. Use this hook to keep customized permission logic aligned.
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
