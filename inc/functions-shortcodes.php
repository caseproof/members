<?php
/**
 * Shortcodes for use within posts and other shortcode-aware areas.
 *
 * @package    Members
 * @subpackage Includes
 * @author     The MemberPress Team
 * @copyright  Copyright (c) 2009 - 2018, The MemberPress Team
 * @link       https://members-plugin.com/
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

# Add shortcodes.
add_action( 'init', 'members_register_shortcodes' );

add_filter( 'login_form_bottom', 'members_login_form_bottom' );
add_filter( 'login_redirect', 'members_login_redirect', 9, 3 );

/**
 * Registers shortcodes.
 *
 * @since  0.2.0
 * @access public
 * @return void
 */
function members_register_shortcodes() {

	// Add the `[members_login_form]` shortcode.
	add_shortcode( 'members_login_form', 'members_login_form_shortcode' );
	add_shortcode( 'login-form',         'members_login_form_shortcode' ); // @deprecated 1.0.0

	// Add the `[members_access]` shortcode.
	add_shortcode( 'members_access', 'members_access_check_shortcode' );
	add_shortcode( 'access',         'members_access_check_shortcode' ); // @deprecated 1.0.0

	// Add the `[members_feed]` shortcode.
	add_shortcode( 'members_feed', 'members_feed_shortcode' );
	add_shortcode( 'feed',         'members_feed_shortcode' ); // @deprecated 1.0.0

	// Add the `[members_logged_in]` shortcode.
	add_shortcode( 'members_logged_in', 'members_is_user_logged_in_shortcode' );
	add_shortcode( 'is_user_logged_in', 'members_is_user_logged_in_shortcode' ); // @deprecated 1.0.0

	// Add the `[members_not_logged_in]` shortcode.
	add_shortcode( 'members_not_logged_in', 'members_not_logged_in_shortcode' );

	// @deprecated 0.2.0.
	add_shortcode( 'get_avatar', 'members_get_avatar_shortcode' );
	add_shortcode( 'avatar',     'members_get_avatar_shortcode' );
}

/**
 * Displays content if the user viewing it is currently logged in. This also blocks content
 * from showing in feeds.
 *
 * @since  0.1.0
 * @access public
 * @param  array   $attr
 * @param  string  $content
 * @return string
 */
function members_is_user_logged_in_shortcode( $attr, $content = null ) {

	return is_feed() || ! is_user_logged_in() || is_null( $content ) ? '' : do_shortcode( $content );
}

/**
 * Displays content if the user viewing it is not currently logged in.
 *
 * @since  2.0.0
 * @access public
 * @param  array   $attr
 * @param  string  $content
 * @return string
 */
function members_not_logged_in_shortcode( $attr, $content = null ) {

	return is_user_logged_in() || is_null( $content ) ? '' : do_shortcode( $content );
}

/**
 * Content that should only be shown in feed readers.  Can be useful for displaying
 * feed-specific items.
 *
 * @since  0.1.0
 * @access public
 * @param  array   $attr
 * @param  string  $content
 * @return string
 */
function members_feed_shortcode( $attr, $content = null ) {

	return ! is_feed() || is_null( $content ) ? '' : do_shortcode( $content );
}

/**
 * Provide/restrict access to specific roles or capabilities. This content should not be shown
 * in feeds.  Note that capabilities are checked first.  If a capability matches, any roles
 * added will *not* be checked.  Users should choose between using either capabilities or roles
 * for the check rather than both.  The best option is to always use a capability.
 *
 * @since  0.1.0
 * @access public
 * @param  array   $attr
 * @param  string  $content
 * @return string
 */
