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

	$stored_rows = get_post_meta( $post_id, '_members_access_role', false );

	if ( ! is_array( $stored_rows ) || empty( $stored_rows ) ) {
		return array();
	}

	// Canonical storage: one meta row containing an array of role slugs.
	if ( 1 === count( $stored_rows ) && is_array( $stored_rows[0] ) ) {
		return array_values( $stored_rows[0] );
	}

	$roles = array();

	foreach ( $stored_rows as $stored ) {
		if ( is_array( $stored ) ) {
			$roles = array_merge( $roles, array_values( $stored ) );
		} elseif ( is_string( $stored ) && '' !== $stored ) {
			$roles[] = $stored;
		}
	}

	$roles = array_values( array_unique( array_map( 'members_sanitize_role', $roles ) ) );

	return $roles;
}

/**
 * Returns access roles for a post, converting legacy `_role` meta when needed.
 *
 * @since  3.2.22
 * @access public
 * @param  int  $post_id  Post ID.
 * @return array
 */
function members_get_post_roles_for_display( $post_id ) {

	$roles = members_get_post_roles( $post_id );

	if ( empty( $roles ) ) {
		$converted = members_convert_old_post_meta( $post_id );

		if ( $converted ) {
			$roles = $converted;
		}
	}

	return is_array( $roles ) ? $roles : array();
}

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
			$roles = members_sanitize_post_roles( $legacy );
		}
	}

	return is_array( $roles ) ? $roles : array();
}

/**
 * Normalizes a stored access-role meta value for REST (no DB writes).
 *
 * @since  3.2.22
 * @access public
 * @param  mixed  $value  Meta value from the database.
 * @return array
 */
function members_prepare_access_roles_for_rest( $value ) {

	return members_normalize_post_roles_value( $value );
}

/**
 * Coerces legacy role meta shapes into a role slug array.
 *
 * @since  3.2.22
 * @access public
 * @param  mixed  $value  Meta value from the database.
 * @return array
 */
function members_normalize_post_roles_value( $value ) {

	if ( is_array( $value ) ) {
		return members_sanitize_post_roles( $value );
	}

	if ( is_string( $value ) && '' !== $value ) {
		return members_sanitize_post_roles( array( $value ) );
	}

	return array();
}

/**
 * Transient key for a role lock failure tied to the current user and post.
 *
 * @since  3.2.22
 * @access public
 * @param  int  $post_id  Post ID.
 * @return string
 */
function members_post_roles_lock_failed_transient_key( $post_id ) {

	return 'members_cp_roles_lock_' . get_current_user_id() . '_' . (int) $post_id;
}

/**
 * Stores a flag so the edit screen can show a lock failure notice.
 *
 * @since  3.2.22
 * @access public
 * @param  int  $post_id  Post ID.
 * @return void
 */
function members_flag_post_roles_lock_failure( $post_id ) {

	set_transient(
		members_post_roles_lock_failed_transient_key( $post_id ),
		1,
		MINUTE_IN_SECONDS
	);
}

/**
 * Reads and clears a pending lock failure notice for the current user and post.
 *
 * @since  3.2.22
 * @access public
 * @param  int  $post_id  Post ID.
 * @return bool True when a notice was pending.
 */
function members_consume_post_roles_lock_failed_notice( $post_id ) {

	$transient_key = members_post_roles_lock_failed_transient_key( $post_id );

	if ( ! get_transient( $transient_key ) ) {
		return false;
	}

	delete_transient( $transient_key );

	return true;
}

/**
 * Sanitizes one or more post access role slugs for storage.
 *
 * Registered meta for `_members_access_role` is a single array value in REST.
 * Role slugs are normalized but not dropped when the role no longer exists so
 * deleted custom roles keep their permission assignment until an editor removes it.
 *
 * @since  3.2.22
 * @access public
 * @param  mixed  $roles  Role slug or list of role slugs.
 * @return array
 */
function members_sanitize_post_roles( $roles ) {

	if ( ! is_array( $roles ) ) {
		$roles = array( $roles );
	}

	$roles = array_filter( $roles, 'is_string' );

	$roles = array_values( array_map( 'members_sanitize_role', $roles ) );

	$roles = array_filter(
		$roles,
		function ( $role ) {
			return '' !== $role;
		}
	);

	return array_values( array_unique( $roles ) );
}

