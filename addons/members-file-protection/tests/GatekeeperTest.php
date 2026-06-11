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

class GatekeeperTest extends TestCase {

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
}
