<?php

use Members\FileProtection\Gatekeeper;
use Members\FileProtection\Services\Settings;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/src/Contracts/UnauthorizedHandlerInterface.php';
require_once dirname( __DIR__ ) . '/src/Contracts/FileDeliveryInterface.php';
require_once dirname( __DIR__ ) . '/src/Services/ShareTokenService.php';
require_once dirname( __DIR__ ) . '/src/Services/DownloadLimitService.php';
require_once dirname( __DIR__ ) . '/src/Services/OffloadIntegration.php';
require_once dirname( __DIR__ ) . '/src/Services/Installer.php';
require_once dirname( __DIR__ ) . '/src/Gatekeeper.php';

if ( ! function_exists( 'path_join' ) ) {
	function path_join( $base, $path ) {
		return trailingslashit( $base ) . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() {
		if ( isset( $GLOBALS['gatekeeper_test_user'] ) && $GLOBALS['gatekeeper_test_user'] instanceof WP_User ) {
			return $GLOBALS['gatekeeper_test_user'];
		}

		return new WP_User();
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return strip_tags( $string );
	}
}

if ( ! function_exists( 'status_header' ) ) {
	function status_header( $code ) {}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		return str_replace( '\\', '/', $path );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

class GatekeeperStubUnauthorized implements \Members\FileProtection\Contracts\UnauthorizedHandlerInterface {

	public $calls = 0;

	public $last_attachment_id = null;

	public function handle( int $attachment_id, $user ): void {
		++$this->calls;
		$this->last_attachment_id = $attachment_id;
	}
}

class GatekeeperStubDelivery implements \Members\FileProtection\Contracts\FileDeliveryInterface {

	public $calls = 0;

	public function deliver( string $absolute_path, int $attachment_id ): void {
		++$this->calls;
	}
}

class GatekeeperStubRepository implements \Members\FileProtection\Contracts\FileRepositoryInterface {

	public function isProtected( int $attachment_id ): bool {
		return false;
	}

	public function allowsAllLoggedIn( int $attachment_id ): bool {
		return false;
	}

	public function getAllowedRoles( int $attachment_id ): array {
		return array();
	}

	public function setProtected( int $attachment_id, bool $protected ): void {}

	public function setAllowedRoles( int $attachment_id, array $roles ): void {}

	public function setAllowsAllLoggedIn( int $attachment_id, bool $allowed ): void {}

	public function getDownloadLimit( int $attachment_id ): int {
		return 0;
	}

	public function setDownloadLimit( int $attachment_id, int $limit ): void {}

	public function resolveAttachmentId( string $file_path ): ?int {
		return null;
	}
}

class GatekeeperAuthorizedRepository extends GatekeeperStubRepository {

	public function resolveAttachmentId( string $file_path ): ?int {
		return 42;
	}

	public function isProtected( int $attachment_id ): bool {
		return true;
	}
}

class GatekeeperCountingDownloadLimits extends \Members\FileProtection\Services\DownloadLimitService {

	public $record_calls = 0;

	public function canDownload( $attachment_id, $user ) {
		return true;
	}

	public function recordDownload( $attachment_id, $user ) {
		++$this->record_calls;
	}
}

class GatekeeperCountingShareTokens extends \Members\FileProtection\Services\ShareTokenService {

	public $consume_calls = 0;

	public function validate( $token, $attachment_id ) {
		return '' !== $token;
	}

	public function consume( $token, $attachment_id ) {
		++$this->consume_calls;

		return true;
	}
}

class GatekeeperTest extends TestCase {

	private function createAuthorizedGatekeeper( GatekeeperStubDelivery $delivery, $download_limits = null, $share_tokens = null ) {
		$settings   = new Settings();
		$repository = new GatekeeperAuthorizedRepository();
		$user       = new WP_User();
		$user->ID   = 1;
		$user->allcaps = array( 'manage_options' => true );

		$GLOBALS['gatekeeper_test_user'] = $user;

		if ( null === $download_limits ) {
			$download_limits = new GatekeeperCountingDownloadLimits( $repository );
		}

		if ( null === $share_tokens ) {
			$share_tokens = new \Members\FileProtection\Services\ShareTokenService();
		}

		return new Gatekeeper(
			$repository,
			new \Members\FileProtection\Services\AccessChecker( $repository ),
			$delivery,
			new GatekeeperStubUnauthorized(),
			$settings,
			$share_tokens,
			$download_limits,
			new \Members\FileProtection\Services\OffloadIntegration( $repository, $settings )
		);
	}

	private function createProtectedPdfFixture( $basename ) {
		$uploads = wp_upload_dir();
		$path    = $uploads['basedir'] . '/' . $basename;

		if ( ! is_dir( $uploads['basedir'] ) ) {
			mkdir( $uploads['basedir'], 0755, true );
		}

		file_put_contents( $path, str_repeat( 'x', 65536 ) );

		return array(
			'path'    => $path,
			'gateway' => '/wp-content/uploads/' . $basename,
		);
	}

	private function tearDownRangeServerVars() {
		unset( $_SERVER['HTTP_RANGE'], $_GET['members_fp_token'], $GLOBALS['gatekeeper_test_user'] );
	}

	public function test_orphan_files_use_unauthorized_handler() {
		$unauthorized = new GatekeeperStubUnauthorized();
		$delivery     = new GatekeeperStubDelivery();
		$settings     = new Settings();
		$repository   = new GatekeeperStubRepository();

		$gatekeeper = new Gatekeeper(
			$repository,
			new \Members\FileProtection\Services\AccessChecker( $repository ),
			$delivery,
			$unauthorized,
			$settings,
			new \Members\FileProtection\Services\ShareTokenService(),
			new \Members\FileProtection\Services\DownloadLimitService( $repository ),
			new \Members\FileProtection\Services\OffloadIntegration( $repository, $settings )
		);

		$uploads = wp_upload_dir();
		$path    = $uploads['basedir'] . '/orphan-test.pdf';

		if ( ! is_dir( $uploads['basedir'] ) ) {
			mkdir( $uploads['basedir'], 0755, true );
		}

		file_put_contents( $path, 'test' );

		$gatekeeper->handle( '/wp-content/uploads/orphan-test.pdf' );

		$this->assertSame( 1, $unauthorized->calls );
		$this->assertSame( 0, $unauthorized->last_attachment_id );
		$this->assertSame( 0, $delivery->calls );

		unlink( $path );
	}

	public function test_local_delivery_skips_offload_when_both_available() {
		$unauthorized = new GatekeeperStubUnauthorized();
		$delivery     = new GatekeeperStubDelivery();
		$settings     = new Settings();
		$repository   = new GatekeeperAuthorizedRepository();
		$user         = new WP_User();
		$user->ID     = 1;
		$user->allcaps = array( 'manage_options' => true );

		$GLOBALS['gatekeeper_test_user'] = $user;
		$GLOBALS['wpdb']                 = new class() {
			public $prefix = 'wp_';

			public function insert( $table, $data, $format = null ) {
				return 1;
			}
		};

		if ( ! function_exists( 'current_time' ) ) {
			function current_time( $type, $gmt = 0 ) {
				return '2020-01-01 00:00:00';
			}
		}

		add_filter(
			'members_fp_offload_download_url',
			static function () {
				return 'https://s3.example.test/protected.pdf';
			}
		);

		$gatekeeper = new Gatekeeper(
			$repository,
			new \Members\FileProtection\Services\AccessChecker( $repository ),
			$delivery,
			$unauthorized,
			$settings,
			new \Members\FileProtection\Services\ShareTokenService(),
			new \Members\FileProtection\Services\DownloadLimitService( $repository ),
			new \Members\FileProtection\Services\OffloadIntegration( $repository, $settings )
		);

		$uploads = wp_upload_dir();
		$path    = $uploads['basedir'] . '/local-and-offload.pdf';

		if ( ! is_dir( $uploads['basedir'] ) ) {
			mkdir( $uploads['basedir'], 0755, true );
		}

		file_put_contents( $path, 'test' );

		$gatekeeper->handle( '/wp-content/uploads/local-and-offload.pdf' );

		unset( $GLOBALS['gatekeeper_test_user'], $GLOBALS['wpdb'] );

		$this->assertSame( 1, $delivery->calls );
		$this->assertSame( 0, $unauthorized->calls );

		unlink( $path );
	}

	public function test_range_continuation_does_not_record_download() {
		$delivery        = new GatekeeperStubDelivery();
		$download_limits = new GatekeeperCountingDownloadLimits( new GatekeeperAuthorizedRepository() );
		$gatekeeper      = $this->createAuthorizedGatekeeper( $delivery, $download_limits );
		$fixture         = $this->createProtectedPdfFixture( 'range-continuation.pdf' );

		$_SERVER['HTTP_RANGE'] = 'bytes=8192-16383';

		for ( $i = 0; $i < 20; ++$i ) {
			$gatekeeper->handle( $fixture['gateway'] );
		}

		$this->assertSame( 0, $download_limits->record_calls );
		$this->assertSame( 20, $delivery->calls );

		unlink( $fixture['path'] );
		$this->tearDownRangeServerVars();
	}

	public function test_initial_request_without_range_records_one_download() {
		$delivery        = new GatekeeperStubDelivery();
		$download_limits = new GatekeeperCountingDownloadLimits( new GatekeeperAuthorizedRepository() );
		$gatekeeper      = $this->createAuthorizedGatekeeper( $delivery, $download_limits );
		$fixture         = $this->createProtectedPdfFixture( 'range-initial.pdf' );

		$gatekeeper->handle( $fixture['gateway'] );

		$this->assertSame( 1, $download_limits->record_calls );
		$this->assertSame( 1, $delivery->calls );

		unlink( $fixture['path'] );
		$this->tearDownRangeServerVars();
	}

	public function test_initial_range_from_zero_records_one_download() {
		$delivery        = new GatekeeperStubDelivery();
		$download_limits = new GatekeeperCountingDownloadLimits( new GatekeeperAuthorizedRepository() );
		$gatekeeper      = $this->createAuthorizedGatekeeper( $delivery, $download_limits );
		$fixture         = $this->createProtectedPdfFixture( 'range-zero.pdf' );

		$_SERVER['HTTP_RANGE'] = 'bytes=0-';
		$gatekeeper->handle( $fixture['gateway'] );

		$this->assertSame( 1, $download_limits->record_calls );

		unlink( $fixture['path'] );
		$this->tearDownRangeServerVars();
	}

	public function test_playback_session_with_mixed_range_requests_records_single_download() {
		$delivery        = new GatekeeperStubDelivery();
		$download_limits = new GatekeeperCountingDownloadLimits( new GatekeeperAuthorizedRepository() );
		$gatekeeper      = $this->createAuthorizedGatekeeper( $delivery, $download_limits );
		$fixture         = $this->createProtectedPdfFixture( 'range-playback.pdf' );

		$_SERVER['HTTP_RANGE'] = 'bytes=0-8191';
		$gatekeeper->handle( $fixture['gateway'] );

		for ( $offset = 8192; $offset < 65536; $offset += 8192 ) {
			$_SERVER['HTTP_RANGE'] = 'bytes=' . $offset . '-';
			$gatekeeper->handle( $fixture['gateway'] );
		}

		$this->assertSame( 1, $download_limits->record_calls );

		unlink( $fixture['path'] );
		$this->tearDownRangeServerVars();
	}

	public function test_share_token_range_continuation_does_not_consume_use() {
		if ( ! function_exists( 'sanitize_text_field' ) ) {
			function sanitize_text_field( $str ) {
				return is_string( $str ) ? trim( strip_tags( $str ) ) : '';
			}
		}

		if ( ! function_exists( 'wp_unslash' ) ) {
			function wp_unslash( $value ) {
				return $value;
			}
		}

		$delivery     = new GatekeeperStubDelivery();
		$share_tokens = new GatekeeperCountingShareTokens();
		$gatekeeper   = $this->createAuthorizedGatekeeper(
			$delivery,
			new GatekeeperCountingDownloadLimits( new GatekeeperAuthorizedRepository() ),
			$share_tokens
		);
		$fixture = $this->createProtectedPdfFixture( 'range-share-token.pdf' );

		$_GET['members_fp_token'] = 'test-token';

		$_SERVER['HTTP_RANGE'] = 'bytes=0-';
		$gatekeeper->handle( $fixture['gateway'] );

		for ( $i = 0; $i < 19; ++$i ) {
			$_SERVER['HTTP_RANGE'] = 'bytes=' . ( ( $i + 1 ) * 4096 ) . '-';
			$gatekeeper->handle( $fixture['gateway'] );
		}

		$this->assertSame( 1, $share_tokens->consume_calls );

		unlink( $fixture['path'] );
		$this->tearDownRangeServerVars();
	}
}