/**
 * Returns role slugs assigned to a post that are not in the current role registry.
 *
 * @since  3.2.22
 * @access public
 * @param  int          $post_id  Post ID.
 * @param  \WP_Post|null $post     Optional post object for the members_wp_roles filter.
 * @return array
 */
function members_get_unknown_post_role_slugs( $post_id, $post = null ) {

	$post = $post instanceof \WP_Post ? $post : get_post( $post_id );

	if ( ! $post ) {
		return array();
	}

	$known_roles = apply_filters( 'members_wp_roles', wp_roles()->role_names, members_get_post_for_content_permissions( $post ) );
	$roles       = members_get_post_roles( $post_id );

	return array_values(
		array_filter(
			$roles,
			function ( $role ) use ( $known_roles ) {
				return ! isset( $known_roles[ $role ] );
			}
		)
	);
}

/**
 * Keeps unknown (e.g. deleted custom) role slugs when saving visible role checkboxes.
 *
 * @since  3.2.22
 * @access public
 * @param  int          $post_id  Post ID.
 * @param  array        $roles    Sanitized role slugs from the current save request.
 * @param  \WP_Post|null $post     Optional post object for the members_wp_roles filter.
 * @return array
 */
function members_merge_unknown_post_roles( $post_id, array $roles, $post = null ) {

	return array_values( array_unique( array_merge( $roles, members_get_unknown_post_role_slugs( $post_id, $post ) ) ) );
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
 * Whether the current request is persisting an autosave (classic or REST).
 *
 * @since  3.2.22
 * @access public
 * @param  int  $post_id  Post ID being saved.
 * @return bool
 */
function members_is_content_permissions_autosave( $post_id = 0 ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return true;
	}

	$post_id = (int) $post_id;

	if ( $post_id && wp_is_post_autosave( $post_id ) ) {
		return true;
	}

	if ( ! empty( $GLOBALS['members_cp_rest_autosave'] ) ) {
		return true;
	}

	return false;
}

/**
 * Marks a REST autosave request so role locks can be bypassed safely.
 *
 * @since  3.2.22
 * @access public
 * @param  mixed             $result   Response to replace.
 * @param  \WP_REST_Server   $server   REST server instance.
 * @param  \WP_REST_Request  $request  Request used to generate the response.
 * @return mixed
 */
function members_cp_rest_detect_autosave( $result, $server, $request ) {

	if ( $request instanceof \WP_REST_Request && false !== strpos( $request->get_route(), '/autosaves' ) ) {
		$GLOBALS['members_cp_rest_autosave'] = true;
	}

	return $result;
}

add_filter( 'rest_pre_dispatch', 'members_cp_rest_detect_autosave', 10, 3 );

/**
 * Clears per-request REST autosave state.
 *
 * @since  3.2.22
 * @access public
 * @param  \WP_REST_Response|mixed  $result   Result to send.
 * @param  \WP_REST_Server          $server   REST server instance.
 * @param  \WP_REST_Request         $request  Request used to generate the response.
 * @return \WP_REST_Response|mixed
 */
function members_cp_rest_cleanup_request_state( $result, $server, $request ) {

	unset( $GLOBALS['members_cp_rest_autosave'] );

	return $result;
}

add_filter( 'rest_post_dispatch', 'members_cp_rest_cleanup_request_state', 999, 3 );

/**
 * Adds a REST response flag when a role lock failure occurred during the request.
 *
 * @since  3.2.22
 * @access public
 * @param  \WP_REST_Response|mixed  $result   Result to send.
 * @param  \WP_REST_Server          $server   REST server instance.
 * @param  \WP_REST_Request         $request  Request used to generate the response.
 * @return \WP_REST_Response|mixed
 */
