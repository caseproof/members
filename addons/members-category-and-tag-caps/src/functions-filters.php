<?php
/**
 * Plugin filters.
 *
 * @package   MembersCategoryAndTagCaps
 * @author    The MemberPress Team 
 * @copyright 2019, The MemberPress Team
 * @link      https://members-plugin.com/-category-and-tag-caps
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\CategoryAndTagCaps;

// Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Filters the category and tag registration arguments and overwrites their
 * capabilities with custom ones.
 *
 * @since  1.0.0
 * @access public
 * @param  array  $args      Array of taxonomy options.
 * @param  string $taxonomy  Name/Slug of the taxonomy.
 * @return array
 */
add_filter( 'register_taxonomy_args', function( $args, $taxonomy ) {

	if ( 'category' === $taxonomy || 'post_tag' === $taxonomy ) {

		if ( ! isset( $args['capabilities'] ) || ! is_array( $args['capabilities'] ) ) {
			$args['capabilities'] = [];
		}

		if ( 'category' === $taxonomy ) {
			$args['capabilities']['manage_terms'] = 'manage_categories';
			$args['capabilities']['assign_terms'] = 'assign_categories';
			$args['capabilities']['edit_terms']   = 'edit_categories';
			$args['capabilities']['delete_terms'] = 'delete_categories';
		} elseif ( 'post_tag' === $taxonomy ) {
			$args['capabilities']['manage_terms'] = 'manage_post_tags';
			$args['capabilities']['assign_terms'] = 'assign_post_tags';
			$args['capabilities']['edit_terms']   = 'edit_post_tags';
			$args['capabilities']['delete_terms'] = 'delete_post_tags';
		}
	}

	return $args;

}, 10, 2 );

/**
 * Filters `map_meta_cap` to make sure core recognizes the appropriate capabilities
 * when looking for category and tag permission.
 *
 * @since  1.0.0
 * @access public
 * @param  array   $caps  Array of capabilities the user must have.
 * @param  string  $cap   The current capability being checked.
 * @return array
 */
add_filter( 'map_meta_cap', function( $caps, $cap ) {

	// Category caps.
	if ( 'manage_categories' === $cap ) {

		return [ get_taxonomy( 'category' )->cap->manage_terms ];

	} elseif ( 'assign_categories' === $cap ) {

		return [ get_taxonomy( 'category' )->cap->assign_terms ];

	} elseif ( 'edit_categories' === $cap ) {

		return [ get_taxonomy( 'category' )->cap->edit_terms ];

	} elseif ( 'delete_categories' === $cap ) {

		return [ get_taxonomy( 'category' )->cap->delete_terms ];

	// Tag caps.
	} elseif ( 'manage_post_tags' === $cap ) {

		return [ get_taxonomy( 'post_tag' )->cap->manage_terms ];

	} elseif ( 'assign_post_tags' === $cap ) {

		return [ get_taxonomy( 'post_tag' )->cap->assign_terms ];

	} elseif ( 'edit_post_tags' === $cap ) {

		return [ get_taxonomy( 'post_tag' )->cap->edit_terms ];

	} elseif ( 'delete_post_tags' === $cap ) {

		return [ get_taxonomy( 'post_tag' )->cap->delete_terms ];
	}

	return $caps;

}, 10, 2 );

/**
 * Grant post-tag granular caps to every role that can manage categories.
 *
 * Core mapped post_tag `manage_terms` to `manage_categories`. After this add-on remaps
 * post_tag to `manage_post_tags`, roles cloned from Administrator (or otherwise missing the
 * new primitives) still had `manage_categories` only — Admin Menus then showed "no access"
 * for Tags while `current_user_can()` could still pass if another role granted the tag caps.
 *
 * Sites that need least-privilege (category management without automatic tag caps) can disable
 * the sync with {@see 'members_ctc_sync_post_tag_caps_for_manage_categories_roles'}.
 *
 * @since 1.0.1
 * @return void
 */
function sync_post_tag_caps_for_roles_with_manage_categories() {
	/**
	 * Whether to grant granular post_tag capabilities to every role that has `manage_categories`.
	 *
	 * Default true keeps compatibility with earlier add-on releases. Return false to skip the
	 * automatic grant (use custom role/cap management instead).
	 *
	 * @since 1.0.2
	 * @param bool $sync Whether to add tag caps to qualifying roles.
	 */
	if ( ! apply_filters( 'members_ctc_sync_post_tag_caps_for_manage_categories_roles', true ) ) {
		return;
	}

	$tag_caps = array( 'manage_post_tags', 'assign_post_tags', 'edit_post_tags', 'delete_post_tags' );
	foreach ( wp_roles()->roles as $slug => $_role_info ) {
		$role = get_role( $slug );
		if ( ! $role || ! $role->has_cap( 'manage_categories' ) ) {
			continue;
		}
		foreach ( $tag_caps as $cap ) {
			$role->add_cap( $cap );
		}
	}
}

/**
 * One-time migration for sites that activated the add-on before tag caps were synced.
 *
 * @since 1.0.1
 * @return void
 */
function maybe_sync_post_tag_caps_roles_migration() {
	if ( '1' === get_option( 'members_ctc_sync_post_tag_caps_roles_v1', '' ) ) {
		return;
	}
	if ( apply_filters( 'members_ctc_sync_post_tag_caps_for_manage_categories_roles', true ) ) {
		sync_post_tag_caps_for_roles_with_manage_categories();
	}
	update_option( 'members_ctc_sync_post_tag_caps_roles_v1', '1', true );
}

add_action( 'init', __NAMESPACE__ . '\maybe_sync_post_tag_caps_roles_migration', 20 );
