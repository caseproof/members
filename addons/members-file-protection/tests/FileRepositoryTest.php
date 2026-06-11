<?php

use Members\FileProtection\Services\FileRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/src/Services/FileRepository.php';

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {

		/** @var int[] */
		public $posts = array();

		/**
		 * @param array $args Query arguments.
		 */
		public function __construct( array $args ) {
			global $members_fp_test_wp_query_handler;

			if ( is_callable( $members_fp_test_wp_query_handler ) ) {
				$this->posts = (array) call_user_func( $members_fp_test_wp_query_handler, $args );
			}
		}
	}
}

if ( ! function_exists( 'attachment_url_to_postid' ) ) {
	function attachment_url_to_postid( $url ) {
		global $members_fp_test_attachment_url_map;

		if ( ! is_array( $members_fp_test_attachment_url_map ) ) {
			return 0;
		}

		return isset( $members_fp_test_attachment_url_map[ $url ] )
			? (int) $members_fp_test_attachment_url_map[ $url ]
			: 0;
	}
}

if ( ! function_exists( 'wp_basename' ) ) {
	function wp_basename( $path ) {
		return basename( str_replace( '\\', '/', $path ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return strip_tags( $string );
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		return $single ? '' : array();
	}
}

class FileRepositoryTest extends TestCase {

	protected function tearDown(): void {
		unset(
			$GLOBALS['members_fp_test_wp_query_handler'],
			$GLOBALS['members_fp_test_attachment_url_map'],
			$GLOBALS['wpdb']
		);
	}

	/**
	 * @param callable $handler Receives WP_Query args, returns attachment ID list.
	 * @return void
	 */
	private function stub_wp_query( callable $handler ) {
		$GLOBALS['members_fp_test_wp_query_handler'] = $handler;
	}

	/**
	 * @param int|null $attachment_id Attachment ID returned by metadata lookup.
	 * @return void
	 */
	private function stub_metadata_lookup( $attachment_id ) {
		$GLOBALS['wpdb'] = new class( $attachment_id ) {
			private $attachment_id;

			public $postmeta = 'wp_postmeta';

			public function __construct( $attachment_id ) {
				$this->attachment_id = $attachment_id;
			}

			public function prepare( $query, ...$args ) {
				return $query;
			}

			public function esc_like( $text ) {
				return addcslashes( $text, '_%\\' );
			}

			public function get_var( $query ) {
				return null === $this->attachment_id ? null : (string) (int) $this->attachment_id;
			}
		};
	}

	public function test_resolve_attachment_id_finds_original_file() {
		$this->stub_wp_query(
			static function ( array $args ) {
				$value = $args['meta_query'][0]['value'] ?? '';

				if ( '2026/06/photo.jpg' === $value ) {
					return array( 100 );
				}

				return array();
			}
		);

		$repository = new FileRepository();

		$this->assertSame(
			100,
			$repository->resolveAttachmentId( '2026/06/photo.jpg' )
		);
	}

	public function test_resolve_image_variant_via_original_filename_pattern() {
		$GLOBALS['members_fp_test_attachment_url_map'] = array();

		$this->stub_wp_query(
			static function ( array $args ) {
				$value = $args['meta_query'][0]['value'] ?? '';

				if ( '2026/06/photo-300x225.jpg' === $value ) {
					return array();
				}

				if ( '2026/06/photo.jpg' === $value ) {
					return array( 100 );
				}

				return array();
			}
		);

		$this->stub_metadata_lookup( null );

		$repository = new FileRepository();

		$this->assertSame(
			100,
			$repository->resolveAttachmentId( '2026/06/photo-300x225.jpg' )
		);
	}

	public function test_resolve_image_variant_via_attachment_metadata_fallback() {
		$GLOBALS['members_fp_test_attachment_url_map'] = array();

		$this->stub_wp_query(
			static function () {
				return array();
			}
		);

		$this->stub_metadata_lookup( 205 );

		$repository = new FileRepository();

		$this->assertSame(
			205,
			$repository->resolveAttachmentId( '2026/06/photo-150x150-cropped.jpg' )
		);
	}

	public function test_unresolved_variant_returns_null() {
		$GLOBALS['members_fp_test_attachment_url_map'] = array();

		$this->stub_wp_query(
			static function () {
				return array();
			}
		);

		$this->stub_metadata_lookup( null );

		$repository = new FileRepository();

		$this->assertSame(
			null,
			$repository->resolveAttachmentId( '2026/06/missing-300x225.jpg' )
		);
	}

	public function test_gateway_url_normalizes_to_uploads_relative_path() {
		$this->stub_wp_query(
			static function ( array $args ) {
				$value = $args['meta_query'][0]['value'] ?? '';

				if ( '2026/06/photo-768x576.jpg' === $value ) {
					return array();
				}

				if ( '2026/06/photo.jpg' === $value ) {
					return array( 100 );
				}

				return array();
			}
		);

		$this->stub_metadata_lookup( null );

		$repository = new FileRepository();

		$this->assertSame(
			100,
			$repository->resolveAttachmentId( '/wp-content/uploads/2026/06/photo-768x576.jpg' )
		);
	}
}
