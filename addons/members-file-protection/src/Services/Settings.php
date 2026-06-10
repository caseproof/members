<?php
/**
 * Plugin settings helper.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and validates file protection settings.
 */
class Settings {

	public const OPTION_EXTENSIONS           = 'members_fp_extensions';
	public const OPTION_PROTECT_IMAGES       = 'members_fp_protect_images';
	public const OPTION_PROTECT_VIDEO        = 'members_fp_protect_video';
	public const OPTION_SIGNED_URL_TTL       = 'members_fp_signed_url_ttl';
	public const OPTION_UNAUTHORIZED         = 'members_fp_unauthorized_behavior';
	public const OPTION_REDIRECT_URL         = 'members_fp_redirect_url';
	public const DEFAULT_EXTENSIONS          = 'pdf,doc,docx,xls,xlsx,xlsm,ppt,pptx,zip,gz,tar,rar,mp3,m4a';
	public const IMAGE_EXTENSIONS            = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );
	public const VIDEO_EXTENSIONS            = array( 'mp4', 'm4v', 'webm', 'mov' );

	/**
	 * Default extension list without video formats.
	 *
	 * @return string[]
	 */
	public function getDefaultExtensions(): array {
		return $this->parseExtensions( self::DEFAULT_EXTENSIONS );
	}

	/**
	 * Configured extensions including optional image types.
	 *
	 * @return string[]
	 */
	public function getExtensions(): array {
		$stored = get_option( self::OPTION_EXTENSIONS, self::DEFAULT_EXTENSIONS );
		$exts   = $this->parseExtensions( is_array( $stored ) ? implode( ',', $stored ) : (string) $stored );

		if ( $this->protectImages() ) {
			$exts = array_values( array_unique( array_merge( $exts, self::IMAGE_EXTENSIONS ) ) );
		}

		if ( $this->protectVideo() ) {
			$exts = array_values( array_unique( array_merge( $exts, self::VIDEO_EXTENSIONS ) ) );
		}

		return $exts;
	}

	/**
	 * @param string $raw Comma-separated extensions.
	 * @return string[]
	 */
	public function parseExtensions( string $raw ): array {
		$parts = array_map( 'trim', explode( ',', strtolower( $raw ) ) );
		$parts = array_filter(
			$parts,
			static function ( $ext ) {
				return '' !== $ext && preg_match( '/^[a-z0-9]+$/', $ext );
			}
		);

		return array_values( array_unique( $parts ) );
	}

	/**
	 * @return bool
	 */
	public function protectImages(): bool {
		return (bool) get_option( self::OPTION_PROTECT_IMAGES, false );
	}

	/**
	 * @return bool
	 */
	public function protectVideo(): bool {
		return (bool) get_option( self::OPTION_PROTECT_VIDEO, false );
	}

	/**
	 * Signed URL TTL in seconds for object storage delivery.
	 *
	 * @return int
	 */
	public function getSignedUrlTtl(): int {
		$ttl = (int) get_option( self::OPTION_SIGNED_URL_TTL, 3600 );
		return max( 60, $ttl );
	}

	/**
	 * @return string 404|redirect
	 */
	public function getUnauthorizedBehavior(): string {
		$behavior = get_option( self::OPTION_UNAUTHORIZED, '404' );
		return 'redirect' === $behavior ? 'redirect' : '404';
	}

	/**
	 * @return string
	 */
	public function getRedirectUrl(): string {
		$url = get_option( self::OPTION_REDIRECT_URL, '{login_url}' );
		return is_string( $url ) ? $url : '{login_url}';
	}

	/**
	 * Whether pretty permalinks are enabled.
	 *
	 * @return bool
	 */
	public function hasPrettyPermalinks(): bool {
		return '' !== get_option( 'permalink_structure', '' );
	}

	/**
	 * Whether the file extension is in the protected list.
	 *
	 * @param string $filename File name or path.
	 * @return bool
	 */
	public function isExtensionProtected( string $filename ): bool {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( '' === $ext ) {
			return false;
		}

		return in_array( $ext, $this->getExtensions(), true );
	}
}