function members_cp_rest_add_roles_lock_failure_flag( $result, $server, $request ) {

	if ( ! ( $result instanceof \WP_REST_Response ) || empty( $GLOBALS['members_cp_roles_lock_failed'] ) ) {
		return $result;
	}

	$data = $result->get_data();

	if ( is_array( $data ) ) {
		$data['members_cp_roles_lock_failed'] = true;
		$result->set_data( $data );
	} elseif ( is_object( $data ) ) {
		$data->members_cp_roles_lock_failed = true;
	}

	unset( $GLOBALS['members_cp_roles_lock_failed'] );

	return $result;
}

add_filter( 'rest_post_dispatch', 'members_cp_rest_add_roles_lock_failure_flag', 10, 3 );

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
 * Option name used for a cross-request post role lock (add_option is atomic in MySQL).
 *
 * @since  3.2.22
 * @access public
 * @param  int  $post_id  Post ID.
 * @return string
 */
function members_post_roles_lock_option_key( $post_id ) {

	return 'members_cp_roles_lock_' . (int) $post_id;
}

/**
 * Acquires a lock that is visible across concurrent HTTP requests.
 *
 * Uses the object cache when a persistent drop-in is active; otherwise uses
 * add_option(), which is stored in the database and works on default installs.
 *
 * @since  3.2.22
 * @access public
 * @param  int  $post_id  Post ID.
 * @param  int  $timeout  Seconds to wait for the lock.
 * @return array|false Lock handle, or false when unavailable.
 */
function members_acquire_post_roles_lock( $post_id, $timeout = 5 ) {

	$post_id  = (int) $post_id;
	$timeout  = max( 1, (int) $timeout );
	$deadline = microtime( true ) + $timeout;
	$cache_key = 'post_' . $post_id;

	while ( microtime( true ) < $deadline ) {

		if ( wp_using_ext_object_cache() && wp_cache_add( $cache_key, time(), 'members_post_roles_lock', $timeout + 25 ) ) {
			return array(
				'storage' => 'cache',
				'key'     => $cache_key,
			);
		}

		if ( ! wp_using_ext_object_cache() ) {
			$option_key = members_post_roles_lock_option_key( $post_id );
			$expires    = time() + $timeout + 25;

			// Options are cached per request; clear before each attempt so concurrent
			// lock acquire/release in other requests is visible while polling.
			wp_cache_delete( $option_key, 'options' );

			if ( add_option( $option_key, $expires, '', 'no' ) ) {
				return array(
					'storage' => 'option',
					'key'     => $option_key,
				);
			}

			wp_cache_delete( $option_key, 'options' );
			$stale_expires = (int) get_option( $option_key, 0 );

			if ( $stale_expires && $stale_expires < time() ) {
				delete_option( $option_key );
			}
		}

		usleep( 50000 );
	}

	return false;
}

/**
 * Releases a lock acquired by members_acquire_post_roles_lock().
 *
 * @since  3.2.22
 * @access public
 * @param  array|false  $lock  Lock handle from members_acquire_post_roles_lock().
 * @return void
 */
function members_release_post_roles_lock( $lock ) {

	if ( ! is_array( $lock ) || empty( $lock['storage'] ) || empty( $lock['key'] ) ) {
		return;
	}

	if ( 'cache' === $lock['storage'] ) {
		wp_cache_delete( $lock['key'], 'members_post_roles_lock' );
		return;
	}

	if ( 'option' === $lock['storage'] ) {
		delete_option( $lock['key'] );
	}
}

/**
 * Runs a callback while holding a short-lived lock for post role meta updates.
 *
 * Reentrant for the same post ID within one request (e.g. migration calling
 * members_get_post_roles_for_display() which may convert legacy meta).
 *
 * @since  3.2.22
 * @access public
 * @param  int        $post_id   Post ID.
 * @param  callable   $callback  Callback that performs the update.
 * @return mixed|false Callback return value, or false when the lock is unavailable.
 */
