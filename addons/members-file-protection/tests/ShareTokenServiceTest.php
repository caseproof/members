<?php

use Members\FileProtection\Services\ShareTokenService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/src/Services/Installer.php';
require_once dirname( __DIR__ ) . '/src/Services/ShareTokenService.php';

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return is_string( $str ) ? trim( $str ) : '';
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		if ( isset( $GLOBALS['members_fp_test_current_time'] ) ) {
			return $GLOBALS['members_fp_test_current_time'];
		}

		return gmdate( 'Y-m-d H:i:s' );
	}
}

class ShareTokenServiceTest extends TestCase {

	private function stubTokenRow( $expires_at ) {
		$GLOBALS['wpdb'] = new class( $expires_at ) {
			private $expires_at;

			public $prefix = 'wp_';

			public function __construct( $expires_at ) {
				$this->expires_at = $expires_at;
			}

			public function prepare( $query, ...$args ) {
				return $query;
			}

			public function get_row( $query ) {
				return (object) array(
					'expires_at' => $this->expires_at,
					'max_uses'   => 0,
					'use_count'  => 0,
				);
			}
		};
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'], $GLOBALS['members_fp_test_current_time'] );
	}

	public function test_validate_accepts_unexpired_gmt_token() {
		$this->stubTokenRow( '2026-06-10 13:00:00' );
		$GLOBALS['members_fp_test_current_time'] = '2026-06-10 12:00:00';

		$service = new ShareTokenService();

		$this->assertTrue( $service->validate( 'abc123', 42 ) );
	}

	public function test_validate_rejects_expired_gmt_token() {
		$this->stubTokenRow( '2026-06-10 11:00:00' );
		$GLOBALS['members_fp_test_current_time'] = '2026-06-10 12:00:00';

		$service = new ShareTokenService();

		$this->assertFalse( $service->validate( 'abc123', 42 ) );
	}

	public function test_validate_rejects_token_at_exact_expiry() {
		$this->stubTokenRow( '2026-06-10 12:00:00' );
		$GLOBALS['members_fp_test_current_time'] = '2026-06-10 12:00:00';

		$service = new ShareTokenService();

		$this->assertFalse( $service->validate( 'abc123', 42 ) );
	}
}
