<?php
/**
 * File repository contract.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Read/write protection meta and resolve attachments from paths.
 */
interface FileRepositoryInterface {

	/**
	 * Whether the attachment is marked protected.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function isProtected( int $attachment_id ): bool;

	/**
	 * Whether all logged-in users may access the attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function allowsAllLoggedIn( int $attachment_id ): bool;

	/**
	 * Allowed role slugs for the attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, string>
	 */
	public function getAllowedRoles( int $attachment_id ): array;

	/**
	 * Sets the protected flag.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $protected     Protected state.
	 * @return void
	 */
	public function setProtected( int $attachment_id, bool $protected ): void;

	/**
	 * Sets allowed roles (replaces existing).
	 *
	 * @param int        $attachment_id Attachment ID.
	 * @param string[]   $roles         Role slugs.
	 * @return void
	 */
	public function setAllowedRoles( int $attachment_id, array $roles ): void;

	/**
	 * Sets the all-logged-in shortcut flag.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $allowed       Whether all logged-in users are allowed.
	 * @return void
	 */
	public function setAllowsAllLoggedIn( int $attachment_id, bool $allowed ): void;

	/**
	 * Per-user download limit (0 = unlimited).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int
	 */
	public function getDownloadLimit( int $attachment_id ): int;

	/**
	 * Sets the per-user download limit.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $limit         Max downloads per user (0 = unlimited).
	 * @return void
	 */
	public function setDownloadLimit( int $attachment_id, int $limit ): void;

	/**
	 * Resolves an attachment ID from a request path or URL fragment.
	 *
	 * @param string $file_path Path or URL relative to site uploads.
	 * @return int|null Null when not found.
	 */
	public function resolveAttachmentId( string $file_path ): ?int;
}