function members_with_post_roles_lock( $post_id, $callback ) {

	static $locks_held = array();

	$post_id = (int) $post_id;

	if ( ! $post_id || ! is_callable( $callback ) ) {
		return false;
	}

	if ( ! empty( $locks_held[ $post_id ] ) ) {
		return call_user_func( $callback );
	}

	$lock = members_acquire_post_roles_lock( $post_id, 5 );

	if ( false === $lock ) {
		return false;
	}

	try {
		$locks_held[ $post_id ] = true;

		return call_user_func( $callback );
	} finally {
		unset( $locks_held[ $post_id ] );
		members_release_post_roles_lock( $lock );
	}
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

	$role = members_sanitize_role( $role );

	$result = members_with_post_roles_lock(
		$post_id,
		function () use ( $post_id, $role ) {
			$roles = members_get_post_roles( $post_id );

			if ( in_array( $role, $roles, true ) ) {
				return false;
			}

			$roles[] = $role;
			members_set_post_roles( $post_id, $roles );

			return true;
		}
	);

	return false === $result ? false : $result;
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

	$role = members_sanitize_role( $role );

	$result = members_with_post_roles_lock(
		$post_id,
		function () use ( $post_id, $role ) {
			$roles = members_get_post_roles( $post_id );
			$index = array_search( $role, $roles, true );

			if ( false === $index ) {
				return false;
			}

			unset( $roles[ $index ] );
			members_set_post_roles( $post_id, array_values( $roles ) );

			return true;
		}
	);

	return false === $result ? false : (bool) $result;
}

/**
 * Whether two role lists are equivalent after sanitization.
 *
 * @since  3.2.22
 * @access public
 * @param  array|string  $roles_a  Role list or single role slug.
 * @param  array|string  $roles_b  Role list or single role slug.
 * @return bool
 */
function members_post_roles_are_equal( $roles_a, $roles_b ) {

	$roles_a = array_values( array_map( 'members_sanitize_role', (array) $roles_a ) );
	$roles_b = array_values( array_map( 'members_sanitize_role', (array) $roles_b ) );

	sort( $roles_a );
	sort( $roles_b );

	return $roles_a === $roles_b;
}

/**
 * Whether a stored `_members_access_role` row matches the canonical role list.
 *
 * @since  3.2.22
 * @access public
 * @param  mixed   $stored  Meta value from get_post_meta().
 * @param  array   $roles   Canonical sanitized role slugs.
 * @return bool
 */
function members_stored_access_role_row_matches( $stored, array $roles ) {

	if ( is_array( $stored ) ) {
		return members_post_roles_are_equal( $stored, $roles );
	}

	if ( is_string( $stored ) && '' !== $stored ) {
		return members_post_roles_are_equal( array( $stored ), $roles );
	}

	return false;
}

/**
 * Sets a post's access roles given an array of roles.
 *
 * Storage format: one `_members_access_role` post meta row containing a PHP array of role
 * slugs (required for block editor REST). Legacy sites used multiple rows with one slug per
 * row; use members_maybe_migrate_access_role_storage() to upgrade existing data.
 *
 * Callers that may run concurrently (REST, classic save) should wrap this in
 * members_with_post_roles_lock(). members_add_post_role() and members_remove_post_role()
 * already acquire the lock before calling this function.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $post_id
 * @param  array   $roles
 * @global object  $wp_roles
 * @return void
 */
