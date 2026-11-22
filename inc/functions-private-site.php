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

	// BuddyPress swaps the loop for its own registration or activation screen, so no other page
	// can render in their place. The routes WordPress answers without the loop never reach that
	// screen though, so a request such as `/register/?sitemap=index` must not be exempt.
	if ( function_exists( 'bp_is_current_component' ) && ( bp_is_current_component( 'register' ) || bp_is_current_component( 'activate' ) ) && ! members_is_loop_free_request() )
		$is_private = false;

	// WooCommerce support. The My Account page holds the login, registration, and lost password
	// forms, and its endpoints (e.g. `/my-account/lost-password/`) are query vars on that same
	// page, so they all pass the check below. `is_page()` alone does not: it reads the queried
	// object, which a `page_id` sent with the request leaves in place while the loop renders
	// another page.
	if ( class_exists( 'WooCommerce' ) ) {
		$page_id = get_option( 'woocommerce_myaccount_page_id' );

		if ( $page_id && is_page( $page_id ) && members_main_query_renders_only( $page_id ) )
			$is_private = false;
	}

	return apply_filters( 'members_is_private_page', $is_private );
}

/**
 * Whether WordPress answers the current request itself, without going through the main loop.
 *
 * robots.txt, the favicon, feeds, trackbacks, and sitemaps are all served either from
 * `template_redirect` or straight after it. A query var sent with the request is enough to turn
 * any URL into one of them, and neither the queried object nor the posts in the main query
 * show what they return, so no page exemption should cover them.
 *
 * @since  3.3.3
 * @access public
 * @return bool
 */
function members_is_loop_free_request() {

	// The conditional tags below only work once the main query has run.
	if ( ! did_action( 'wp' ) ) {
		return false;
	}

	return is_robots() || is_favicon() || is_feed() || is_trackback() || get_query_var( 'sitemap' ) || get_query_var( 'sitemap-stylesheet' );
}

/**
 * Whether the main query renders the given post or page and nothing else.
 *
 * Conditional tags such as `is_page( $id )` only look at the queried object. Query vars sent
 * with the request, in the query string or in a POST body, take precedence over the ones the
 * rewrite rules produce, so a request can keep an exempt page as its queried object while
 * WordPress renders something else in its place. This applies the same checks that
 * `members_is_login_page_request()` applies to a login page that is a real page on the site:
 *
 * - Requests that WordPress answers itself without the main loop, such as feeds and sitemaps,
 *   are refused, because nothing they return can be seen in the main query.
 * - 404s and empty loops are refused. `WP_Query::set_404()` resets only the `is_` flags, so the
 *   queried object and the loop can still point at the page while the theme renders its 404
 *   template instead. A real page is never a 404 when it is requested normally, so unlike a
 *   virtual login path there is no legitimate request to keep working here.
 * - The queried object and every post in the loop must be the given post.
 *
 * @since  3.3.3
 * @access public
 * @param  int   $post_id
 * @return bool
 */
function members_main_query_renders_only( $post_id ) {

	global $wp_query;

	$post_id = (int) $post_id;

	// Without a post to compare against, or before the main query has run, nothing is confirmed.
	if ( ! $post_id || ! did_action( 'wp' ) ) {
		return false;
	}

	if ( members_is_loop_free_request() ) {
		return false;
	}

	if ( is_404() || empty( $wp_query->posts ) ) {
		return false;
	}

	if ( get_queried_object_id() !== $post_id ) {
		return false;
	}

	foreach ( (array) $wp_query->posts as $queried_post ) {

		$queried_post_id = is_object( $queried_post ) ? $queried_post->ID : $queried_post;

		if ( (int) $queried_post_id !== $post_id ) {
			return false;
		}
	}

	return true;
}

