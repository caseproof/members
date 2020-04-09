<?php

namespace Members\AddOns\PrivacyCaps;

# Add actions and filters.
add_action( 'members_register_caps', __NAMESPACE__ . '\register_caps'          );
add_filter( 'map_meta_cap',          __NAMESPACE__ . '\map_meta_cap',    95, 2 );

# Register activation/deactivation hooks.
register_activation_hook( __FILE__, __NAMESPACE__ . '\activation' );

/**
 * Adds the privacy capabilities to the administrator role whenever the plugin
 * is activated.
 *
 * On Multisite, we're not adding any caps to a role because these caps are, by
 * default, only allowed for people with the `manage_network` capability, which
 * is a Super Admin cap.  If network owners want sub-site administrators to be
 * able to have thse caps, they should be added to the role via the edit role
 * screen in Members.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function activation() {

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

/**
 * Registers capabilities with the Members plugin.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function register_caps() {

	members_register_cap( 'manage_privacy_options', [
		'label' => __( 'Manage Privacy Options', 'members' ),
		'group' => 'general'
 	] );

	members_register_cap( 'erase_others_personal_data', [
		'label' => __( "Erase Others' Personal Data", 'members' ),
		'group' => 'user'
	] );

	members_register_cap( 'export_others_personal_data', [
		'label' => __( "Export Others' Personal Data", 'members' ),
		'group' => 'user'
	] );
}

/**
 * The privacy caps are mapped to `manage_options` (or `manage_network` in the
 * case of multisite) by default, effectively making them meta caps.  We're
 * turning the caps into primitive caps.
 *
 * @since  1.0.0
 * @access public
 * @param  array   $caps
 * @param  string  $cap
 * @return array
 */
function map_meta_cap( $caps, $cap ) {

 	$privacy_caps = [
 		'export_others_personal_data',
 		'erase_others_personal_data',
 		'manage_privacy_options'
 	];

 	if ( in_array( $cap, $privacy_caps ) ) {

		// The cap should map back to itself.
		$caps = [ $cap ];

		// Core WP requires the 'delete_users' cap when erasing a user's
		// personal data. This becomes even more important on multisite
		// where even a sub-site admin might not be able to delete users.
		// So, we're adding this as a required cap too.
		if ( 'erase_others_personal_data' === $cap ) {
			$caps[] = 'delete_users';
		}
 	}

 	return $caps;
}
