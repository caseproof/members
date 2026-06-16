<?php
/**
 * Detects explicit file download requests vs inline viewing.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Determines whether a gateway request should count toward download limits.
 */
class DownloadRequestDetector {

	public const QUERY_ARG = 'members_fp_download';

	/**
	 * Extensions that browsers typically save rather than display inline.
	 *
	 * @var string[]
	 */
	private const DOWNLOAD_EXTENSIONS = array(
		'doc',
		'docx',
		'xls',
		'xlsx',
		'xlsm',
		'ppt',
		'pptx',
		'zip',
		'gz',
		'tar',
		'rar',
	);

	/**
	 * Whether the current request is an explicit download (not inline viewing).
	 *
	 * @param string $file_path File name or path.
	 * @return bool
	 */
	public static function isDownloadRequest( string $file_path = '' ): bool {
		if ( self::hasExplicitDownloadParam() ) {
			return true;
		}

		if ( self::hasBrowserDownloadIntent() ) {
			return true;
		}

		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		if ( '' !== $extension && in_array( $extension, self::DOWNLOAD_EXTENSIONS, true ) ) {
			return true;
		}

		return (bool) apply_filters( 'members_fp_is_download_request', false, $file_path );
	}

	/**
	 * Content-Disposition value for the current request.
	 *
	 * @param string $file_path File name or path.
	 * @return string attachment|inline
	 */
	public static function contentDisposition( string $file_path ): string {
		return self::isDownloadRequest( $file_path ) ? 'attachment' : 'inline';
	}

	/**
	 * @return bool
	 */
	private static function hasExplicitDownloadParam(): bool {
		if ( isset( $_GET[ self::QUERY_ARG ] ) ) {
			return rest_sanitize_boolean( wp_unslash( $_GET[ self::QUERY_ARG ] ) );
		}

		$query_var = get_query_var( self::QUERY_ARG, '' );

		if ( is_string( $query_var ) && '' !== $query_var ) {
			return rest_sanitize_boolean( $query_var );
		}

		return false;
	}

	/**
	 * @return bool
	 */
	private static function hasBrowserDownloadIntent(): bool {
		if ( ! isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ) {
			return false;
		}

		return 'download' === strtolower( (string) wp_unslash( $_SERVER['HTTP_SEC_FETCH_DEST'] ) );
	}
}
