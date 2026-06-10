<?php
/**
 * Role-based access checker.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Contracts\AccessCheckerInterface;
use Members\FileProtection\Contracts\FileRepositoryInterface;

/**
 * Encapsulates file access decisions.
 */
class AccessChecker implements AccessCheckerInterface {

	/**
	 * @var FileRepositoryInterface
	 */
	private $repository;

	/**
	 * @param FileRepositoryInterface $repository File meta repository.
	 */
	public function __construct( FileRepositoryInterface $repository ) {
		$this->repository = $repository;
	}

	/**
	 * @inheritDoc
	 */
	public function canAccess( int $attachment_id, ?\WP_User $user ): bool {
		if ( $user && user_can( $user, 'manage_options' ) ) {
			return true;
		}

		if ( ! $this->repository->isProtected( $attachment_id ) ) {
			return true;
		}

		if ( ! $user || 0 === $user->ID ) {
			$allowed = false;
		} elseif ( $this->repository->allowsAllLoggedIn( $attachment_id ) ) {
			$allowed = true;
		} else {
			$roles   = $this->repository->getAllowedRoles( $attachment_id );
			$allowed = ! empty( $roles ) && members_user_has_role( $user->ID, $roles );
		}

		return (bool) apply_filters( 'members_file_access_check', $allowed, $attachment_id, $user );
	}
}
