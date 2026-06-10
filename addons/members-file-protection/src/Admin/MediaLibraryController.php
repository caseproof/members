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
 * Adds protection controls to the Media Library and attachment modal.
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
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachmentFields' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'saveAttachmentFields' ), 10, 2 );
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
	 * Adds fields to the attachment details panel (grid modal and list quick edit).
	 *
	 * @param array    $form_fields Existing fields.
	 * @param \WP_Post $post        Attachment post.
	 * @return array
	 */
	public function attachmentFields( array $form_fields, $post ): array {
		if ( ! Capabilities::currentUserCanManage() ) {
			return $form_fields;
		}

		$settings   = $this->container->get( Settings::class );
		$repository = $this->container->get( FileRepositoryInterface::class );
		$file       = get_attached_file( $post->ID );
		$extension  = $file ? strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) : '';

		if ( ! $settings->isExtensionProtected( 'file.' . $extension ) ) {
			$form_fields['members_fp_notice'] = array(
				'label' => __( 'File Protection', 'members' ),
				'input' => 'html',
				'html'  => '<p class="description">' . esc_html__( 'This file type is not in the protected extensions list.', 'members' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=members-file-protection' ) ) . '">' . esc_html__( 'Settings', 'members' ) . '</a></p>',
			);

			return $form_fields;
		}

		$protected  = $repository->isProtected( (int) $post->ID );
		$all_logged = $repository->allowsAllLoggedIn( (int) $post->ID );
		$roles      = $repository->getAllowedRoles( (int) $post->ID );
		$dl_limit   = $repository->getDownloadLimit( (int) $post->ID );

		global $wp_roles;
		$_wp_roles = apply_filters( 'members_wp_roles', $wp_roles->role_names, $post );
		asort( $_wp_roles );

		ob_start();
		?>
		<div class="members-fp-media-fields" data-members-fp-media-fields>
			<p>
				<label>
					<input type="checkbox" name="attachments[<?php echo esc_attr( (string) $post->ID ); ?>][members_fp_protected]" value="1" <?php checked( $protected ); ?> data-members-fp-toggle />
					<?php esc_html_e( 'Enable file protection', 'members' ); ?>
				</label>
			</p>
			<div class="members-fp-media-fields__roles<?php echo $protected ? '' : ' is-hidden'; ?>" data-members-fp-roles>
				<p>
					<label>
						<input type="checkbox" name="attachments[<?php echo esc_attr( (string) $post->ID ); ?>][members_fp_all_logged_in]" value="1" <?php checked( $all_logged ); ?> data-members-fp-all-logged-in />
						<?php esc_html_e( 'Allow all logged-in users', 'members' ); ?>
					</label>
				</p>
				<fieldset class="members-fp-metabox__role-list<?php echo $all_logged ? ' is-disabled' : ''; ?>" data-members-fp-role-fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Allowed roles', 'members' ); ?></legend>
					<ul>
						<?php foreach ( $_wp_roles as $role => $name ) : ?>
							<li>
								<label>
									<input type="checkbox" name="attachments[<?php echo esc_attr( (string) $post->ID ); ?>][members_fp_roles][]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $roles, true ) ); ?> <?php disabled( $all_logged ); ?> data-members-fp-role />
									<?php echo esc_html( translate_user_role( $name ) ); ?>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
				</fieldset>
				<p>
					<label>
						<?php esc_html_e( 'Download limit per user', 'members' ); ?>
						<input type="number" min="0" step="1" class="small-text" name="attachments[<?php echo esc_attr( (string) $post->ID ); ?>][members_fp_download_limit]" value="<?php echo esc_attr( (string) $dl_limit ); ?>" />
					</label>
				</p>
				<p class="description">
					<a href="<?php echo esc_url( get_edit_post_link( $post->ID, 'raw' ) ); ?>">
						<?php esc_html_e( 'Open attachment for share links and advanced options', 'members' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		$form_fields['members_fp_protection'] = array(
			'label' => __( 'File Protection', 'members' ),
			'input' => 'html',
			'html'  => $html,
		);

		return $form_fields;
	}

	/**
	 * @param array $post       Attachment post data.
	 * @param array $attachment Submitted attachment fields.
	 * @return array
	 */
	public function saveAttachmentFields( array $post, array $attachment ): array {
		if ( ! Capabilities::currentUserCanManage() || empty( $post['ID'] ) ) {
			return $post;
		}

		$post_id = (int) $post['ID'];

		if ( ! isset( $attachment['members_fp_protected'] ) && ! isset( $attachment['members_fp_all_logged_in'] ) && ! isset( $attachment['members_fp_roles'] ) ) {
			return $post;
		}

		$repository = $this->container->get( FileRepositoryInterface::class );
		$roles      = isset( $attachment['members_fp_roles'] ) ? array_map( 'sanitize_key', (array) $attachment['members_fp_roles'] ) : array();

		$roles = array_values(
			array_filter(
				$roles,
				static function ( $role ) {
					return '' !== $role && function_exists( 'members_role_exists' ) && members_role_exists( $role );
				}
			)
		);

		$repository->setProtected( $post_id, ! empty( $attachment['members_fp_protected'] ) );
		$repository->setAllowsAllLoggedIn( $post_id, ! empty( $attachment['members_fp_all_logged_in'] ) );
		$repository->setAllowedRoles( $post_id, $roles );
		$repository->setDownloadLimit( $post_id, isset( $attachment['members_fp_download_limit'] ) ? (int) $attachment['members_fp_download_limit'] : 0 );

		return $post;
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

		if ( 'upload.php' !== $hook && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'members-file-protection-admin',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/admin.css',
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'members-file-protection-media',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/media.js',
			array(),
			'1.0.0',
			true
		);
	}
}
