<?php
/**
 * Lightweight test runner when PHPUnit PHAR is unavailable.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/SettingsTest.php';
require __DIR__ . '/AccessCheckerTest.php';
require __DIR__ . '/ApacheServerConfigTest.php';
require __DIR__ . '/GatekeeperTest.php';
require __DIR__ . '/NginxServerConfigTest.php';

$classes = array(
	'SettingsTest',
	'AccessCheckerTest',
	'ApacheServerConfigTest',
	'GatekeeperTest',
	'NginxServerConfigTest',
);

$passed = 0;
$failed = 0;

foreach ( $classes as $class ) {
	$instance = new $class();
	$methods    = get_class_methods( $instance );

	foreach ( $methods as $method ) {
		if ( 0 !== strpos( $method, 'test_' ) ) {
			continue;
		}

		try {
			$instance->$method();
			echo "PASS {$class}::{$method}\n";
			++$passed;
		} catch ( Throwable $e ) {
			echo "FAIL {$class}::{$method} - {$e->getMessage()}\n";
			++$failed;
		}
	}
}

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
