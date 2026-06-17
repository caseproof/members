<?php
/**
 * Unauthorized access handler contract.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Handles denied file access requests.
 */
interface UnauthorizedHandlerInterface {

	/**
	 * Sends the configured response and exits.
	 *
	 * @param int           $attachment_id Attachment ID (0 if unknown).
	 * @param \WP_User|null $user          Current user.
	 * @param string        $context       Optional denial context (e.g. download_limit).
	 * @return void
	 */
	public function handle( int $attachment_id, ?\WP_User $user, string $context = '' ): void;
}
