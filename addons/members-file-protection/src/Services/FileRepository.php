<?php
/**
 * WordPress post meta file repository.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Contracts\FileRepositoryInterface;

/**
 * Stores protection data on attachment post meta.
 */
class FileRepository implements FileRepositoryInterface {

	/**
	 * @inheritDoc
	 */
	public function isProtected( int $attachment_id ): bool {
		return (bool) get_post_meta( $attachment_id, '_members_file_protected', true );
	}

	/**
	 * @inheritDoc
	 */
	public function allowsAllLoggedIn( int $attachment_id ): bool {
		return (bool) get_post_meta( $attachment_id, '_members_file_all_logged_in', true );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedRoles( int $attachment_id ): array {
		$roles = get_post_meta( $attachment_id, '_members_file_access_role', false );

		if ( ! is_array( $roles ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'strval', $roles ),
				static function ( $role ) {
					return '' !== $role;
				}
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function setProtected( int $attachment_id, bool $protected ): void {
		update_post_meta( $attachment_id, '_members_file_protected', $protected ? 1 : 0 );
	}

	/**
	 * @inheritDoc
	 */
	public function setAllowedRoles( int $attachment_id, array $roles ): void {
		delete_post_meta( $attachment_id, '_members_file_access_role' );

		foreach ( $roles as $role ) {
			if ( is_string( $role ) && '' !== $role ) {
				add_post_meta( $attachment_id, '_members_file_access_role', sanitize_key( $role ) );
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function setAllowsAllLoggedIn( int $attachment_id, bool $allowed ): void {
		update_post_meta( $attachment_id, '_members_file_all_logged_in', $allowed ? 1 : 0 );
	}

	/**
	 * @inheritDoc
	 */
	public function getDownloadLimit( int $attachment_id ): int {
		return max( 0, (int) get_post_meta( $attachment_id, '_members_file_download_limit', true ) );
	}

	/**
	 * @inheritDoc
	 */
	public function setDownloadLimit( int $attachment_id, int $limit ): void {
		update_post_meta( $attachment_id, '_members_file_download_limit', max( 0, (int) $limit ) );
	}

	/**
	 * @inheritDoc
	 */
	public function resolveAttachmentId( string $file_path ): ?int {
		$file_path = $this->normalizePath( $file_path );

		if ( '' === $file_path ) {
			return null;
		}

		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['baseurl'] ) ) {
			$url = trailingslashit( $uploads['baseurl'] ) . ltrim( $file_path, '/' );
			$id  = attachment_url_to_postid( $url );

			if ( $id ) {
				return (int) $id;
			}
		}

		$query = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => '_wp_attached_file',
						'value' => $file_path,
					),
				),
			)
		);

		if ( ! empty( $query->posts[0] ) ) {
			return (int) $query->posts[0];
		}

		return $this->resolveImageSizeAttachmentId( $file_path );
	}

	/**
	 * Resolves thumbnail / intermediate image paths to a parent attachment.
	 *
	 * @param string $file_path Uploads-relative file path.
	 * @return int|null
	 */
	private function resolveImageSizeAttachmentId( string $file_path ): ?int {
		global $wpdb;

		$basename = wp_basename( $file_path );

		if ( '' === $basename ) {
			return null;
		}

		// Prefer matching the parent attachment from resized filename patterns.
		if ( preg_match( '/^(.+)-(\d+)x(\d+)\.([a-zA-Z0-9]+)$/', $basename, $matches ) ) {
			$original_name = $matches[1] . '.' . $matches[4];
			$dir           = trailingslashit( dirname( $file_path ) );
			$original_path = ( '.' === $dir ? '' : $dir ) . $original_name;

			$query = new \WP_Query(
				array(
					'post_type'              => 'attachment',
					'post_status'            => 'inherit',
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_query'             => array(
						array(
							'key'   => '_wp_attached_file',
							'value' => $original_path,
						),
					),
				)
			);

			if ( ! empty( $query->posts[0] ) ) {
				return (int) $query->posts[0];
			}
		}

		$serialized_fragment = 's:' . strlen( $basename ) . ':"' . $basename . '"';

		// Search serialized attachment metadata for an exact generated-size filename.
		$attachment_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_wp_attachment_metadata'
				AND meta_value LIKE %s
				LIMIT 1",
				'%' . $wpdb->esc_like( $serialized_fragment ) . '%'
			)
		);

		if ( $attachment_id > 0 ) {
			return $attachment_id;
		}

		return null;
	}

	/**
	 * Normalizes a gateway path to uploads-relative form.
	 *
	 * @param string $file_path Request path or URL fragment.
	 * @return string
	 */
	private function normalizePath( string $file_path ): string {
		$file_path = wp_strip_all_tags( rawurldecode( $file_path ) );
		$file_path = str_replace( '\\', '/', $file_path );

		if ( false !== strpos( $file_path, '?' ) ) {
			$file_path = strstr( $file_path, '?', true );
		}

		$uploads = wp_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? parse_url( $uploads['baseurl'], PHP_URL_PATH ) : '';
		$home    = parse_url( home_url(), PHP_URL_PATH );

		if ( $baseurl && 0 === strpos( $file_path, $baseurl ) ) {
			$file_path = substr( $file_path, strlen( $baseurl ) );
		} elseif ( $home && 0 === strpos( $file_path, $home ) ) {
			$file_path = substr( $file_path, strlen( $home ) );
			if ( $baseurl && 0 === strpos( $file_path, $baseurl ) ) {
				$file_path = substr( $file_path, strlen( $baseurl ) );
			}
		}

		return ltrim( $file_path, '/' );
	}
}
