<?php
/**
 * General functions file for the plugin.
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

/**
 * Marks the current page as personal, so shared caches don't store it.
 *
 * Call this whenever Members renders something that differs between visitors, like protected
 * content, a logged-in-only shortcode, or the login form. Otherwise a cache that ignores login
 * cookies (such as a CDN "cache everything" rule) can store the logged-out version and serve it to
 * members. The Login Form widget is the one exception: it sits in sidebars on every page, so
 * marking it would switch off caching for the whole site, and a cached copy only shows a logged-in
 * member a login form.
 *
 * Defines `DONOTCACHEPAGE`, which page-caching plugins honor, and sends WordPress' no-cache
 * headers if they haven't been sent yet.
 *
 * @access public
 * @param  string  $context  What made the page personal (for the filter).
 * @return void
 */
function members_do_not_cache_page( $context = '' ) {

	static $done = false;

	if ( $done ) {
		return;
	}

	/**
	 * Filters whether Members marks a personal page as uncacheable. Return false if your cache
	 * already varies pages by login (for example, it bypasses logged-in visitors and never caches
	 * protected URLs).
	 *
	 * @param bool    $do_not_cache Whether to mark the page as uncacheable.
	 * @param string  $context      What made the page personal.
	 */
	if ( ! apply_filters( 'members_do_not_cache_page', true, $context ) ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	if ( ! headers_sent() ) {
		nocache_headers();
	}

	$done = true;
}

/**
 * Validates a value as a boolean.  This way, strings such as "true" or "false" will be converted
 * to their correct boolean values.
 *
 * @since  1.0.0
 * @access public
 * @param  mixed   $val
 * @return bool
 */
function members_validate_boolean( $val ) {

	return filter_var( $val, FILTER_VALIDATE_BOOLEAN );
}


/**
 * Helper function for sorting objects by priority.
 *
 * @since  2.0.0
 * @access protected
 * @param  object     $a
 * @param  object     $b
 * @return int
 */
function members_priority_sort( $a, $b ) {

	return $a->priority - $b->priority;
}
