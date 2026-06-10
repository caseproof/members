<?php

use Members\FileProtection\Services\ApacheServerConfig;
use Members\FileProtection\Services\Settings;
use PHPUnit\Framework\TestCase;

class ApacheServerConfigTest extends TestCase {

	public function test_config_block_contains_markers_and_extensions() {
		$config = new ApacheServerConfig( new Settings() );
		$block  = $config->getConfigBlock( array( 'pdf', 'zip' ) );

		$this->assertStringContainsString( 'RewriteEngine On', $block );
		$this->assertStringContainsString( 'members_fp_gateway', $block );
		$this->assertStringContainsString( '(pdf|zip)', $block );
	}

	public function test_insert_with_markers_is_idempotent() {
		$config   = new ApacheServerConfig( new Settings() );
		$block    = $config->getConfigBlock( array( 'pdf' ) );
		$existing = "# Existing\n";
		$method   = new ReflectionMethod( ApacheServerConfig::class, 'insertWithMarkers' );
		$method->setAccessible( true );
		$first  = $method->invoke( $config, $existing, $block );
		$second = $method->invoke( $config, $first, $block );

		$this->assertSame( substr_count( $second, '# BEGIN Members File Protection' ), 1 );
	}
}
