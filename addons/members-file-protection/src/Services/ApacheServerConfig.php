<?php
/**
 * Apache / LiteSpeed .htaccess configuration.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Contracts\ServerConfigInterface;

/**
 * Writes rewrite rules to the uploads .htaccess file.
 */
class ApacheServerConfig implements ServerConfigInterface {

	private const MARKER_START = '# BEGIN Members File Protection';
	private const MARKER_END   = '# END Members File Protection';

	/** @var Settings */
	private $settings;

	/**
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @inheritDoc
	 */
	public function write( array $extensions ): bool {
		if ( empty( $extensions ) ) {
			return false;
		}

		$block = $this->getConfigBlock( $extensions );
		$file  = $this->htaccessPath();

		if ( ! $this->ensureFilesystem() ) {
			update_option( 'members_fp_htaccess_manual', 1 );
			return false;
		}

		global $wp_filesystem;

		$existing = $wp_filesystem->exists( $file ) ? $wp_filesystem->get_contents( $file ) : '';

		if ( false === $existing ) {
			update_option( 'members_fp_htaccess_manual', 1 );
			return false;
		}

		$updated = $this->insertWithMarkers( $existing, $block );

		if ( ! $wp_filesystem->put_contents( $file, $updated, FS_CHMOD_FILE ) ) {
			update_option( 'members_fp_htaccess_manual', 1 );
			return false;
		}

		delete_option( 'members_fp_htaccess_manual' );
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function remove(): bool {
		$file = $this->htaccessPath();

		if ( ! file_exists( $file ) ) {
			return true;
		}

		if ( ! $this->ensureFilesystem() ) {
			return false;
		}

		global $wp_filesystem;

		$existing = $wp_filesystem->get_contents( $file );

		if ( false === $existing ) {
			return false;
		}

		$updated = preg_replace(
			'/' . preg_quote( self::MARKER_START, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . '\s*/s',
			'',
			$existing
		);

		return (bool) $wp_filesystem->put_contents( $file, $updated, FS_CHMOD_FILE );
	}

	/**
	 * @inheritDoc
	 */
	public function isActive(): bool {
		$file = $this->htaccessPath();

		if ( ! is_readable( $file ) ) {
			return false;
		}

		$contents = file_get_contents( $file );

		return is_string( $contents ) && false !== strpos( $contents, self::MARKER_START );
	}

	/**
	 * @inheritDoc
	 */
	public function getConfigBlock( array $extensions ): string {
		$regex = $this->extensionRegex( $extensions );
		$index = esc_url( home_url( 'index.php' ) );
		$index = wp_parse_url( $index, PHP_URL_PATH );

		if ( ! $index ) {
			$index = '/index.php';
		}

		$lines = array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteCond %{REQUEST_FILENAME} -f',
			'RewriteRule ' . $regex . ' ' . $index . '?members_fp_gateway=%{REQUEST_URI} [QSA,L]',
			'</IfModule>',
		);

		return implode( "\n", $lines );
	}

	/**
	 * @inheritDoc
	 */
	public function matchesExtensions( array $extensions ): bool {
		if ( ! $this->isActive() || empty( $extensions ) ) {
			return false;
		}

		$file = $this->htaccessPath();

		if ( ! is_readable( $file ) ) {
			return false;
		}

		$contents = file_get_contents( $file );

		if ( ! is_string( $contents ) || ! preg_match( '/RewriteRule \\\.\(([^)]+)\)\$/', $contents, $matches ) ) {
			return false;
		}

		$in_file  = explode( '|', $matches[1] );
		$expected = $extensions;

		sort( $in_file );
		sort( $expected );

		return $in_file === $expected;
	}

	/**
	 * Human-readable server label.
	 *
	 * @return string
	 */
	public function getServerLabel(): string {
		$software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';

		if ( false !== strpos( $software, 'litespeed' ) ) {
			return 'LiteSpeed';
		}

		return 'Apache';
	}

	/**
	 * @return string
	 */
	private function htaccessPath(): string {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . '.htaccess';
	}

	/**
	 * @param string[] $extensions Extensions.
	 * @return string
	 */
	private function extensionRegex( array $extensions ): string {
		$escaped = array_map( 'preg_quote', $extensions );
		return '\\.(' . implode( '|', $escaped ) . ')$';
	}

	/**
	 * @return bool
	 */
	private function ensureFilesystem(): bool {
		global $wp_filesystem;

		if ( $wp_filesystem instanceof \WP_Filesystem_Base ) {
			return true;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		return (bool) WP_Filesystem();
	}

	/**
	 * @param string $content Existing file content.
	 * @param string $block   New block.
	 * @return string
	 */
	private function insertWithMarkers( string $content, string $block ): string {
		$wrapped = self::MARKER_START . "\n" . $block . "\n" . self::MARKER_END . "\n";

		if ( false !== strpos( $content, self::MARKER_START ) ) {
			return (string) preg_replace(
				'/' . preg_quote( self::MARKER_START, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . '\s*/s',
				$wrapped,
				$content
			);
		}

		return rtrim( $content ) . "\n\n" . $wrapped;
	}
}
