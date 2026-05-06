<?php
/**
 * Plugin Activator.
 *
 * Runs the plugin activation routine.
 *
 * @package   MembersCategoryAndTagCaps
 * @author    The MemberPress Team 
 * @copyright 2019, The MemberPress Team
 * @link      https://members-plugin.com/-category-and-tag-caps
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */
namespace Members\CategoryAndTagCaps;

defined('ABSPATH') || exit;
/**
 * Activator class.
 *
 * @since  1.0.0
 * @access public
 */
class Activator {

	/**
	 * Runs necessary code when first activating the plugin.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public static function activate() {

		// Activator can run before addon.php loads filters (e.g. first activation).
		require_once __DIR__ . '/functions-filters.php';

		// Get the administrator role.
		$role = get_role( 'administrator' );

		// If the administrator role exists, add required capabilities
		// for the plugin.
		if ( ! empty( $role ) ) {

			$role->add_cap( 'manage_categories' );
			$role->add_cap( 'assign_categories' );
			$role->add_cap( 'edit_categories'   );
			$role->add_cap( 'delete_categories' );

			$role->add_cap( 'manage_post_tags' );
			$role->add_cap( 'assign_post_tags' );
			$role->add_cap( 'edit_post_tags'   );
			$role->add_cap( 'delete_post_tags' );
		}

		// Roles with manage_categories could manage tags before granular caps; keep parity.
		sync_post_tag_caps_for_roles_with_manage_categories();
	}
}
