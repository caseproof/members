<?php
/**
 * PHPUnit bootstrap with WordPress function stubs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

if ( ! class_exists( 'PHPUnit\\Framework\\TestCase' ) ) {
	class PHPUnit_Framework_TestCase {
		protected function assertSame( $expected, $actual, $message = '' ) {
			if ( $expected !== $actual ) {
				throw new Exception( $message ?: 'Failed assertSame' );
			}
		}

		protected function assertTrue( $condition, $message = '' ) {
			if ( ! $condition ) {
				throw new Exception( $message ?: 'Failed assertTrue' );
			}
		}

		protected function assertFalse( $condition, $message = '' ) {
			if ( $condition ) {
				throw new Exception( $message ?: 'Failed assertFalse' );
			}
		}

		protected function assertContains( $needle, $haystack, $message = '' ) {
			if ( ! in_array( $needle, (array) $haystack, true ) ) {
				throw new Exception( $message ?: 'Failed assertContains' );
			}
		}

		protected function assertNotContains( $needle, $haystack, $message = '' ) {
			if ( in_array( $needle, (array) $haystack, true ) ) {
				throw new Exception( $message ?: 'Failed assertNotContains' );
			}
		}

		protected function assertStringContainsString( $needle, $haystack, $message = '' ) {
			if ( false === strpos( (string) $haystack, (string) $needle ) ) {
				throw new Exception( $message ?: 'Failed assertStringContainsString' );
			}
		}
	}

	class_alias( 'PHPUnit_Framework_TestCase', 'PHPUnit\\Framework\\TestCase' );
}

require_once dirname( __DIR__ ) . '/src/Services/Settings.php';
require_once dirname( __DIR__ ) . '/src/Contracts/FileRepositoryInterface.php';
require_once dirname( __DIR__ ) . '/src/Contracts/AccessCheckerInterface.php';
require_once dirname( __DIR__ ) . '/src/Services/AccessChecker.php';
require_once dirname( __DIR__ ) . '/src/Contracts/ServerConfigInterface.php';
require_once dirname( __DIR__ ) . '/src/Services/ApacheServerConfig.php';

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		global $members_fp_test_filters;
		$args = func_get_args();
		array_shift( $args );

		if ( empty( $members_fp_test_filters[ $tag ] ) ) {
			return $value;
		}

		foreach ( $members_fp_test_filters[ $tag ] as $callback ) {
			$args[0] = $value;
			$value   = call_user_func_array( $callback, $args );
		}

		return $value;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		global $members_fp_test_filters;
		$members_fp_test_filters[ $tag ][] = $callback;
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	function remove_all_filters( $tag ) {
		global $members_fp_test_filters;
		unset( $members_fp_test_filters[ $tag ] );
	}
}

if ( ! function_exists( 'members_user_has_role' ) ) {
	function members_user_has_role( $user_id, $roles ) {
		global $members_fp_test_user_roles;
		$roles = (array) $roles;

		foreach ( (array) $members_fp_test_user_roles as $role ) {
			if ( in_array( $role, $roles, true ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'user_can' ) ) {
	function user_can( $user, $cap ) {
		if ( ! $user instanceof WP_User ) {
			return false;
		}

		return ! empty( $user->allcaps[ $cap ] );
	}
}

if ( ! class_exists( 'WP_User' ) ) {
	class WP_User {
		public $ID = 0;
		public $allcaps = array();
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test' . $path;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		return array(
			'basedir' => '/tmp/uploads',
			'baseurl' => 'https://example.test/wp-content/uploads',
		);
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $members_fp_test_options;

		if ( isset( $members_fp_test_options[ $option ] ) ) {
			return $members_fp_test_options[ $option ];
		}

		return $default;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return $url;
	}
}
