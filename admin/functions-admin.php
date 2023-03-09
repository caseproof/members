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

/**
 * Check whether the MemberPress plugin is active.
 *
 * @return boolean
 */
function members_is_memberpress_active() {
	return defined( 'MEPR_PLUGIN_SLUG' );
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

add_action( 'in_admin_header', 'members_admin_header', 0 );
/**
 * Branded header
 *
 * @return void
 */
function members_admin_header() {

	if ( members_is_memberpress_active() || empty( $_GET['page'] ) || ! in_array( $_GET['page'], array( 'roles', 'members', 'members-settings', 'members-about' ) ) ) {
		return;
	}

	$dismissed = get_option( 'members_dismiss_upgrade_header', false );

	if ( ! empty( $dismissed ) ) {
		return;
	}

    ?>

    <div class="members-upgrade-header" id="members-upgrade-header">
    	<span id="close-members-upgrade-header">X</span>
    	<?php _e( 'You\'re using Members. To unlock more features, consider <a href="https://memberpress.com/plans/pricing/?utm_source=members&utm_medium=link&utm_campaign=in_plugin&utm_content=pro_features">adding MemberPress.</a>' ); ?>
    </div>

    <div id="members-admin-header"><img class="members-logo" src="<?php echo members_plugin()->uri . 'img/Members-header.svg'; ?>" /></div>

    <script>
    	jQuery(document).ready(function($) {
    		$('#close-members-upgrade-header').on('click', function(event) {
    			var upgradeHeader = $('#members-upgrade-header');
    			upgradeHeader.fadeOut();
    			$.ajax({
    				url: ajaxurl,
    				type: 'POST',
    				data: {
    					action: 'members_dismiss_upgrade_header',
    					nonce: "<?php echo wp_create_nonce( 'members_dismiss_upgrade_header' ); ?>"
    				},
    			})
    			.done(function() {
    				console.log("success");
    			})
    			.fail(function() {
    				console.log("error");
    			})
    			.always(function() {
    				console.log("complete");
    			});
    		});
    	});
    </script>

    <?php
}

add_action( 'in_admin_footer', 'members_admin_promote_links' );
/**
 * Promotional links footer
 *
 * @return void
 */
function members_admin_promote_links() {
  global $current_screen, $plp_update;

  if( empty( $current_screen->id ) || ! members_is_admin_page() ) {
    return;
  }

  $links = array(
    array(
      'url' => 'https://wordpress.org/support/plugin/members/',
      'text' => __('Support', 'members'),
      'target' => '_blank'
    ),
    array(
      'url' => 'https://members-plugin.com/',
      'text' => __( 'Docs', 'members' ),
      'target' => '_blank'
    ),
    array(
      'url' => '/admin.php?page=members-about',
      'text' => __( 'About Us', 'members' ),
      'target' => '_blank'
    )
  );

  $title = __( 'Made with ♥ by the Members Team', 'members' );

  require_once( members_plugin()->dir . 'admin/views/promotion.php' );
}

add_action( 'wp_ajax_members_dismiss_upgrade_header', 'members_dismiss_upgrade_header' );
/**
 * Dismisses the Members upgrade header bar.
 *
 * @return void
 */
function members_dismiss_upgrade_header() {

	// Security check
	if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'members_dismiss_upgrade_header' ) ) {
		die();
	}

	update_option( 'members_dismiss_upgrade_header', true );
}

/**
 * Conditional to check whether we're on a Members admin page.
 *
 * @return boolean
 */
function members_is_admin_page() {
	$screen = get_current_screen();
	return ! empty( $screen->id ) && ! empty( Members\Admin\Settings_Page::get_instance()->admin_pages ) && in_array( $screen->id, Members\Admin\Settings_Page::get_instance()->admin_pages );
}
