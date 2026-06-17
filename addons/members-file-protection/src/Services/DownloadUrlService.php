<?php
/**
 * Builds download URLs for protected attachments.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Returns attachment URLs that count toward download limits.
 */
class DownloadUrlService {

	/**
	 * Returns a URL that triggers a counted download for the attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Empty when the attachment has no URL.
	 */
	public static function getUrl( int $attachment_id ): string {
		$url = wp_get_attachment_url( $attachment_id );

		if ( ! $url ) {
			return '';
		}

		$download_url = add_query_arg( DownloadRequestDetector::QUERY_ARG, '1', $url );

		return (string) apply_filters( 'members_fp_download_url', $download_url, $attachment_id, $url );
	}
}
