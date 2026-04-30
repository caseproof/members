<?php
/**
 * Tests for Admin Menus URL slug extraction and admin bar href normalization.
 *
 * @package Members
 */

namespace Members\Tests;

use WP_UnitTestCase;
use function Members\AddOns\AdminMenus\members_am_normalize_toolbar_href;
use function Members\AddOns\AdminMenus\members_am_slugs_for_admin_redirect_url;

/**
 * @covers \Members\AddOns\AdminMenus\members_am_slugs_for_admin_redirect_url
 * @covers \Members\AddOns\AdminMenus\members_am_normalize_toolbar_href
 */
class AdminMenusSlugToolbarTest extends WP_UnitTestCase {

	/**
	 * @param string $url Full admin URL.
	 * @return array
	 */
	private function slugs( $url ) {
		return members_am_slugs_for_admin_redirect_url( $url );
	}

	/**
	 * @param string $href Href to normalize.
	 * @return string
	 */
	private function norm( $href ) {
		return members_am_normalize_toolbar_href( $href );
	}

	public function test_slugs_edit_pages_list_omits_bare_edit_php() {
		$url   = admin_url( 'edit.php?post_type=page' );
		$slugs = $this->slugs( $url );
		$this->assertContains( 'edit.php?post_type=page', $slugs );
		$this->assertNotContains( 'edit.php', $slugs, 'Bare edit.php must not match Posts-only hidden ids when post_type is not post.' );
	}

	public function test_slugs_edit_posts_list_includes_bare_edit_php() {
		$url   = admin_url( 'edit.php?post_type=post' );
		$slugs = $this->slugs( $url );
		$this->assertContains( 'edit.php', $slugs );
		$this->assertContains( 'edit.php?post_type=post', $slugs );
	}

	public function test_slugs_cpt_post_new_composite() {
		$url   = admin_url( 'post-new.php?post_type=book' );
		$slugs = $this->slugs( $url );
		$this->assertContains( 'post-new.php?post_type=book', $slugs );
		$this->assertNotContains( 'post-new.php', $slugs );
	}

	public function test_slugs_tools_page_composite() {
		$url   = admin_url( 'tools.php?page=export' );
		$slugs = $this->slugs( $url );
		$this->assertContains( 'tools.php', $slugs );
		$this->assertContains( 'export', $slugs );
		$this->assertContains( 'tools.php?page=export', $slugs );
	}

	public function test_slugs_edit_tags_taxonomy_composite() {
		$url   = admin_url( 'edit-tags.php?taxonomy=category' );
		$slugs = $this->slugs( $url );
		$this->assertContains( 'edit-tags.php', $slugs );
		$this->assertContains( 'edit-tags.php?taxonomy=category', $slugs );
	}

	public function test_slugs_page_query_must_be_string() {
		// Duplicate keys: last wins in PHP query string parse; ensure no TypeError if page were ever non-string.
		$url   = admin_url( 'admin.php?page=members-settings' );
		$slugs = $this->slugs( $url );
		$this->assertContains( 'members-settings', $slugs );
		$this->assertContains( 'admin.php?page=members-settings', $slugs );
	}

	public function test_normalize_empty_hash_javascript() {
		$this->assertSame( '', $this->norm( '' ) );
		$this->assertSame( '', $this->norm( '#' ) );
		$this->assertSame( '', $this->norm( 'javascript:void(0)' ) );
		$this->assertSame( '', $this->norm( 'JavaScript:alert(1)' ) );
	}

	public function test_normalize_rejects_whitespace_in_href() {
		$this->assertSame( '', $this->norm( '/wp-admin/plugins.php ' ) );
	}

	public function test_normalize_relative_admin_script() {
		$out = $this->norm( 'plugins.php' );
		$this->assertNotSame( '', $out );
		$this->assertStringContainsString( 'plugins.php', $out );
		$parts = wp_parse_url( $out );
		$this->assertIsArray( $parts );
		$this->assertSame( wp_parse_url( site_url( '/' ), PHP_URL_HOST ), $parts['host'] );
	}

	public function test_normalize_full_admin_url_round_trip() {
		$in  = admin_url( 'options-general.php' );
		$out = $this->norm( $in );
		$this->assertSame( $in, $out );
	}

	public function test_normalize_rejects_foreign_host() {
		$this->assertSame( '', $this->norm( 'https://example.com/wp-admin/plugins.php' ) );
	}
}