function members_set_post_roles( $post_id, $roles ) {

	$roles = members_sanitize_post_roles( $roles );

	if ( empty( $roles ) ) {
		delete_post_meta( $post_id, '_members_access_role' );
		return;
	}

	// Write first so concurrent reads never see a transient empty meta value.
	update_post_meta( $post_id, '_members_access_role', $roles );

	global $wpdb;

	$stored_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_members_access_role' ORDER BY meta_id ASC",
			$post_id
		)
	);

	if ( ! is_array( $stored_rows ) || count( $stored_rows ) <= 1 ) {
		return;
	}

	$kept_meta_id = null;

	foreach ( $stored_rows as $row ) {
		$stored = maybe_unserialize( $row->meta_value );

		if ( ! members_stored_access_role_row_matches( $stored, $roles ) ) {
			delete_metadata_by_mid( 'post', $row->meta_id );
			continue;
		}

		// Prefer the canonical array row over a legacy single-string row with the same roles.
		if ( is_array( $stored ) ) {
			if ( null !== $kept_meta_id ) {
				delete_metadata_by_mid( 'post', $kept_meta_id );
			}

			$kept_meta_id = $row->meta_id;
			continue;
		}

		if ( null === $kept_meta_id ) {
			$kept_meta_id = $row->meta_id;
			continue;
		}

		delete_metadata_by_mid( 'post', $row->meta_id );
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

	$old_roles = get_post_meta( $post_id, '_role', false );

	if ( empty( $old_roles ) ) {
		return false;
	}

	// Access roles already exist; remove stale legacy meta only.
	if ( ! empty( members_get_post_roles( $post_id ) ) ) {
		delete_post_meta( $post_id, '_role' );
		return false;
	}

	$converted = members_with_post_roles_lock(
		$post_id,
		function () use ( $post_id ) {
			$old_roles = get_post_meta( $post_id, '_role', false );

			if ( empty( $old_roles ) ) {
				$roles = members_get_post_roles( $post_id );

				return ! empty( $roles ) ? $roles : false;
			}

			if ( ! empty( members_get_post_roles( $post_id ) ) ) {
				delete_post_meta( $post_id, '_role' );

				return false;
			}

			delete_post_meta( $post_id, '_role' );
			members_set_post_roles( $post_id, $old_roles );

			$roles = members_get_post_roles( $post_id );

			return ! empty( $roles ) ? $roles : $old_roles;
		}
	);

	if ( $converted ) {
		return $converted;
	}

	return false;
}

/**
 * Cached wrapper for members_can_current_user_view_post() during REST requests.
 *
 * @since  3.2.22
 * @access public
 * @param  int  $post_id  Post ID.
 * @return bool
 */
function members_rest_can_current_user_view_post( $post_id ) {

	static $cache = array();

	$post_id = (int) $post_id;

	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$cache[ $post_id ] = members_can_current_user_view_post( $post_id );

	return $cache[ $post_id ];
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

	// Users who manage permissions can view every post in the editor REST context.
	if ( current_user_can( 'restrict_content' ) ) {
		return $posts;
	}

	foreach ( $posts as $key => $post ) {
		if ( ! members_rest_can_current_user_view_post( $post->ID ) ) {
			unset( $posts[ $key ] );
		}
	}

	return array_values( $posts );
}

/**
 * Database storage version for `_members_access_role` (single serialized array per post).
 *
 * @since  3.2.22
 */
if ( ! defined( 'MEMBERS_ACCESS_ROLES_STORAGE_VERSION' ) ) {
	define( 'MEMBERS_ACCESS_ROLES_STORAGE_VERSION', 2 );
}

/**
 * Whether a post still uses legacy `_members_access_role` storage.
 *
 * @since  3.2.22
 * @access public
 * @param  int  $post_id  Post ID.
 * @return bool
 */
function members_post_needs_access_role_storage_migration( $post_id ) {

	$rows = get_post_meta( $post_id, '_members_access_role', false );

	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return false;
	}

	if ( count( $rows ) > 1 ) {
		return true;
	}

	$stored = $rows[0];

	return ! is_array( $stored );
}

/**
 * Whether any posts still need `_members_access_role` storage migration.
 *
 * @since  3.2.22
 * @access public
 * @return bool
 */
function members_access_role_storage_migration_pending() {

	global $wpdb;

	$not_array = $wpdb->esc_like( 'a:' ) . '%';

	$pending = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key = '_members_access_role'
			AND meta_value NOT LIKE %s
			LIMIT 1",
			$not_array
		)
	);

	if ( ! empty( $pending ) ) {
		return true;
	}

	$pending = $wpdb->get_var(
		"SELECT post_id FROM {$wpdb->postmeta}
		WHERE meta_key = '_members_access_role'
		GROUP BY post_id
		HAVING COUNT(*) > 1
		LIMIT 1"
	);

	return ! empty( $pending );
}

/**
 * Migrates a batch of posts from legacy `_members_access_role` rows to a single array value.
 *
 * @since  3.2.22
 * @access public
 * @param  int  $limit  Maximum number of posts to migrate in this batch.
 * @return int Number of posts migrated.
 */
