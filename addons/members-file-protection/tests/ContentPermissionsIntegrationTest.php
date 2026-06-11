<?php

use Members\FileProtection\Contracts\FileRepositoryInterface;
use Members\FileProtection\Services\ContentPermissionsIntegration;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/src/Services/ContentPermissionsIntegration.php';

class ContentPermissionsRepositoryStub implements FileRepositoryInterface {

	private $protected = array();

	public function setProtected( int $attachment_id, bool $value ): void {
		$this->protected[ $attachment_id ] = $value;
	}

	public function isProtected( int $attachment_id ): bool {
		return ! empty( $this->protected[ $attachment_id ] );
	}

	public function allowsAllLoggedIn( int $attachment_id ): bool {
		return false;
	}

	public function getAllowedRoles( int $attachment_id ): array {
		return array();
	}

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

class ContentPermissionsIntegrationTestable extends ContentPermissionsIntegration {

	private $posts = array();

	public function setReferencingPosts( array $posts ) {
		$this->posts = $posts;
	}

	protected function findReferencingPosts( int $attachment_id ): array {
		return $this->posts;
	}

	public function forceEnabled( $enabled ) {
		global $members_fp_test_options;
		$members_fp_test_options[ ContentPermissionsIntegration::OPTION_INHERIT ] = $enabled;
	}
}

if ( ! function_exists( 'members_content_permissions_enabled' ) ) {
	function members_content_permissions_enabled() {
		return true;
	}
}

if ( ! function_exists( 'members_has_post_permissions' ) ) {
	function members_has_post_permissions( $post_id = '' ) {
		global $members_fp_test_post_permissions;
		return ! empty( $members_fp_test_post_permissions[ (int) $post_id ] );
	}
}

if ( ! function_exists( 'members_can_user_view_post' ) ) {
	function members_can_user_view_post( $user_id, $post_id = '' ) {
		global $members_fp_test_can_view;
		$key = (int) $user_id . ':' . (int) $post_id;

		return ! empty( $members_fp_test_can_view[ $key ] );
	}
}

class ContentPermissionsIntegrationTest extends TestCase {

	public function test_skips_individually_protected_files() {
		$repo = new ContentPermissionsRepositoryStub();
		$repo->setProtected( 5, true );
		$integration = new ContentPermissionsIntegrationTestable( $repo );
		$integration->forceEnabled( true );
		$integration->setReferencingPosts( array( 10 ) );

		$this->assertFalse( $integration->filterAccessCheck( false, 5, null ) );
		$this->assertTrue( $integration->filterAccessCheck( true, 5, null ) );
	}

	public function test_denies_when_restricted_parent_blocks_user() {
		global $members_fp_test_post_permissions, $members_fp_test_can_view;

		$members_fp_test_post_permissions = array( 10 => true );
		$members_fp_test_can_view         = array();

		$integration = new ContentPermissionsIntegrationTestable( new ContentPermissionsRepositoryStub() );
		$integration->forceEnabled( true );
		$integration->setReferencingPosts( array( 10 ) );

		$this->assertFalse( $integration->filterAccessCheck( true, 5, null ) );
	}

	public function test_allows_when_user_can_view_restricted_parent() {
		global $members_fp_test_post_permissions, $members_fp_test_can_view;

		$members_fp_test_post_permissions = array( 10 => true );
		$members_fp_test_can_view         = array( '7:10' => true );

		$user                = new WP_User();
		$user->ID            = 7;
		$user->allcaps       = array();

		$integration = new ContentPermissionsIntegrationTestable( new ContentPermissionsRepositoryStub() );
		$integration->forceEnabled( true );
		$integration->setReferencingPosts( array( 10 ) );

		$this->assertTrue( $integration->filterAccessCheck( true, 5, $user ) );
	}

	public function test_allows_when_only_public_parents_reference_file() {
		global $members_fp_test_post_permissions;

		$members_fp_test_post_permissions = array();

		$integration = new ContentPermissionsIntegrationTestable( new ContentPermissionsRepositoryStub() );
		$integration->forceEnabled( true );
		$integration->setReferencingPosts( array( 10 ) );

		$this->assertTrue( $integration->filterAccessCheck( true, 5, null ) );
	}

	public function test_requires_access_to_at_least_one_restricted_parent() {
		global $members_fp_test_post_permissions, $members_fp_test_can_view;

		$members_fp_test_post_permissions = array( 10 => true, 11 => true );
		$members_fp_test_can_view         = array( '7:11' => true );

		$user                = new WP_User();
		$user->ID            = 7;
		$user->allcaps       = array();

		$integration = new ContentPermissionsIntegrationTestable( new ContentPermissionsRepositoryStub() );
		$integration->forceEnabled( true );
		$integration->setReferencingPosts( array( 10, 11 ) );

		$this->assertTrue( $integration->filterAccessCheck( true, 5, $user ) );
	}

	public function test_allows_when_mixed_public_and_restricted_parents_exist() {
		global $members_fp_test_post_permissions, $members_fp_test_can_view;

		$members_fp_test_post_permissions = array( 10 => true );
		$members_fp_test_can_view         = array();

		$integration = new ContentPermissionsIntegrationTestable( new ContentPermissionsRepositoryStub() );
		$integration->forceEnabled( true );
		$integration->setReferencingPosts( array( 10, 20 ) );

		$this->assertTrue( $integration->filterAccessCheck( true, 5, null ) );
	}
}
