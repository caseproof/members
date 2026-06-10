<?php
/**
 * Access checker contract.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Determines whether a user may access a protected file.
 */
interface AccessCheckerInterface {

	/**
	 * Returns true if the user may access the attachment.
	 *
	 * Users with manage_options always pass. Result is filterable via
	 * members_file_access_check after internal checks (except manage_options bypass).
	 *
	 * @param int           $attachment_id Attachment ID.
	 * @param \WP_User|null $user          Current user or null when logged out.
	 * @return bool
	 */
	public function canAccess( int $attachment_id, ?\WP_User $user ): bool;
}
