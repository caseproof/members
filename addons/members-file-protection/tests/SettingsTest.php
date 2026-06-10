<?php

use Members\FileProtection\Services\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase {

	public function test_parses_extensions() {
		$settings = new Settings();
		$parsed   = $settings->parseExtensions( 'pdf, DOC ,mp3' );

		$this->assertSame( array( 'pdf', 'doc', 'mp3' ), $parsed );
	}

	public function test_default_extensions_exclude_video() {
		$settings = new Settings();
		$defaults = $settings->getDefaultExtensions();

		$this->assertContains( 'pdf', $defaults );
		$this->assertNotContains( 'mp4', $defaults );
		$this->assertNotContains( 'm4v', $defaults );
	}

	public function test_is_extension_protected() {
		$settings = new Settings();
		$this->assertTrue( $settings->isExtensionProtected( 'report.pdf' ) );
		$this->assertFalse( $settings->isExtensionProtected( 'photo.jpg' ) );
	}
}
