<?php
/**
 * Nginx configuration generator.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Contracts\ServerConfigInterface;

/**
 * Generates Nginx location blocks; cannot auto-write server config.
 */
class NginxServerConfig implements ServerConfigInterface {

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
		update_option( 'members_fp_nginx_manual', 1 );
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function remove(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function isActive(): bool {
		$uploads = wp_upload_dir();
		$test    = $this->findProtectedTestUrl();

		if ( ! $test ) {
			return false;
		}

		$response = wp_remote_get(
			$test,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'cookies'     => array(),
				'headers'     => array(
					'Cache-Control' => 'no-cache',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return self::responseIndicatesProtection( $code );
	}

	/**
	 * Whether an HTTP status indicates the gatekeeper intercepted the request.
	 *
	 * @param int $code HTTP status code.
	 * @return bool
	 */
	public static function responseIndicatesProtection( int $code ): bool {
		if ( in_array( $code, array( 200, 206 ), true ) ) {
			return false;
		}

		return in_array( $code, array( 301, 302, 303, 307, 403, 404 ), true );
	}

	/**
	 * @inheritDoc
	 */
	public function getConfigBlock( array $extensions ): string {
		$exts    = implode( '|', array_map( 'preg_quote', $extensions ) );
		$uploads = wp_upload_dir();
		$path    = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );

		if ( ! $path ) {
			$path = '/wp-content/uploads';
		}

		$index = wp_parse_url( home_url( 'index.php' ), PHP_URL_PATH );

		if ( ! $index ) {
			$index = '/index.php';
		}

		$lines = array(
			'location ~* ^' . preg_quote( rtrim( $path, '/' ), '/' ) . '/.+\.(' . $exts . ')$ {',
			'    rewrite ^ ' . $index . '?members_fp_gateway=$request_uri? last;',
			'}',
		);

		return implode( "\n", $lines );
	}

	/**
	 * @inheritDoc
	 */
	public function matchesExtensions( array $extensions ): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getServerLabel(): string {
		return 'Nginx';
	}

	/**
	 * Finds a protected attachment URL for loopback testing.
	 *
	 * @return string|null
	 */
	private function findProtectedTestUrl(): ?string {
		$query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_members_file_protected',
						'value' => '1',
					),
				),
			)
		);

		if ( empty( $query->posts[0] ) ) {
			return null;
		}

		$url = wp_get_attachment_url( (int) $query->posts[0] );

		return $url ? $url : null;
	}
}
