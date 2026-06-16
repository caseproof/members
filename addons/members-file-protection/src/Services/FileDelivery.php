<?php
/**
 * Streams files to the client.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Contracts\FileDeliveryInterface;
use Members\FileProtection\Services\DownloadRequestDetector;

/**
 * Secure chunked file delivery.
 */
class FileDelivery implements FileDeliveryInterface {

	/**
	 * @inheritDoc
	 */
	public function deliver( string $absolute_path, int $attachment_id ): void {
		$absolute_path = wp_normalize_path( $absolute_path );
		$uploads       = wp_upload_dir();
		$basedir       = wp_normalize_path( $uploads['basedir'] );

		if ( 0 !== strpos( $absolute_path, $basedir ) || ! is_file( $absolute_path ) || ! is_readable( $absolute_path ) ) {
			status_header( 404 );
			exit;
		}

		$real = realpath( $absolute_path );

		if ( false === $real || 0 !== strpos( wp_normalize_path( $real ), $basedir ) ) {
			status_header( 404 );
			exit;
		}

		$filename = wp_basename( $real );
		$type     = wp_check_filetype( $filename );
		$mime     = $type['type'] ? $type['type'] : 'application/octet-stream';
		$size     = (int) filesize( $real );
		$start    = 0;
		$end      = $size - 1;
		$length   = $size;
		$status   = 200;
		$method   = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';

		if ( $size > 0 && isset( $_SERVER['HTTP_RANGE'] ) && preg_match( '/bytes=(\d*)-(\d*)/', wp_unslash( $_SERVER['HTTP_RANGE'] ), $matches ) ) {
			if ( '' !== $matches[1] ) {
				$start = (int) $matches[1];
				$end   = '' !== $matches[2] ? (int) $matches[2] : $size - 1;
			} elseif ( '' !== $matches[2] ) {
				$suffix = (int) $matches[2];

				if ( $suffix >= $size ) {
					$start = 0;
					$end   = $size - 1;
				} else {
					$start = $size - $suffix;
					$end   = $size - 1;
				}
			}

			if ( $start > $end || $start >= $size ) {
				status_header( 416 );
				header( 'Content-Range: bytes */' . (string) $size );
				exit;
			}

			$end    = min( $end, $size - 1 );
			$length = $end - $start + 1;
			$status = 206;
		}

		if ( ! headers_sent() ) {
			status_header( $status );
			CacheHeaders::sendNoStore();
			header( 'Accept-Ranges: bytes' );
			header( 'X-Robots-Tag: noindex, nofollow', true );
			header( 'Content-Type: ' . $mime );

			if ( 206 === $status ) {
				header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $size );
			}

			header( 'Content-Length: ' . (string) $length );
			header(
				'Content-Disposition: ' . DownloadRequestDetector::contentDisposition( $filename ) . '; filename="' . rawurlencode( $filename ) . '"'
			);
			header( 'Pragma: no-cache' );
		}

		if ( 'HEAD' === $method ) {
			exit;
		}

		$handle = fopen( $real, 'rb' );

		if ( false === $handle ) {
			status_header( 404 );
			exit;
		}

		if ( $start > 0 ) {
			fseek( $handle, $start );
		}

		$remaining = $length;

		while ( $remaining > 0 && ! feof( $handle ) ) {
			$read = (int) min( 8192, $remaining );
			echo fread( $handle, $read ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$remaining -= $read;

			if ( function_exists( 'flush' ) ) {
				flush();
			}
		}

		fclose( $handle );
		exit;
	}
}
