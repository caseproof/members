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
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

# Add shortcodes.
add_action( 'init', 'members_register_shortcodes' );

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

	if ( ! is_null( $content ) && ! is_feed() )
		members_do_not_cache_page( 'members_logged_in' );

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

	if ( ! is_null( $content ) && ! is_feed() )
		members_do_not_cache_page( 'members_not_logged_in' );

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

	members_do_not_cache_page( 'members_access' );

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
 * @param array $attr Shortcode attributes.
 * @return string
 */
function members_login_form_shortcode( $attr = [] ) {
    // Default attributes
    $defaults = array(
        'echo'     => false,
        'redirect' => members_get_login_form_page_url(),
    );
    
    // Merge user attributes with defaults
    $attr = shortcode_atts( $defaults, $attr, 'members_login_form' );

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
    <?php } else {
        // Let two-factor and CAPTCHA plugins hook into this form like they do on wp-login.php.
        members_login_form_enqueue_scripts();

        // Add the login_form_bottom filter only for this specific Members shortcode form
        add_filter( 'login_form_bottom', 'members_login_form_bottom' );
        ?>
        <div class="members-login-form">
            <?php echo wp_login_form( $attr ); ?>
        </div>
        <?php
        // Remove the filter after rendering to avoid affecting other login forms
        remove_filter( 'login_form_bottom', 'members_login_form_bottom' );
        ?>
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
 * Returns the URL of the page the login form is on, minus the query args Members adds after a
 * failed login.
 *
 * @since  3.2.28
 * @access public
 * @return string
 */
function members_get_login_form_page_url() {

    $url = ( is_ssl() ? 'https://' : 'http://' ) . ( isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '' ) . ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' );

    return esc_url_raw( remove_query_arg( array( 'login', 'members_login_error' ), $url ) );
}

/**
 * Loads the login scripts two-factor and CAPTCHA plugins normally add only to wp-login.php, so
 * they also work on Members login forms. Runs once per page.
 *
 * @since  3.2.28
 * @access public
 * @return void
 */
function members_login_form_enqueue_scripts() {

    static $done = false;

    if ( $done ) {
        return;
    }

    $done = true;

    // Wordfence Login Security: its login script finds any form with the standard WordPress login
    // fields and adds the 2FA code prompt (and its CAPTCHA) to it.
    if ( class_exists( '\WordfenceLS\Controller_WordfenceLS' ) && method_exists( '\WordfenceLS\Controller_WordfenceLS', 'shared' ) ) {
        $wordfence = \WordfenceLS\Controller_WordfenceLS::shared();

        if ( is_callable( array( $wordfence, '_login_enqueue_scripts' ) ) ) {
            $wordfence->_login_enqueue_scripts();
        }
    }

    /**
     * Fires when a Members login form is shown, for plugins that need to add their login scripts.
     *
     * @since 3.2.28
     */
    do_action( 'members_login_form_enqueue_scripts' );
}

/**
 * Whether the current login request came from a Members login form.
 *
 * @since  3.2.28
 * @access public
 * @return bool
 */
function members_is_login_form_request() {

    if ( ! empty( $_POST['members_login_form'] ) ) {
        return true;
    }

    // Forms rendered before 3.2.28 (e.g. still in a page cache) send a nonce instead.
    return isset( $_POST['members_login_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['members_login_nonce'] ) ), 'members_login_form' );
}

/**
 * Returns the message to show for a failed login. Errors that would reveal whether a username or
 * email exists all get the same generic message.
 *
 * @since  3.2.28
 * @access public
 * @param  \WP_Error|mixed  $error  Login error.
 * @return string
 */
function members_get_login_error_message( $error ) {

    $generic = __( 'Invalid username or password.', 'members' );

    if ( ! is_wp_error( $error ) ) {
        return $generic;
    }

    $code = $error->get_error_code();

    if ( ! $code || in_array( $code, array( 'invalid_username', 'invalid_email', 'incorrect_password', 'invalidcombo' ), true ) ) {
        return $generic;
    }

    // Other errors (e.g. a security plugin's lockout notice) are shown, minus any username.
    $message = preg_replace( '/<strong>[^<]+<\/strong>\s*(is not registered|was not found)/i', __( 'the username or email', 'members' ), $error->get_error_message() );

    return '' !== trim( wp_strip_all_tags( $message ) ) ? $message : $generic;
}

/**
 * Sends failed logins from a Members login form back to the form with an error message, and
 * successful logins on to the form's redirect URL.
 *
 * @since 3.2.18
 *
 * @param string $redirect_to    The redirect destination URL.
 * @param string $request        The request URL.
 * @param object $user           The user object.
 * @return string                The redirect URL.
 */
function members_login_redirect( $redirect_to, $request, $user ) {

    if ( ! members_is_login_form_request() ) {
        return $redirect_to;
    }

    if ( $user instanceof \WP_User ) {
        return remove_query_arg( array( 'login', 'members_login_error' ), $redirect_to );
    }

    // Send them back to the page the form is on. Only same-site URLs are allowed.
    $form_page = isset( $_POST['members_login_page'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['members_login_page'] ) ) ) : '';

    if ( ! $form_page ) {
        $form_page = wp_get_referer() ? wp_get_referer() : home_url( '/' );
    }

    // Keep the message for the next page load. A random key in the URL (instead of a PHP session)
    // keeps it tied to this one redirect and doesn't stop the page from being cached.
    $key = wp_generate_password( 20, false );

    set_transient( 'members_login_error_' . $key, members_get_login_error_message( $user ), 5 * MINUTE_IN_SECONDS );

    $form_page = add_query_arg(
        array(
            'login'               => 'failed',
            'members_login_error' => $key,
        ),
        remove_query_arg( array( 'login', 'members_login_error' ), $form_page )
    );

    wp_safe_redirect( $form_page );
    exit;
}

/**
 * Filters the login form bottom output to mark the form as a Members form and show the error
 * message after a failed login. This is only added to Members shortcode forms.
 *
 * @since 3.2.18
 *
 * @return string The HTML to output below the login form.
 */
function members_login_form_bottom() {

    // Marks the submission as a Members form and tells us where to send failed logins.
    $output  = '<input type="hidden" name="members_login_form" value="1" />';
    $output .= '<input type="hidden" name="members_login_page" value="' . esc_url( members_get_login_form_page_url() ) . '" />';

    if ( isset( $_GET['login'] ) && 'failed' === $_GET['login'] ) {

        $key           = isset( $_GET['members_login_error'] ) ? sanitize_key( wp_unslash( $_GET['members_login_error'] ) ) : '';
        $error_message = $key ? get_transient( 'members_login_error_' . $key ) : '';

        if ( ! $error_message || ! is_string( $error_message ) ) {
            $error_message = __( 'Invalid username or password.', 'members' );
        }

        // Allow specific HTML tags in error messages
        $allowed_html = array(
            'a' => array(
                'href' => array(),
                'title' => array(),
                'target' => array(),
                'rel' => array(),
                'class' => array(),
            ),
            'br' => array(),
            'em' => array(),
            'strong' => array(),
            'p' => array('class' => array()),
            'span' => array('class' => array()),
            'div' => array('class' => array()),
        );

        $output .= '<p class="members-login-notice members-login-error">' . wp_kses( $error_message, $allowed_html ) . '</p>';
    }

    return $output;
}