/**
 * Whether the current request is for the site's login, lost password, or registration page.
 *
 * Plugins that move the login page (e.g. WP Ghost, WPS Hide Login) or sites that use a regular
 * page for it serve it through a normal front-end request, so private site mode would otherwise
 * redirect the login page to itself. Those plugins report the new URL through the core
 * `login_url`, `lostpassword_url`, and `register_url` filters, so compare against those.
 *
 * Matching the request path on its own is not enough. Query vars sent with the request take
 * precedence over the ones the rewrite rules produce, whether they arrive in the query string
 * or in a POST body, so a request such as `/login/?pagename=some-page` keeps the exempt path
 * while the main query resolves to other content. Once the main query has run, also confirm
 * that the request really did land on the login page, and that nothing else was rendered in
 * its place, before exempting it. Requests that WordPress answers itself without the main
 * loop, such as feeds and sitemaps, are refused before those checks, because nothing they
 * return can be seen there.
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

	$matched_url = '';

	foreach ( array( wp_login_url(), wp_lostpassword_url(), wp_registration_url() ) as $url ) {
		$path = untrailingslashit( rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );

		if ( '' !== $path && $path === $request_path ) {
			$matched_url = $url;
			break;
		}
	}

	// The path is not one of the login paths, so nothing is exempt.
	if ( '' === $matched_url ) {
		return false;
	}

	// The main query has not run yet, so there is nothing else to check against.
	if ( ! did_action( 'wp' ) ) {
		return true;
	}

	// WordPress answers robots.txt, the favicon, feeds, trackbacks, and sitemaps itself, either
	// from `template_redirect` or straight after it, and none of them goes through the main
	// loop, so the checks below cannot see what they return. None of them is the login page.
	if ( is_robots() || is_favicon() || is_feed() || is_trackback() || get_query_var( 'sitemap' ) || get_query_var( 'sitemap-stylesheet' ) ) {
		return false;
	}

	global $wp_query;

	$login_page_id = url_to_postid( $matched_url );

	// The login URL belongs to a real page on the site, so the request must have resolved to
	// that page and the loop must hold nothing else. The queried object is not enough on its
	// own: a query var such as `page_id` leaves it pointing at the login page while the loop
	// renders other content, and `WP_Query::set_404()` resets only the `is_` flags, leaving
	// both of them in place while the theme renders its 404 template instead.
	//
	// A login page that is also the posts page is refused here, because the loop holds the blog
	// posts rather than the page. That leaves such a site redirecting to itself, which is the
	// lesser of the two: exempting it would serve the whole blog to logged-out visitors.
	if ( $login_page_id ) {

		if ( is_404() || empty( $wp_query->posts ) ) {
			return false;
		}

		if ( get_queried_object_id() !== $login_page_id ) {
			return false;
		}

		foreach ( (array) $wp_query->posts as $queried_post ) {

			$queried_post_id = is_object( $queried_post ) ? $queried_post->ID : $queried_post;

			if ( (int) $queried_post_id !== $login_page_id ) {
				return false;
			}
		}

		return true;
	}

	// No page of our own sits behind the login URL, which is how login plugins that serve a
	// virtual page behave. Keep the exemption, but only while the request has not resolved to
	// real content of any kind.
	//
	// Start with the loop, because that is what the theme renders. A post that came out of the
	// database always has an ID, so one in the loop means the request was pointed at real
	// content, whatever the `is_` flags and the queried object say.
	foreach ( (array) $wp_query->posts as $queried_post ) {

		$queried_post_id = is_object( $queried_post ) ? $queried_post->ID : $queried_post;

		if ( 0 !== (int) $queried_post_id ) {
			return false;
		}
	}

	if ( is_archive() || is_search() || is_home() || get_queried_object_id() ) {
		return false;
	}

	// Some login plugins (e.g. Theme My Login) serve the virtual page as a made-up page: they
	// flag the main query as singular and swap the loop for a single post that is not in the
	// database and has no ID. That is the login page itself, so it stays exempt. A singular
	// request can only get this far with such a post, because a real one was refused above.
	if ( is_singular() ) {
		return ! empty( $wp_query->posts );
	}

	// Unlike the branch above, a 404 stays exempt here on purpose. A login path that WordPress
	// cannot route is a 404 to the main query, so the login page itself arrives in that state;
	// refusing it would redirect the login page to itself, which is the loop from #154.
	return true;
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
