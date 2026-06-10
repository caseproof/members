<?php
/**
 * File delivery contract.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Streams a file to the client.
 */
interface FileDeliveryInterface {

	/**
	 * Sends headers and streams the file. Exits the request.
	 *
	 * @param string $absolute_path Absolute filesystem path.
	 * @param int    $attachment_id Attachment ID for context.
	 * @return void
	 */
	public function deliver( string $absolute_path, int $attachment_id ): void;
}
