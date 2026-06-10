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
	 * Allows access when the user can view a public or permitted parent post.
	 *
	 * @param bool     $allowed       Current decision.
	 * @param int      $attachment_id Attachment ID.
	 * @param \WP_User $user          Current user.
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

		foreach ( $posts as $post_id ) {
			if ( ! function_exists( 'members_has_post_permissions' ) || ! members_has_post_permissions( $post_id ) ) {
				return true;
			}

			if ( function_exists( 'members_can_user_view_post' ) && members_can_user_view_post( $user_id, $post_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @return int[]
	 */
	private function findReferencingPosts( int $attachment_id ): array {
		global $wpdb;

		$url   = wp_get_attachment_url( $attachment_id );
		$ids   = array();
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );

		if ( empty( $types ) ) {
			return array();
		}

		$thumbnail = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d",
				$attachment_id
			)
		);

		$ids = array_merge( $ids, array_map( 'intval', (array) $thumbnail ) );

		if ( $url ) {
			$type_list = array_values( $types );
			$holders   = implode( ',', array_fill( 0, count( $type_list ), '%s' ) );
			$args      = array_merge( $type_list, array( '%' . $wpdb->esc_like( $url ) . '%' ) );
			$sql       = "SELECT ID FROM {$wpdb->posts}
				WHERE post_type IN ({$holders})
				AND post_status IN ('publish','private','draft')
				AND post_content LIKE %s";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built from post types.
			$content_ids = $wpdb->get_col( call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $args ) ) );

			$ids = array_merge( $ids, array_map( 'intval', (array) $content_ids ) );
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}
}
