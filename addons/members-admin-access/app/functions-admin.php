<?php
/**
 * Admin functions.
 *
 * @package   MembersAdminAccess
 * @author    The MemberPress Team 
 * @copyright Copyright (c) 2018, The MemberPress Team
 * @link      https://members-plugin.com/-admin-access
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Members\AddOns\AdminAccess;

# Redirect users without access.
add_action( 'admin_init', __NAMESPACE__ . '\access_check', 0 );

# Register custom settings views.
add_action( 'members_register_settings_views', __NAMESPACE__ . '\register_views' );

/**
 * Checks if the current user has access to the admin.  If not, it redirects them.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function access_check() {

	if ( ! current_user_has_access() && ! wp_doing_ajax() ) {
		wp_redirect( esc_url_raw( get_redirect_url() ) );
		exit;
	}

	// Override WooCommerce's admin redirect.
	add_filter( 'woocommerce_prevent_admin_access', '__return_false' );
}

/**
 * Registers custom settings views with the Members plugin.
 *
 * @since  1.0.0
 * @access public
 * @param  object  $manager
 * @return void
 */
function register_views( $manager ) {

	// Bail if not on the settings screen.
	if ( 'members-settings' !== $manager->name )
		return;

	require_once( app()->dir . 'app/class-view-settings.php' );

	// Register a view for the plugin settings.
	$manager->register_view(
		new View_Settings(
			'members_admin_access',
			[
				'label'    => esc_html__( 'Admin Access', 'members' ),
				'priority' => 15
			]
		)
	);
}