function members_migrate_access_role_storage_batch( $limit = 100 ) {

	global $wpdb;

	$limit = max( 1, (int) $limit );

	$post_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT post_id FROM (
				SELECT DISTINCT post_id
				FROM {$wpdb->postmeta}
				WHERE meta_key = '_members_access_role'
				AND meta_value NOT LIKE %s
				UNION
				SELECT post_id
				FROM {$wpdb->postmeta}
				WHERE meta_key = '_members_access_role'
				GROUP BY post_id
				HAVING COUNT(*) > 1
			) AS members_pending_access_role_migration
			LIMIT %d",
			$wpdb->esc_like( 'a:' ) . '%',
			$limit
		)
	);

	if ( empty( $post_ids ) ) {
		return 0;
	}

	$migrated = 0;

	foreach ( $post_ids as $post_id ) {
		$post_id = (int) $post_id;

		if ( ! $post_id || ! members_post_needs_access_role_storage_migration( $post_id ) ) {
			continue;
		}

		$migrated_ok = members_with_post_roles_lock(
			$post_id,
			function () use ( $post_id ) {
				$roles = members_get_post_roles_for_display( $post_id );
				members_set_post_roles( $post_id, $roles );

				return true;
			}
		);

		if ( false !== $migrated_ok ) {
			$migrated++;
		}
	}

	return $migrated;
}

/**
 * Runs batched `_members_access_role` storage migration after plugin updates.
 *
 * @since  3.2.22
 * @access public
 * @return void
 */
function members_maybe_migrate_access_role_storage() {

	if ( (int) get_option( 'members_access_roles_storage_version', 0 ) >= MEMBERS_ACCESS_ROLES_STORAGE_VERSION ) {
		return;
	}

	if ( wp_installing() ) {
		return;
	}

	// Avoid adding latency to public requests; migrate in admin or cron contexts.
	if ( ! is_admin() && ! wp_doing_cron() ) {
		return;
	}

	// Rate-limit batches so the discovery query does not run on every admin request.
	if ( get_transient( 'members_access_role_migration_lock' ) ) {
		return;
	}

	set_transient( 'members_access_role_migration_lock', 1, 5 * MINUTE_IN_SECONDS );

	members_migrate_access_role_storage_batch( 100 );

	if ( ! members_access_role_storage_migration_pending() ) {
		update_option( 'members_access_roles_storage_version', MEMBERS_ACCESS_ROLES_STORAGE_VERSION, false );
		delete_transient( 'members_access_role_migration_lock' );
	}
}

add_action( 'wp_loaded', 'members_maybe_migrate_access_role_storage' );

/**
 * Persists `_members_access_role` via members_set_post_roles() when updated through meta APIs.
 *
 * @since  3.2.22
 * @access public
 * @param  null|bool  $check        Short-circuit return value.
 * @param  int        $object_id    Post ID.
 * @param  string     $meta_key     Meta key.
 * @param  mixed      $meta_value   New meta value.
 * @param  mixed      $prev_value   Previous meta value.
 * @return null|bool
 */
function members_filter_update_post_roles_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value ) {

	unset( $prev_value );

	if ( '_members_access_role' !== $meta_key || ! members_content_permissions_enabled() ) {
		return $check;
	}

	$object_id = (int) $object_id;

	if ( ! $object_id || ! get_post( $object_id ) ) {
		return $check;
	}

	static $internal_update = array();

	if ( ! empty( $internal_update[ $object_id ] ) ) {
		return $check;
	}

	$roles = members_merge_unknown_post_roles( $object_id, members_sanitize_post_roles( $meta_value ) );

	$save_callback = function () use ( $object_id, $roles, &$internal_update ) {
		$internal_update[ $object_id ] = true;

		try {
			members_set_post_roles( $object_id, $roles );

			return true;
		} finally {
			unset( $internal_update[ $object_id ] );
		}
	};

	if ( members_is_content_permissions_autosave( $object_id ) ) {
		$save_callback();

		return true;
	}

	$saved = members_with_post_roles_lock( $object_id, $save_callback );

	if ( false === $saved ) {
		members_flag_post_roles_lock_failure( $object_id );

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$GLOBALS['members_cp_roles_lock_failed'] = true;
		}

		return false;
	}

	return true;
}

add_filter( 'update_post_metadata', 'members_filter_update_post_roles_metadata', 10, 5 );
