<?php
/**
 * Admin Menus add-on configuration.
 *
 * @package    Members
 * @subpackage AddOns
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

return array(
	'dir'       => trailingslashit( dirname( __DIR__ ) ),
	'namespace' => 'members/addons/admin_menus',
);
