<?php
/**
 * Optional inheritance from Members Content Permissions.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Contracts\FileRepositoryInterface;

/**
 * Applies post-level content permission rules to embedded attachments.
 */
class ContentPermissionsIntegration {

	public const OPTION_INHERIT = 'members_fp_inherit_content_permissions';

	/** @var FileRepositoryInterface */
	private $repository;

	/**
	 * @param FileRepositoryInterface $repository File meta repository.
	 */
	public function __construct( FileRepositoryInterface $repository ) {
		$this->repository = $repository;
	}

	/**
	 * @return void
	 */
	public function registerHooks() {
		add_filter( 'members_file_access_check', array( $this, 'filterAccessCheck' ), 10, 3 );
	}

	/**
	 * @return bool
	 */
	public function isEnabled(): bool {
		return (bool) get_option( self::OPTION_INHERIT, false )
			&& function_exists( 'members_content_permissions_enabled' )
			&& members_content_permissions_enabled();
	}

	/**
	 * Allows access when the user can view a permitted parent or embedding post.
	 *
	 * @param bool          $allowed       Current decision.
	 * @param int           $attachment_id Attachment ID.
	 * @param \WP_User|null $user          Current user.
	 * @return bool
	 */
	public function filterAccessCheck( $allowed, $attachment_id, $user ) {
		if ( ! $this->isEnabled() || $this->repository->isProtected( (int) $attachment_id ) ) {
			return $allowed;
		}

		$user_id = $user && $user->ID > 0 ? (int) $user->ID : 0;
		$posts   = $this->findReferencingPosts( (int) $attachment_id );

		if ( empty( $posts ) ) {
			return $allowed;
		}

		$restricted = array();

		foreach ( $posts as $post_id ) {
			if ( function_exists( 'members_has_post_permissions' ) && members_has_post_permissions( $post_id ) ) {
				$restricted[] = (int) $post_id;
			}
		}

		if ( empty( $restricted ) ) {
			return true;
		}

		foreach ( $restricted as $post_id ) {
			if ( function_exists( 'members_can_user_view_post' ) && members_can_user_view_post( $user_id, $post_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Finds posts that reference an attachment in content, blocks, or featured image.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int[]
	 */
	protected function findReferencingPosts( int $attachment_id ): array {
		global $wpdb;

		$ids   = array();
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );

		if ( empty( $types ) ) {
			return array();
		}

		$parent = wp_get_post_parent_id( $attachment_id );

		if ( $parent > 0 ) {
			$ids[] = (int) $parent;
		}

		$thumbnail = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d",
				$attachment_id
			)
		);

		$ids = array_merge( $ids, array_map( 'intval', (array) $thumbnail ) );

		$type_list = array_values( $types );
		$holders   = implode( ',', array_fill( 0, count( $type_list ), '%s' ) );

		foreach ( $this->getContentSearchNeedles( $attachment_id ) as $needle ) {
			if ( '' === $needle ) {
				continue;
			}

			$args = array_merge( $type_list, array( '%' . $wpdb->esc_like( $needle ) . '%' ) );
			$sql  = "SELECT ID FROM {$wpdb->posts}
				WHERE post_type IN ({$holders})
				AND post_status IN ('publish','private','draft')
				AND post_content LIKE %s";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built from post types.
			$content_ids = $wpdb->get_col( call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $args ) ) );

			$ids = array_merge( $ids, array_map( 'intval', (array) $content_ids ) );
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Builds content search fragments for attachment URLs, paths, and block IDs.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string[]
	 */
	protected function getContentSearchNeedles( int $attachment_id ): array {
		$needles = array(
			'"id":' . $attachment_id,
			'"id": ' . $attachment_id,
		);

		$url = wp_get_attachment_url( $attachment_id );

		if ( $url ) {
			$needles[] = $url;

			$path = wp_parse_url( $url, PHP_URL_PATH );

			if ( is_string( $path ) && '' !== $path ) {
				$needles[] = $path;
			}
		}

		$file = get_attached_file( $attachment_id );

		if ( is_string( $file ) && '' !== $file ) {
			$needles[] = wp_basename( $file );
			$needles[] = str_replace( '\\', '/', $file );

			$uploads = wp_upload_dir();

			if ( ! empty( $uploads['basedir'] ) && ! empty( $uploads['baseurl'] ) ) {
				$relative = ltrim( str_replace( wp_normalize_path( trailingslashit( $uploads['basedir'] ) ), '', wp_normalize_path( $file ) ), '/' );

				if ( '' !== $relative ) {
					$needles[] = $relative;
				}
			}
		}

		return array_values( array_unique( array_filter( $needles ) ) );
	}
}
