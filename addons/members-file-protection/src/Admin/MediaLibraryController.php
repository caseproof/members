<?php
/**
 * Media Library list, bulk, and grid modal integration.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Admin;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Capabilities;
use Members\FileProtection\Container;
use Members\FileProtection\Contracts\FileRepositoryInterface;
use Members\FileProtection\Services\Settings;

/**
 * Media Library list view, bulk actions, and read-only grid badge integration.
 */
class MediaLibraryController {

	/** @var Container */
	private $container;

	/**
	 * @param Container $container Service container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;

		add_filter( 'manage_media_columns', array( $this, 'addColumn' ) );
		add_action( 'manage_media_custom_column', array( $this, 'renderColumn' ), 10, 2 );
		add_filter( 'bulk_actions-upload', array( $this, 'bulkActions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handleBulkActions' ), 10, 3 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepareAttachmentForJs' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * @param string[] $columns Media columns.
	 * @return string[]
	 */
	public function addColumn( array $columns ): array {
		$columns['members_fp_protected'] = __( 'File Protection', 'members' );
		return $columns;
	}

	/**
	 * @param string $column  Column name.
	 * @param int    $post_id Attachment ID.
	 * @return void
	 */
	public function renderColumn( string $column, int $post_id ): void {
		if ( 'members_fp_protected' !== $column ) {
			return;
		}

		$repository = $this->container->get( FileRepositoryInterface::class );
		$settings   = $this->container->get( Settings::class );
		$file       = get_attached_file( $post_id );
		$extension  = $file ? strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) : '';

		if ( ! $settings->isExtensionProtected( 'file.' . $extension ) ) {
			echo '<span class="members-fp-media-column members-fp-media-column--na" aria-label="' . esc_attr__( 'Extension not protected', 'members' ) . '">—</span>';
			return;
		}

		if ( $repository->isProtected( $post_id ) ) {
			echo '<span class="members-fp-media-column members-fp-media-column--yes">' . esc_html__( 'Protected', 'members' ) . '</span>';
			return;
		}

		echo '<span class="members-fp-media-column members-fp-media-column--no">' . esc_html__( 'Public', 'members' ) . '</span>';
	}

	/**
	 * @param string[] $actions Bulk actions.
	 * @return string[]
	 */
	public function bulkActions( array $actions ): array {
		if ( ! Capabilities::currentUserCanManage() ) {
			return $actions;
		}

		$actions['members_fp_protect']   = __( 'Protect files', 'members' );
		$actions['members_fp_unprotect'] = __( 'Remove file protection', 'members' );

		return $actions;
	}

	/**
	 * @param string $redirect Redirect URL.
	 * @param string $action   Bulk action.
	 * @param int[]  $ids      Attachment IDs.
	 * @return string
	 */
	public function handleBulkActions( string $redirect, string $action, array $ids ): string {
		if ( ! Capabilities::currentUserCanManage() || empty( $ids ) ) {
			return $redirect;
		}

		$repository = $this->container->get( FileRepositoryInterface::class );
		$changed    = 0;

		foreach ( $ids as $id ) {
			$id = (int) $id;

			if ( $id <= 0 || 'attachment' !== get_post_type( $id ) ) {
				continue;
			}

			if ( 'members_fp_protect' === $action ) {
				$repository->setProtected( $id, true );
				++$changed;
			} elseif ( 'members_fp_unprotect' === $action ) {
				$repository->setProtected( $id, false );
				++$changed;
			}
		}

		if ( $changed > 0 ) {
			$redirect = add_query_arg( 'members_fp_bulk', $changed, $redirect );
		}

		return $redirect;
	}

	/**
	 * @param array    $response Attachment JS data.
	 * @param \WP_Post $attachment Attachment post.
	 * @return array
	 */
	public function prepareAttachmentForJs( array $response, $attachment ): array {
		$repository = $this->container->get( FileRepositoryInterface::class );
		$settings   = $this->container->get( Settings::class );
		$file       = get_attached_file( $attachment->ID );
		$extension  = $file ? strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) : '';

		$response['membersFpProtected']    = $repository->isProtected( (int) $attachment->ID );
		$response['membersFpExtensionOn']  = $settings->isExtensionProtected( 'file.' . $extension );

		return $response;
	}

	/**
	 * @param string $hook Admin hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( ! Capabilities::currentUserCanManage() ) {
			return;
		}

		if ( ! in_array( $hook, array( 'upload.php', 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		// Block editor screens enqueue the media modal badge via BlockEditorController.
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $this->usesBlockEditor( $hook ) ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'members-file-protection-admin',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/admin.css',
			array( 'dashicons' ),
			'1.0.7'
		);

		wp_enqueue_script(
			'members-file-protection-media-library',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/block-editor.js',
			array( 'wp-i18n' ),
			'1.0.3',
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'members-file-protection-media-library', 'members' );
		}
	}

	/**
	 * Whether the current admin screen uses the block editor.
	 *
	 * @param string $hook Admin hook suffix.
	 * @return bool
	 */
	private function usesBlockEditor( string $hook ): bool {
		if ( ! function_exists( 'use_block_editor_for_post' ) || ! function_exists( 'use_block_editor_for_post_type' ) ) {
			return false;
		}

		if ( 'post-new.php' === $hook ) {
			$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';

			return (bool) use_block_editor_for_post_type( $post_type );
		}

		if ( 'post.php' === $hook ) {
			$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

			if ( $post_id <= 0 ) {
				return false;
			}

			return (bool) use_block_editor_for_post( $post_id );
		}

		return false;
	}
}
