<?php

use Members\FileProtection\Services\NginxServerConfig;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/src/Services/Settings.php';
require_once dirname( __DIR__ ) . '/src/Services/NginxServerConfig.php';

class NginxServerConfigTest extends TestCase {

	public function test_config_block_passes_path_and_query_separately() {
		$nginx = new NginxServerConfig( new \Members\FileProtection\Services\Settings() );
		$block = $nginx->getConfigBlock( array( 'pdf', 'mp4' ) );

		$this->assertStringContainsString( 'members_fp_gateway=$uri?', $block );
		$this->assertFalse( false !== strpos( $block, '$request_uri' ), 'Nginx config must not use $request_uri' );
	}

	public function test_response_indicates_protection_for_redirects() {
		$this->assertTrue( NginxServerConfig::responseIndicatesProtection( 302 ) );
		$this->assertTrue( NginxServerConfig::responseIndicatesProtection( 404 ) );
	}

	public function test_response_indicates_protection_rejects_file_delivery() {
		$this->assertFalse( NginxServerConfig::responseIndicatesProtection( 200 ) );
		$this->assertFalse( NginxServerConfig::responseIndicatesProtection( 206 ) );
	}
}
