<?php
/**
 * Attachment edit screen protection metabox.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Admin;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Capabilities;
use Members\FileProtection\Container;
use Members\FileProtection\Contracts\FileRepositoryInterface;
use Members\FileProtection\Services\Settings;
use Members\FileProtection\Services\ShareTokenService;

/**
 * Renders and saves the File Protection metabox on attachments.
 */
class MetaboxController {

	/** @var Container */
	private $container;

	/**
	 * @param Container $container Service container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
		add_action( 'load-post.php', array( $this, 'load' ) );
	}

	/**
	 * Registers metabox hooks for attachment screens.
	 *
	 * @return void
	 */
	public function load() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'attachment' !== $screen->post_type ) {
			return;
		}

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
		add_action( 'edit_attachment', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * @return void
	 */
	public function enqueue() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'attachment' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'members-file-protection-admin',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/admin.css',
			array( 'dashicons' ),
			'1.2.2'
		);

		wp_enqueue_script(
			'members-file-protection-metabox',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/metabox.js',
			array(),
			'1.2.2',
			true
		);

		wp_localize_script(
			'members-file-protection-metabox',
			'membersFileProtectionMetabox',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'members_fp_metabox' ),
				'attachmentId'  => (int) get_the_ID(),
				'i18n'          => array(
					'generate' => __( 'Generate link', 'members' ),
					'copy'     => __( 'Copy', 'members' ),
					'copied'   => __( 'Copied!', 'members' ),
					'revoke'   => __( 'Revoke', 'members' ),
					'revokeAll' => __( 'Revoke all links', 'members' ),
					'revokeAllConfirm' => __( 'Revoke all share links for this file?', 'members' ),
					'revokeAllError' => __( 'Could not revoke share links.', 'members' ),
					'error'    => __( 'Could not create share link.', 'members' ),
					'revokeError' => __( 'Could not revoke share link.', 'members' ),
					'protected' => __( 'Protected', 'members' ),
					'public'   => __( 'Public', 'members' ),
				),
			)
		);
	}

	/**
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function add_meta_box( $post_type ) {
		if ( 'attachment' !== $post_type || ! Capabilities::currentUserCanManage() ) {
			return;
		}

		add_meta_box(
			'members-file-protection',
			__( 'File Protection', 'members' ),
			array( $this, 'render' ),
			'attachment',
			'normal',
			'low'
		);
	}

	/**
	 * @param \WP_Post $post Attachment post.
	 * @return void
	 */
	public function render( $post ) {
		$settings   = $this->container->get( Settings::class );
		$repository = $this->container->get( FileRepositoryInterface::class );
		$file       = get_attached_file( $post->ID );
		$extension  = $file ? strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) : '';
		$enabled    = $settings->isExtensionProtected( 'file.' . $extension );
		$protected  = $repository->isProtected( (int) $post->ID );
		$all_logged = $repository->allowsAllLoggedIn( (int) $post->ID );
		$roles      = $repository->getAllowedRoles( (int) $post->ID );
		$dl_limit   = $repository->getDownloadLimit( (int) $post->ID );
		$tokens     = $protected ? $this->container->get( ShareTokenService::class )->listForAttachment( (int) $post->ID ) : array();

		global $wp_roles;
		$_wp_roles = apply_filters( 'members_wp_roles', $wp_roles->role_names, $post );
		asort( $_wp_roles );

		wp_nonce_field( 'members_fp_metabox', 'members_fp_metabox_nonce' );

		$settings_url = admin_url( 'admin.php?page=members-file-protection' );
		$download_url = members_fp_get_download_url( (int) $post->ID );
		$shortcode    = '[members_fp_download id="' . (int) $post->ID . '"]' . __( 'Download', 'members' ) . '[/members_fp_download]';
		?>
		<div class="members-fp-metabox" data-members-fp-metabox>
			<?php if ( ! $enabled ) : ?>
				<div class="notice notice-info inline members-fp-metabox__notice">
					<p>
						<?php
						printf(
							/* translators: 1: file extension, 2: extension */
							esc_html__( '%1$s files are not in your protected extensions list.', 'members' ),
							esc_html( strtoupper( $extension ) )
						);
						?>
						<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Add extension in settings', 'members' ); ?></a>
					</p>
				</div>
			<?php else : ?>
				<div class="members-fp-metabox__header">
					<label class="members-fp-toggle">
						<input type="checkbox" name="members_fp_protected" value="1" role="switch" aria-checked="<?php echo $protected ? 'true' : 'false'; ?>" <?php checked( $protected ); ?> data-members-fp-toggle />
						<span class="members-fp-toggle__track" aria-hidden="true"><span class="members-fp-toggle__thumb"></span></span>
						<span class="members-fp-toggle__label"><?php esc_html_e( 'Protect this file', 'members' ); ?></span>
					</label>
					<span class="members-fp-status members-fp-status--<?php echo $protected ? 'on' : 'off'; ?>" data-members-fp-status>
						<?php echo $protected ? esc_html__( 'Protected', 'members' ) : esc_html__( 'Public', 'members' ); ?>
					</span>
				</div>
				<p class="description members-fp-metabox__intro<?php echo $protected ? ' is-hidden' : ''; ?>" data-members-fp-intro><?php esc_html_e( 'Enable protection to restrict who can access this file.', 'members' ); ?></p>

				<div class="members-fp-metabox__settings<?php echo $protected ? '' : ' is-hidden'; ?>" data-members-fp-roles>
					<div class="members-fp-metabox__grid">
						<section class="members-fp-panel members-fp-panel--access">
							<h3 class="members-fp-panel__title">
								<span class="dashicons dashicons-groups" aria-hidden="true"></span>
								<?php esc_html_e( 'Who can access', 'members' ); ?>
							</h3>

							<label class="members-fp-option members-fp-option--highlight">
								<input type="checkbox" name="members_fp_all_logged_in" value="1" <?php checked( $all_logged ); ?> data-members-fp-all-logged-in />
								<span class="members-fp-option__label"><?php esc_html_e( 'All logged-in users', 'members' ); ?></span>
							</label>

							<fieldset class="members-fp-metabox__role-list<?php echo $all_logged ? ' is-disabled' : ''; ?>" data-members-fp-role-fieldset>
								<legend class="members-fp-panel__legend"><?php esc_html_e( 'Or specific roles', 'members' ); ?></legend>
								<?php if ( empty( $_wp_roles ) ) : ?>
									<p class="description"><?php esc_html_e( 'No roles found. Enable “All logged-in users” above.', 'members' ); ?></p>
								<?php else : ?>
									<div class="members-fp-metabox__role-scroll">
										<ul>
											<?php foreach ( $_wp_roles as $role => $name ) : ?>
												<li>
													<label class="members-fp-option members-fp-option--compact">
														<input
															type="checkbox"
															name="members_fp_roles[]"
															value="<?php echo esc_attr( $role ); ?>"
															<?php checked( in_array( $role, $roles, true ) ); ?>
															<?php disabled( $all_logged ); ?>
															data-members-fp-role
														/>
														<span class="members-fp-option__label"><?php echo esc_html( translate_user_role( $name ) ); ?></span>
													</label>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>
							</fieldset>

							<div class="notice notice-warning inline members-fp-metabox__warning is-hidden" role="alert" data-members-fp-warning>
								<p><?php esc_html_e( 'Select at least one role, or enable “All logged-in users”.', 'members' ); ?></p>
							</div>

							<p class="description members-fp-panel__footnote"><?php esc_html_e( 'Administrators always have access.', 'members' ); ?></p>
						</section>

						<section class="members-fp-panel members-fp-panel--limit">
							<h3 class="members-fp-panel__title">
								<span class="dashicons dashicons-download" aria-hidden="true"></span>
								<?php esc_html_e( 'Download limit', 'members' ); ?>
							</h3>
							<div class="members-fp-limit-control">
								<label for="members_fp_download_limit" class="members-fp-limit-control__label"><?php esc_html_e( 'Per user', 'members' ); ?></label>
								<input type="number" min="0" step="1" class="small-text" id="members_fp_download_limit" name="members_fp_download_limit" value="<?php echo esc_attr( (string) $dl_limit ); ?>" />
								<span class="description"><?php esc_html_e( '0 = unlimited', 'members' ); ?></span>
							</div>
							<p class="description members-fp-panel__footnote"><?php esc_html_e( 'Inline viewing does not count. Share links and admins are exempt.', 'members' ); ?></p>
						</section>
					</div>

					<section class="members-fp-panel members-fp-panel--sharing" data-members-fp-sharing>
						<h3 class="members-fp-panel__title">
							<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
							<?php esc_html_e( 'Sharing', 'members' ); ?>
						</h3>

						<?php if ( '' !== $download_url ) : ?>
							<div class="members-fp-subsection" data-members-fp-download-section>
								<h4 class="members-fp-subsection__title"><?php esc_html_e( 'Member download link', 'members' ); ?></h4>
								<p class="description members-fp-subsection__desc"><?php esc_html_e( 'Counts toward the download limit for logged-in users.', 'members' ); ?></p>
								<div class="members-fp-copy-field">
									<input type="text" class="members-fp-copy-field__input" readonly value="<?php echo esc_attr( $download_url ); ?>" aria-label="<?php esc_attr_e( 'Download URL', 'members' ); ?>" data-members-fp-select-on-click />
									<button type="button" class="button" data-members-fp-share-copy><?php esc_html_e( 'Copy URL', 'members' ); ?></button>
								</div>
								<div class="members-fp-copy-field members-fp-copy-field--shortcode">
									<input type="text" class="members-fp-copy-field__input members-fp-copy-field__input--mono" readonly value="<?php echo esc_attr( $shortcode ); ?>" aria-label="<?php esc_attr_e( 'Download shortcode', 'members' ); ?>" data-members-fp-select-on-click />
									<button type="button" class="button" data-members-fp-shortcode-copy><?php esc_html_e( 'Copy shortcode', 'members' ); ?></button>
								</div>
							</div>
						<?php endif; ?>

						<div class="members-fp-subsection<?php echo '' !== $download_url ? ' members-fp-subsection--divider' : ''; ?>" data-members-fp-share>
								<div class="members-fp-panel__head">
									<h4 class="members-fp-subsection__title"><?php esc_html_e( 'Private share links', 'members' ); ?></h4>
									<button type="button" class="button-link members-fp-revoke-all<?php echo empty( $tokens ) ? ' is-hidden' : ''; ?>" data-members-fp-share-revoke-all><?php esc_html_e( 'Revoke all', 'members' ); ?></button>
								</div>
								<p class="description members-fp-subsection__desc"><?php esc_html_e( 'Guest links that bypass login and download limits.', 'members' ); ?></p>

								<div class="members-fp-share-form">
									<label class="members-fp-share-form__field">
										<span class="members-fp-share-form__label"><?php esc_html_e( 'Expires', 'members' ); ?></span>
										<select data-members-fp-share-expires>
											<option value="hour"><?php esc_html_e( '1 hour', 'members' ); ?></option>
											<option value="day" selected><?php esc_html_e( '1 day', 'members' ); ?></option>
											<option value="week"><?php esc_html_e( '1 week', 'members' ); ?></option>
											<option value="month"><?php esc_html_e( '30 days', 'members' ); ?></option>
											<option value="never"><?php esc_html_e( 'Never', 'members' ); ?></option>
										</select>
									</label>
									<label class="members-fp-share-form__field">
										<span class="members-fp-share-form__label"><?php esc_html_e( 'Max uses', 'members' ); ?></span>
										<input type="number" min="0" step="1" class="small-text" value="0" data-members-fp-share-max-uses title="<?php esc_attr_e( '0 = unlimited', 'members' ); ?>" />
									</label>
									<button type="button" class="button button-primary" data-members-fp-share-generate><?php esc_html_e( 'Generate link', 'members' ); ?></button>
								</div>

								<p class="members-fp-share-empty<?php echo empty( $tokens ) ? '' : ' is-hidden'; ?>" data-members-fp-share-empty><?php esc_html_e( 'No share links yet.', 'members' ); ?></p>

								<ul class="members-fp-metabox__share-list" data-members-fp-share-list>
									<?php foreach ( $tokens as $token ) : ?>
										<?php
										$share_url = $this->container->get( ShareTokenService::class )->buildShareUrl( (int) $post->ID, $token->token );
										$summary   = $this->container->get( ShareTokenService::class )->formatTokenSummary( $token->expires_at, (int) $token->use_count, (int) $token->max_uses );
										?>
										<li class="members-fp-share-item" data-token-id="<?php echo esc_attr( (string) $token->id ); ?>">
											<div class="members-fp-share-item__head">
												<span class="description members-fp-share-item__meta"><?php echo esc_html( $summary ); ?></span>
												<div class="members-fp-share-item__actions">
													<button type="button" class="button button-small" data-members-fp-share-copy><?php esc_html_e( 'Copy', 'members' ); ?></button>
													<button type="button" class="button button-small members-fp-share-item__revoke" data-members-fp-share-revoke data-token-id="<?php echo esc_attr( (string) $token->id ); ?>"><?php esc_html_e( 'Revoke', 'members' ); ?></button>
												</div>
											</div>
											<input type="text" class="members-fp-copy-field__input members-fp-share-item__url" readonly value="<?php echo esc_attr( $share_url ); ?>" aria-label="<?php esc_attr_e( 'Share link URL', 'members' ); ?>" data-members-fp-select-on-click />
										</li>
									<?php endforeach; ?>
								</ul>
						</div>
					</section>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param int $post_id Attachment ID.
	 * @return void
	 */
	public function save( $post_id ) {
		if ( ! Capabilities::currentUserCanManage() ) {
			return;
		}

		if ( empty( $_POST['members_fp_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['members_fp_metabox_nonce'] ) ), 'members_fp_metabox' ) ) {
			return;
		}

		$post_id = (int) $post_id;

		if ( $post_id <= 0 || 'attachment' !== get_post_type( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$file      = get_attached_file( $post_id );
		$extension = $file ? strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) : '';
		$settings  = $this->container->get( Settings::class );

		if ( ! $settings->isExtensionProtected( 'file.' . $extension ) ) {
			return;
		}

		$repository = $this->container->get( FileRepositoryInterface::class );
		$protected  = ! empty( $_POST['members_fp_protected'] );
		$all_logged = ! empty( $_POST['members_fp_all_logged_in'] );
		$roles      = isset( $_POST['members_fp_roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['members_fp_roles'] ) ) : array();

		$roles = array_values(
			array_filter(
				$roles,
				static function ( $role ) {
					return '' !== $role && members_role_exists( $role );
				}
			)
		);

		$repository->setProtected( (int) $post_id, $protected );
		$repository->setAllowsAllLoggedIn( (int) $post_id, $all_logged );

		if ( $all_logged ) {
			$repository->setAllowedRoles( (int) $post_id, array() );
		} else {
			$repository->setAllowedRoles( (int) $post_id, $roles );
		}
		$repository->setDownloadLimit( (int) $post_id, isset( $_POST['members_fp_download_limit'] ) ? (int) $_POST['members_fp_download_limit'] : 0 );
	}
}
