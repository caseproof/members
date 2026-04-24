<?php
namespace Members\AddOns\PrivacyCaps;

defined( 'ABSPATH' ) || exit;

class Activator {

	public static function activate() {

		if ( is_multisite() ) {
			return;
		}

		$role = get_role( 'administrator' );

		if ( $role ) {
			$role->add_cap( 'export_others_personal_data' );
			$role->add_cap( 'erase_others_personal_data'  );
			$role->add_cap( 'manage_privacy_options'      );
		}
	}
}
