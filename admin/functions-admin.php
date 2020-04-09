<?php
/**
 * General admin functionality.
 *
 * @package    Members
 * @subpackage Admin
 * @author     Justin Tadlock <justintadlock@gmail.com>
 * @copyright  Copyright (c) 2009 - 2018, Justin Tadlock
 * @link       https://themehybrid.com/plugins/members
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

# Register scripts/styles.
add_action( 'admin_enqueue_scripts', 'members_admin_register_scripts', 0 );
add_action( 'admin_enqueue_scripts', 'members_admin_register_styles',  0 );

/**
 * Get an Underscore JS template.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $name
 * @return bool
 */
function members_get_underscore_template( $name ) {
	require_once( members_plugin()->dir . "admin/tmpl/{$name}.php" );
}

/**
 * Registers custom plugin scripts.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function members_admin_register_scripts() {

	$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	wp_register_script( 'members-settings',  members_plugin()->uri . "js/settings{$min}.js",  array( 'jquery'  ), '', true );
	wp_register_script( 'members-edit-post', members_plugin()->uri . "js/edit-post{$min}.js", array( 'jquery'  ), '', true );
	wp_register_script( 'members-edit-role', members_plugin()->uri . "js/edit-role{$min}.js", array( 'postbox', 'wp-util' ), '', true );

	// Localize our script with some text we want to pass in.
	$i18n = array(
		'button_role_edit' => esc_html__( 'Edit',                'members' ),
		'button_role_ok'   => esc_html__( 'OK',                  'members' ),
		'label_grant_cap'  => esc_html__( 'Grant %s capability', 'members' ),
		'label_deny_cap'   => esc_html__( 'Deny %s capability',  'members' ),
		'ays_delete_role'  => esc_html__( 'Are you sure you want to delete this role? This is a permanent action and cannot be undone.', 'members' ),
		'hidden_caps'      => members_get_hidden_caps()
	);

	wp_localize_script( 'members-edit-role', 'members_i18n', $i18n );
}

/**
 * Registers custom plugin scripts.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function members_admin_register_styles() {

	$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	wp_register_style( 'members-admin', members_plugin()->uri . "css/admin{$min}.css" );
}

/**
 * Function for safely deleting a role and transferring the deleted role's users to the default
 * role.  Note that this function can be extremely intensive.  Whenever a role is deleted, it's
 * best for the site admin to assign the user's of the role to a different role beforehand.
 *
 * @since  0.2.0
 * @access public
 * @param  string  $role
 * @return void
 */
function members_delete_role( $role ) {

	// Get the default role.
	$default_role = get_option( 'default_role' );

	// Don't delete the default role. Site admins should change the default before attempting to delete the role.
	if ( $role == $default_role )
		return;

	// Get all users with the role to be deleted.
	$users = get_users( array( 'role' => $role ) );

	// Check if there are any users with the role we're deleting.
	if ( is_array( $users ) ) {

		// If users are found, loop through them.
		foreach ( $users as $user ) {

			// If the user has the role and no other roles, set their role to the default.
			if ( $user->has_cap( $role ) && 1 >= count( $user->roles ) )
				$user->set_role( $default_role );

			// Else, remove the role.
			else if ( $user->has_cap( $role ) )
				$user->remove_role( $role );
		}
	}

	// Remove the role.
	remove_role( $role );

	// Remove the role from the role factory.
	members_unregister_role( $role );
}

/**
 * Returns an array of all the user meta keys in the $wpdb->usermeta table.
 *
 * @since  0.2.0
 * @access public
 * @global object  $wpdb
 * @return array
 */
function members_get_user_meta_keys() {
	global $wpdb;

	return $wpdb->get_col( "SELECT meta_key FROM $wpdb->usermeta GROUP BY meta_key ORDER BY meta_key" );
}

add_action( 'admin_enqueue_scripts', 'members_add_pointers' );
/**
 * Adds helper pointers to the admin
 *
 * @return void
 */
function members_add_pointers() {

	$pointers = apply_filters( 'members_admin_pointers', array() );

	if ( empty( $pointers ) ) {
		return;
	}

	// Get dismissed pointers
	$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
	$valid_pointers =array();
 
	// Check pointers and remove dismissed ones.
	foreach ( $pointers as $pointer_id => $pointer ) {
 
		// Sanity check
		if ( in_array( $pointer_id, $dismissed ) || empty( $pointer )  || empty( $pointer_id ) || empty( $pointer['target'] ) || empty( $pointer['options'] ) ) {
			continue;
		}
 
		$pointer['pointer_id'] = $pointer_id;
 
		$valid_pointers['pointers'][] =  $pointer;
	}
 
	if ( empty( $valid_pointers ) ) {
		return;
	}
 
	wp_enqueue_style( 'wp-pointer' );
	wp_enqueue_script( 'members-pointers', members_plugin()->uri . '/js/members-pointers.min.js', array( 'wp-pointer' ) );
	wp_localize_script( 'members-pointers', 'membersPointers', $valid_pointers );
}

add_filter( 'members_admin_pointers', 'members_3_helper_pointer' );
/**
 * Adds a pointer for the Members 3.0 release
 *
 * @param  array 	$pointers 		Pointers
 *
 * @return array
 */
function members_3_helper_pointer( $pointers ) {
	ob_start();
	?>
	<h3><?php _e( 'Welcome to Members 3.0!', 'members' ); ?></h3>
	<p><?php _e( 'The new Members is here to deliver an easier experience and more advanced features.', 'members' ); ?></p>
	<p><?php _e( 'Don\'t worry, it will work the same as it always has for you!  We\'ve just made the following changes:', 'members' ); ?></p>
	<p><?php _e( '<strong>1.</strong> We\'ve centralized all of the main Members settings here. This will make things much easier to find and use.', 'members' ); ?></p>
	<p><?php _e( '<strong>2.</strong> All of our Add-ons are now <strong>freely</strong> included in Members! Just visit the Add-ons menu item here to start using these premium features.', 'members' ); ?></p>
	<p><?php _e( 'We\'re excited about these new changes and we hope they\'ll make your experience with Members even better!', 'members' ); ?></p>
	<p><?php _e( '- The MemberPress team', 'members' ); ?></p>
	<?php
	$content = ob_get_clean();
    $pointers['members_30'] = array(
        'target' => '#toplevel_page_members',
        'options' => array(
            'content' => $content,
            'position' => array( 
            	'edge' => 'left', 
            	'align' => 'center' 
            )
        )
    );
    return $pointers;
}