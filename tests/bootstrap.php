<?php
/**
 * PHPUnit bootstrap: WordPress test library + Members (Admin Menus add-on active).
 *
 * Set {@see https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/ WP_TESTS_DIR}
 * to your `wordpress-develop/tests/phpunit/includes` parent’s `tests/phpunit` sibling’s `tests` path
 * (the env var points at the directory that contains `includes/functions.php`).
 *
 * Example: export WP_TESTS_DIR=/tmp/wordpress-tests-lib
 *
 * @package Members
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "\nMembers PHPUnit: set WP_TESTS_DIR to the wordpress-tests-lib directory (contains includes/functions.php).\n\n";
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load Members main plugin on muplugins_loaded.
 *
 * @return void
 */
function members_tests_load_plugin() {
	require dirname( __DIR__ ) . '/members.php';
}

tests_add_filter(
	'pre_option_members_active_addons',
	static function () {
		return array( 'members-admin-menus' );
	}
);

tests_add_filter( 'muplugins_loaded', 'members_tests_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
