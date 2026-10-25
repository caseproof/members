<?php
/**
 * Handles the private site and private feed features of the plugin.  If private site is
 * selected in the plugin settings, the plugin will redirect all non-logged-in users to the
 * login page.  If private feed is selected, all content is blocked from feeds from the site.
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

# Redirects users to the login page.
add_action( 'template_redirect', 'members_please_log_in', 0 );

# Disable content in feeds if the feed should be private.
add_filter( 'the_content_feed', 'members_private_feed', 95 );
add_filter( 'the_excerpt_rss',  'members_private_feed', 95 );
add_filter( 'comment_text_rss', 'members_private_feed', 95 );

# Filters for the feed error message.
if ( isset( $GLOBALS['wp_embed'] ) && $GLOBALS['wp_embed'] instanceof \WP_Embed ) {
	add_filter( 'members_feed_error_message', array( $GLOBALS['wp_embed'], 'run_shortcode' ),   5 );
	add_filter( 'members_feed_error_message', array( $GLOBALS['wp_embed'], 'autoembed'     ),   5 );
}
add_filter( 'members_feed_error_message',                              'wptexturize',       10 );
add_filter( 'members_feed_error_message',                              'convert_smilies',   15 );
add_filter( 'members_feed_error_message',                              'convert_chars',     20 );
add_filter( 'members_feed_error_message',                              'wpautop',           25 );
add_filter( 'members_feed_error_message',                              'do_shortcode',      30 );
add_filter( 'members_feed_error_message',                              'shortcode_unautop', 35 );

# Authenticate when accessing the REST API.
add_filter( 'rest_authentication_errors', 'members_private_rest_api', 95 );

/**
 * Conditional tag to see if we have a private blog.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function members_is_private_blog() {

	return apply_filters( 'members_is_private_blog', members_get_setting( 'private_blog' ) );
}

/**
 * Conditional tag to see if we have a private feed.
 *
 * @since  1.0.0
 * @access public
 * @return bool
 */
function members_is_private_feed() {

	return apply_filters( 'members_is_private_feed', members_get_setting( 'private_feed' ) );
}

/**
 * Conditional tag to see if we have a private REST API
 *
 * @since  2.0.0
 * @access public
 * @return bool
 */
function members_is_private_rest_api() {

	return apply_filters( 'members_is_private_rest_api', members_get_setting( 'private_rest_api' ) );
}

/**
 * Redirects users that are not logged in to the 'wp-login.php' page.
 *
 * @since  0.1.0
 * @access public
 * @return void
 */
function members_please_log_in() {

	// If private blog is not enabled, bail.
	if ( ! members_is_private_blog() )
		return;

	// If this is a multisite instance and the user is logged into the network.
	if ( is_multisite() && is_user_logged_in() && ! is_user_member_of_blog() && ! is_super_admin() ) {
		members_ms_private_blog_die();
	}

	// Check if the private blog feature is active and if the user is not logged in.
	if ( ! is_user_logged_in() && members_is_private_page() ) {

		auth_redirect();
		exit;
	}
}

/**
 * Function for determining whether a page should be public even though we're in private
 * site mode.  Plugin devs can filter this to make specific pages public.
 *
 * @since  2.0.0
 * @access public
 * @return bool
 */
function members_is_private_page() {

	$is_private = true;

	// The login page must stay reachable, or logged-out visitors loop back to it forever.
	if ( members_is_login_page_request() )
		$is_private = false;

	if ( function_exists( 'bp_is_current_component' ) && ( bp_is_current_component( 'register' ) || bp_is_current_component( 'activate' ) ) )
		$is_private = false;

	// WooCommerce support.
	if ( class_exists( 'WooCommerce' ) ) {
		$page_id = get_option( 'woocommerce_myaccount_page_id' );

		if ( $page_id && is_page( $page_id ) )
			$is_private = false;
	}

	return apply_filters( 'members_is_private_page', $is_private );
}

/**
 * Whether the current request is for the site's login, lost password, or registration page.
 *
 * Plugins that move the login page (e.g. WP Ghost, WPS Hide Login) or sites that use a regular
 * page for it serve it through a normal front-end request, so private site mode would otherwise
 * redirect the login page to itself. Those plugins report the new URL through the core
 * `login_url`, `lostpassword_url`, and `register_url` filters, so compare against those.
 *
 * @since  3.2.28
 * @access public
 * @return bool
 */
function members_is_login_page_request() {

	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return false;
	}

	$request_path = untrailingslashit( rawurldecode( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) ) );

	foreach ( array( wp_login_url(), wp_lostpassword_url(), wp_registration_url() ) as $url ) {
		$path = untrailingslashit( rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );

		if ( '' !== $path && $path === $request_path ) {
			return true;
		}
	}

	return false;
}

/**
 * Blocks feed items if the user has selected the private feed feature.
 *
 * @since  0.2.0
 * @access public
 * @param  string  $content
 * @return string
 */
function members_private_feed( $content ) {

	return members_is_private_feed() ? members_get_private_feed_message() : $content;
}

/**
 * Returns the private feed error message.
 *
 * @since  1.0.0
 * @access public
 * @return string
 */
function members_get_private_feed_message() {

	return apply_filters( 'members_feed_error_message', members_get_setting( 'private_feed_error' ) );
}

/**
 * Returns an error if the REST API is accessed by an unauthenticated user.
 *
 * @link   https://developer.wordpress.org/rest-api/using-the-rest-api/frequently-asked-questions/#require-authentication-for-all-requests
 * @since  2.0.0
 * @access public
 * @param  object  $result
 * @return object
 */
function members_private_rest_api( $result ) {

	if ( empty( $result ) && members_is_private_rest_api() && ! is_user_logged_in() ) {

		return new WP_Error(
			'rest_not_logged_in',
			esc_html(
				apply_filters(
					'members_rest_api_error_message',
					__( 'You are not currently logged in.', 'members' )
				)
			),
			array( 'status' => 401 )
		);
	}

	return $result;
}

/**
 * Outputs an error message if a user attempts to access a site that they do not have
 * access to on multisite.
 *
 * @since  2.0.0
 * @access public
 * @return void
 */
function members_ms_private_blog_die() {

	$blogs = get_blogs_of_user( get_current_user_id() );

	$blogname = get_bloginfo( 'name' );

	$message = __( 'You do not currently have access to the "%s" site. If you believe you should have access, please contact your network administrator.', 'members' );

	if ( empty( $blogs ) )
		wp_die( sprintf( $message, $blogname ), 403 );

	$output = '<p>' . sprintf( $message, $blogname ) . '</p>';

	$output .= sprintf( '<p>%s</p>', __( 'If you reached this page by accident and meant to visit one of your own sites, try one of the following links.', 'members' ) );

	$output .= '<ul>';

	foreach ( $blogs as $blog )
		$output .= sprintf( '<li><a href="%s">%s</a></li>', esc_url( get_home_url( $blog->userblog_id ) ), esc_html( $blog->blogname ) );

	$output .= '</ul>';

	wp_die( $output, 403 );
}
