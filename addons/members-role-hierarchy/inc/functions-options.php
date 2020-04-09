<?php
/**
 * Functions for handling plugin options.
 *
 * @package    MembersRoleHierarchy
 * @subpackage Includes
 * @author     Justin Tadlock <justin@justintadlock.com>
 * @copyright  Copyright (c) 2017, Justin Tadlock
 * @link       http://themehybrid.com/plugins/members-role-hierarchy
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/**
 * Returns the comparison operator to compare roles.
 *
 * @since  1.0.0
 * @return string
 */
function mrh_get_comparison_operator() {

	$allowed = array( '>', '>=' );

	$operator = apply_filters( 'mrh_get_comparison_operator', members_get_setting( 'comparison_operator' ) );

	return in_array( $operator, $allowed ) ? $operator : '>';
}

/**
 * Gets a setting from from the plugin settings in the database.
 *
 * @since  1.0.0
 * @access public
 * @return mixed
 */
function mrh_get_setting( $option = '' ) {

	$defaults = members_get_default_settings();

	$settings = wp_parse_args( get_option( 'mrh_plugin_settings', $defaults ), $defaults );

	return isset( $settings[ $option ] ) ? $settings[ $option ] : false;
}

/**
 * Returns an array of the default plugin settings.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function mrh_get_default_settings() {

	return array(
		// @since 1.0.0
		'comparison_operator' => '>'
	);
}
