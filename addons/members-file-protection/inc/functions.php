<?php
/**
 * Public template functions for File Protection.
 *
 * @package MembersFileProtection
 */

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Services\DownloadUrlService;

/**
 * Returns a download URL for an attachment (counts toward per-user limits).
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function members_fp_get_download_url( $attachment_id ) {
	return DownloadUrlService::getUrl( (int) $attachment_id );
}
