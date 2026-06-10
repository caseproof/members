<?php

use Members\FileProtection\Services\NginxServerConfig;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/src/Services/NginxServerConfig.php';

class NginxServerConfigTest extends TestCase {

	public function test_response_indicates_protection_for_redirects() {
		$this->assertTrue( NginxServerConfig::responseIndicatesProtection( 302 ) );
		$this->assertTrue( NginxServerConfig::responseIndicatesProtection( 404 ) );
	}

	public function test_response_indicates_protection_rejects_file_delivery() {
		$this->assertFalse( NginxServerConfig::responseIndicatesProtection( 200 ) );
		$this->assertFalse( NginxServerConfig::responseIndicatesProtection( 206 ) );
	}
}
