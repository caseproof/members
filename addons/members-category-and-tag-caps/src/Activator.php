<?php
/**
 * Plugin Activator.
 *
 * Runs the plugin activation routine.
 *
 * @package   MembersCategoryAndTagCaps
 * @author    The MemberPress Team 
 * @copyright 2019, The MemberPress Team
 * @link      https://members-plugin.com/
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

		// WordPress normally maps these caps to `manage_categories` (managing terms)
		// and `edit_posts` (assigning them). Once this add-on is on they stand on
		// their own, so give them to every role that could already do those things,
		// otherwise editors, authors and contributors lose access.
		$caps = [
			'manage_categories' => [
				'manage_categories',
				'edit_categories',
				'delete_categories',
				'manage_post_tags',
				'edit_post_tags',
				'delete_post_tags',
			],
			'edit_posts' => [
				'assign_categories',
				'assign_post_tags',
			],
		];

		foreach ( wp_roles()->role_objects as $role ) {

			foreach ( $caps as $had => $new_caps ) {

				if ( 'administrator' !== $role->name && empty( $role->capabilities[ $had ] ) ) {
					continue;
				}

				foreach ( $new_caps as $cap ) {

					// Leave caps a site owner has already granted or denied alone.
					if ( ! isset( $role->capabilities[ $cap ] ) ) {
						$role->add_cap( $cap );
					}
				}
			}
		}
	}
}