function members_access_check_shortcode( $attr, $content = null ) {

	// If there's no content or if viewing a feed, return an empty string.
	if ( is_null( $content ) || is_feed() )
		return '';

	$user_can = false;

	// Set up the default attributes.
	$defaults = array(
		'capability' => '',  // Single capability or comma-separated multiple capabilities.
		'role'       => '',  // Single role or comma-separated multiple roles.
		'user_id'    => '',  // Single user ID or comma-separated multiple IDs.
		'user_name'  => '',  // Single user name or comma-separated multiple names.
		'user_email' => '',  // Single user email or comma-separated multiple emails.
		'operator'   => 'or' // Only the `!` operator is supported for now.  Everything else falls back to `or`.
	);

	// Merge the input attributes and the defaults.
	$attr = shortcode_atts( $defaults, $attr, 'members_access' );

	// Get the operator.
	$operator = strtolower( $attr['operator'] );

	// If the current user has the capability, show the content.
	if ( $attr['capability'] ) {

		// Get the capabilities.
		$caps = explode( ',', $attr['capability'] );

		if ( '!' === $operator )
			return members_current_user_can_any( $caps ) ? '' : do_shortcode( $content );

		return members_current_user_can_any( $caps ) ? do_shortcode( $content ) : '';
	}

	// If the current user has the role, show the content.
	if ( $attr['role'] ) {

		// Get the roles.
		$roles = explode( ',', $attr['role'] );

		if ( '!' === $operator )
			return members_current_user_has_role( $roles ) ? '' : do_shortcode( $content );

		return members_current_user_has_role( $roles ) ? do_shortcode( $content ) : '';
	}

	$user_id = 0;
	$user_name = $user_email = '';

	if ( is_user_logged_in() ) {

		$user       = wp_get_current_user();
		$user_id    = get_current_user_id();
		$user_name  = $user->user_login;
		$user_email = $user->user_email;
	}

	// If the current user has one of the user ids.
	if ( $attr['user_id'] ) {

		// Get the user IDs.
		$ids = array_map( 'trim', explode( ',', $attr['user_id'] ) );

		if ( '!' === $operator ) {
			return in_array( $user_id, $ids ) ? '' : do_shortcode( $content );
		}

		return in_array( $user_id, $ids ) ? do_shortcode( $content ) : '';
	}

	// If the current user has one of the user names.
	if ( $attr['user_name'] ) {

		// Get the user names.
		$names = array_map( 'trim', explode( ',', $attr['user_name'] ) );

		if ( '!' === $operator ) {
			return in_array( $user_name, $names ) ? '' : do_shortcode( $content );
		}

		return in_array( $user_name, $names ) ? do_shortcode( $content ) : '';
	}

	// If the current user has one of the user emails.
	if ( $attr['user_email'] ) {

		// Get the user emails.
		$emails = array_map( 'trim', explode( ',', $attr['user_email'] ) );

		if ( '!' === $operator ) {
			return in_array( $user_email, $emails ) ? '' : do_shortcode( $content );
		}

		return in_array( $user_email, $emails ) ? do_shortcode( $content ) : '';
	}

	// Return an empty string if we've made it to this point.
	return '';
}

/**
 * Displays a login form.
 *
 * @since  0.1.0
 * @access public
 * @return string
 */
function members_login_form_shortcode() {
    ob_start();
    if ( is_user_logged_in() ) { ?>
        <div class="members-login-form">
            <p class="members-login-notice members-login-notice-success">
                <?php esc_html_e('You are already logged in.', 'members'); ?>
            </p>
        </div>
        <style>
            .members-login-notice {
                display: block !important;
                max-width: 320px;
                padding: 10px;
                background: #f1f1f1;
                border-radius: 4px;
                border-left: 3px solid #36d651;
                font-size: 18px;
                font-weight: 500;
            }
        </style>
    <?php } else { ?>
        <div class="members-login-form">
            <?php echo wp_login_form( array( 'echo' => false ) ); ?>
        </div>
        <style>
            .members-login-form * {
                box-sizing: border-box;
            }
            .members-login-form label {
                display: block;
                margin-bottom: 4px;
                font-size: 18px;
                font-weight: 500;
            }
            .members-login-form input[type="text"],
            .members-login-form input[type="password"] {
                width: 100%;
                max-width: 320px;
                padding: 0.5rem 0.75rem;
                border: 1px solid #64748b;
                border-radius: 4px;
                font-size: 16px;
            }
            .members-login-form input[type="submit"] {
                width: 100%;
                max-width: 320px;
                padding: 0.75rem;
                cursor: pointer;
                background: #64748b;
                border: 0;
                border-radius: 4px;
                color: #fff;
                font-size: 16px;
                font-weight: 500;
            }
            .members-logged-in input[type="submit"] {
                pointer-events: none;
                opacity: 0.4;
                cursor: not-allowed;
            }
            .members-login-notice {
                display: block !important;
                max-width: 320px;
                padding: 10px;
                background: #f1f1f1;
                border-radius: 4px;
                border-left: 3px solid #36d651;
                font-size: 18px;
                font-weight: 500;
            }
            .members-login-error {
                border-left-color: #d63638;
            }
        </style>
    <?php
    }
    return ob_get_clean();
}

