<?php
namespace Members\AddOns\PrivacyCaps;

defined( 'ABSPATH' ) || exit;

class Activator {

	public static function activate() {

		if ( is_multisite() ) {
			return;
		}

		// On single site WordPress maps these caps to `manage_options`, so every role
		// with it could use the privacy tools. Keep it that way once they stand on
		// their own.
		foreach ( wp_roles()->role_objects as $role ) {

			if ( 'administrator' !== $role->name && empty( $role->capabilities['manage_options'] ) ) {
				continue;
			}

			foreach ( [ 'export_others_personal_data', 'erase_others_personal_data', 'manage_privacy_options' ] as $cap ) {

				// Leave caps a site owner has already granted or denied alone.
				if ( ! isset( $role->capabilities[ $cap ] ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}
}
