<?php

use Members\FileProtection\Contracts\FileRepositoryInterface;
use Members\FileProtection\Services\AccessChecker;
use PHPUnit\Framework\TestCase;

class FileRepositoryStub implements FileRepositoryInterface {

	private $protected = array();
	private $all_logged = array();
	private $roles = array();

	public function setFixture( $id, $protected, $all_logged, array $roles ) {
		$this->protected[ $id ]  = $protected;
		$this->all_logged[ $id ] = $all_logged;
		$this->roles[ $id ]      = $roles;
	}

	public function isProtected( int $attachment_id ): bool {
		return ! empty( $this->protected[ $attachment_id ] );
	}

	public function allowsAllLoggedIn( int $attachment_id ): bool {
		return ! empty( $this->all_logged[ $attachment_id ] );
	}

	public function getAllowedRoles( int $attachment_id ): array {
		return isset( $this->roles[ $attachment_id ] ) ? $this->roles[ $attachment_id ] : array();
	}

	public function setProtected( int $attachment_id, bool $protected ): void {}

	public function setAllowedRoles( int $attachment_id, array $roles ): void {}

	public function setAllowsAllLoggedIn( int $attachment_id, bool $allowed ): void {}

	public function getDownloadLimit( int $attachment_id ): int {
		return 0;
	}

	public function setDownloadLimit( int $attachment_id, int $limit ): void {}

	public function resolveAttachmentId( string $file_path ): ?int {
		return null;
	}
}

class AccessCheckerTest extends TestCase {

	private function user( $id, array $caps = array(), array $roles = array() ) {
		global $members_fp_test_user_roles;
		$user                = new WP_User();
		$user->ID            = $id;
		$user->allcaps       = $caps;
		$members_fp_test_user_roles = $roles;
		return $user;
	}

	public function test_manage_options_bypass() {
		$repo    = new FileRepositoryStub();
		$repo->setFixture( 1, true, false, array() );
		$checker = new AccessChecker( $repo );
		$user    = $this->user( 1, array( 'manage_options' => true ), array() );

		$this->assertTrue( $checker->canAccess( 1, $user ) );
	}

	public function test_unprotected_file_allows_everyone() {
		$repo    = new FileRepositoryStub();
		$repo->setFixture( 1, false, false, array() );
		$checker = new AccessChecker( $repo );

		$this->assertTrue( $checker->canAccess( 1, null ) );
	}

	public function test_logged_out_denied_for_protected_file() {
		$repo    = new FileRepositoryStub();
		$repo->setFixture( 1, true, false, array( 'subscriber' ) );
		$checker = new AccessChecker( $repo );

		$this->assertFalse( $checker->canAccess( 1, null ) );
	}

	public function test_all_logged_in_allows_authenticated_user() {
		$repo    = new FileRepositoryStub();
		$repo->setFixture( 1, true, true, array() );
		$checker = new AccessChecker( $repo );
		$user    = $this->user( 5, array(), array( 'subscriber' ) );

		$this->assertTrue( $checker->canAccess( 1, $user ) );
	}

	public function test_matching_role_allows_access() {
		$repo    = new FileRepositoryStub();
		$repo->setFixture( 1, true, false, array( 'premium_member' ) );
		$checker = new AccessChecker( $repo );
		$user    = $this->user( 5, array(), array( 'premium_member' ) );

		$this->assertTrue( $checker->canAccess( 1, $user ) );
	}

	public function test_zero_roles_blocks_non_admin() {
		$repo    = new FileRepositoryStub();
		$repo->setFixture( 1, true, false, array() );
		$checker = new AccessChecker( $repo );
		$user    = $this->user( 5, array(), array( 'subscriber' ) );

		$this->assertFalse( $checker->canAccess( 1, $user ) );
	}

	public function test_unprotected_file_runs_access_filter() {
		$repo    = new FileRepositoryStub();
		$repo->setFixture( 1, false, false, array() );
		$checker = new AccessChecker( $repo );

		add_filter(
			'members_file_access_check',
			static function ( $allowed ) {
				return false;
			}
		);

		$this->assertFalse( $checker->canAccess( 1, null ) );

		remove_all_filters( 'members_file_access_check' );
	}
}
