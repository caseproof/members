<?php
/**
 * Functions for handling plugin options.
 *
 * @package    MembersRoleHierarchy
 * @subpackage Includes
 * @author     The MemberPress Team 
 * @copyright  Copyright (c) 2017, The MemberPress Team
 * @link       https://members-plugin.com/
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

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
