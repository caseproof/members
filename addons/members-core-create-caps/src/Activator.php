<?php
/**
 * Core Create Caps activator.
 *
 * @package    Members
 * @subpackage Core_Create_Caps
 */
namespace Members\Core_Create_Caps;

defined( 'ABSPATH' ) || exit;

/**
 * Runs when the add-on is turned on (and again on Reset Roles).
 */
class Activator {

	/**
	 * WordPress normally checks `edit_posts` / `edit_pages` for "Add New". Once
	 * this add-on is on it checks `create_posts` / `create_pages` instead, so give
	 * those to every role that could already add posts or pages. Otherwise
	 * everyone, administrators included, loses "Add New".
	 *
	 * @return void
	 */
	public static function activate() {

		$caps = [
			'edit_posts' => 'create_posts',
			'edit_pages' => 'create_pages',
		];

		foreach ( wp_roles()->role_objects as $role ) {

			foreach ( $caps as $had => $cap ) {

				// Leave caps a site owner has already granted or denied alone.
				if ( ! empty( $role->capabilities[ $had ] ) && ! isset( $role->capabilities[ $cap ] ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}
}
