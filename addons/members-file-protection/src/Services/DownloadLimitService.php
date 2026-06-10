<?php
/**
 * Per-user download limit tracking.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Contracts\FileRepositoryInterface;

/**
 * Enforces and logs per-file download limits.
 */
class DownloadLimitService {

	/** @var FileRepositoryInterface */
	private $repository;

	/**
	 * @param FileRepositoryInterface $repository File meta repository.
	 */
	public function __construct( FileRepositoryInterface $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Whether the user may download (under limit).
	 *
	 * @param int           $attachment_id Attachment ID.
	 * @param \WP_User|null $user          Current user.
	 * @return bool
	 */
	public function canDownload( $attachment_id, $user ) {
		$limit = $this->repository->getDownloadLimit( (int) $attachment_id );

		if ( $limit <= 0 ) {
			return true;
		}

		if ( ! $user || 0 === $user->ID ) {
			return false;
		}

		return $this->getUserDownloadCount( (int) $attachment_id, (int) $user->ID ) < $limit;
	}

	/**
	 * Logs a download for rate limiting.
	 *
	 * @param int           $attachment_id Attachment ID.
	 * @param \WP_User|null $user          Current user.
	 * @return void
	 */
	public function recordDownload( $attachment_id, $user ) {
		global $wpdb;

		$wpdb->insert(
			Installer::downloadLogTable(),
			array(
				'attachment_id' => (int) $attachment_id,
				'user_id'       => $user ? (int) $user->ID : 0,
				'downloaded_at' => current_time( 'mysql', true ),
				'ip_address'    => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			),
			array( '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @param int $user_id       User ID.
	 * @return int
	 */
	public function getUserDownloadCount( $attachment_id, $user_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . Installer::downloadLogTable() . ' WHERE attachment_id = %d AND user_id = %d',
				(int) $attachment_id,
				(int) $user_id
			)
		);
	}
}
