<?php
/**
 * Plugin Activator.
 *
 * Runs the plugin activation routine.
 *
 * @package   MembersCategoryAndTagCaps
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-category-and-tag-caps
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\CategoryAndTagCaps;

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
	}
}