/**
 * Filters the login redirect URL to send failed logins back to the
 * referrer with a query arg of `login=failed`.
 *
 * @since 3.2.18
 *
 * @param string $redirect_to    The redirect destination URL.
 * @param string $request        The request URL.
 * @param object $user           The user object.
 * @return string                The redirect URL.
 */
function members_login_redirect( $redirect_to, $request, $user ) {
    if ( ! isset( $_POST['members_redirect_to'] ) ) {
        return $redirect_to;
    } elseif ( empty( $user ) || is_wp_error( $user ) ) {
        // Start session if not already started
        if (!session_id()) {
            session_start();
        }
        
        // Get the referrer URL
        $redirect_to = $_SERVER['HTTP_REFERER'];
        
        // Remove any existing login and error parameters
        $redirect_to = remove_query_arg(['login', 'error'], $redirect_to);
        
        // Add login=failed parameter
        $redirect_to = add_query_arg('login', 'failed', $redirect_to);
        
        // Add error parameter if it's a 2FA error
        if (is_wp_error($user) && $user->get_error_code() == 'wfls_twofactor_required') {
            // Store error in session as backup
            $_SESSION['members_login_error'] = 'wfls_twofactor_required';
            
            // Add to URL params
            $redirect_to = add_query_arg('error', 'wfls_twofactor_required', $redirect_to);
        }
        
        // wp_redirect() does not return a value, it simply redirects
        wp_redirect($redirect_to);
        exit; // Important to exit after redirect
    } else {
        // On success, clear any error session data
        if (!session_id()) {
            session_start();
        }
        if (isset($_SESSION['members_login_error'])) {
            unset($_SESSION['members_login_error']);
        }
        
        // On success, remove any error parameters
        return remove_query_arg(['login', 'error'], $_SERVER['HTTP_REFERER']);
    }
}

/**
 * Filters the login form bottom output to add an error message if the login has failed.
 *
 * @since 3.2.18
 *
 * @return string The HTML to output below the login form.
 */
function members_login_form_bottom() {
    // Start session if not already started
    if (!session_id()) {
        session_start();
    }
    
    $output = '<input type="hidden" name="members_redirect_to" value="1" />';

    if ( isset( $_GET['login'] ) && $_GET['login'] == 'failed' ) {
        $error_message = '';
        
        // Check URL parameter first, then fall back to session
        if (
            (isset($_GET['error']) && $_GET['error'] == 'wfls_twofactor_required') || 
            (isset($_SESSION['members_login_error']) && $_SESSION['members_login_error'] == 'wfls_twofactor_required')
        ) {
            $error_message = esc_html__('Please provide your 2FA code when prompted.', 'members');
            // Clear the session error after using it
            if (isset($_SESSION['members_login_error'])) {
                unset($_SESSION['members_login_error']);
            }
        } else {
            $error_message = esc_html__('Invalid username or password.', 'members');
        }
        
        $output .= '<p class="members-login-notice members-login-error">' . $error_message . '</p>';
    }

    return $output;
}
