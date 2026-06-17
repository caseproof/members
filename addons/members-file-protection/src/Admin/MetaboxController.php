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
			'1.1.0'
		);

		wp_enqueue_script(
			'members-file-protection-metabox',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/metabox.js',
			array(),
			'1.1.0',
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
				<p class="description members-fp-metabox__intro"><?php esc_html_e( 'Protected files require permission to access. Direct URLs are routed through the Members gateway.', 'members' ); ?></p>

				<div class="members-fp-metabox__settings<?php echo $protected ? '' : ' is-hidden'; ?>" data-members-fp-roles>
					<section class="members-fp-panel">
						<h3 class="members-fp-panel__title"><?php esc_html_e( 'Who can access', 'members' ); ?></h3>

						<label class="members-fp-option">
							<input type="checkbox" name="members_fp_all_logged_in" value="1" <?php checked( $all_logged ); ?> data-members-fp-all-logged-in />
							<span class="members-fp-option__label"><?php esc_html_e( 'All logged-in users', 'members' ); ?></span>
						</label>

						<fieldset class="members-fp-metabox__role-list<?php echo $all_logged ? ' is-disabled' : ''; ?>" data-members-fp-role-fieldset>
							<legend class="members-fp-panel__legend"><?php esc_html_e( 'Or limit to these roles', 'members' ); ?></legend>
							<?php if ( empty( $_wp_roles ) ) : ?>
								<p class="description"><?php esc_html_e( 'No custom roles found. Enable “All logged-in users” above.', 'members' ); ?></p>
							<?php else : ?>
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
							<?php endif; ?>
						</fieldset>

						<div class="notice notice-warning inline members-fp-metabox__warning is-hidden" role="alert" data-members-fp-warning>
							<p><?php esc_html_e( 'Select at least one role, or enable “All logged-in users”.', 'members' ); ?></p>
						</div>

						<p class="description members-fp-panel__footnote"><?php esc_html_e( 'Site administrators always have access.', 'members' ); ?></p>
					</section>

					<section class="members-fp-panel">
						<h3 class="members-fp-panel__title"><?php esc_html_e( 'Download limit', 'members' ); ?></h3>
						<div class="members-fp-field-row">
							<label for="members_fp_download_limit" class="screen-reader-text"><?php esc_html_e( 'Download limit per user', 'members' ); ?></label>
							<input type="number" min="0" step="1" class="small-text" id="members_fp_download_limit" name="members_fp_download_limit" value="<?php echo esc_attr( (string) $dl_limit ); ?>" />
							<span class="description"><?php esc_html_e( 'downloads per user (0 = unlimited)', 'members' ); ?></span>
						</div>
						<p class="description members-fp-panel__footnote"><?php esc_html_e( 'Viewing a file inline does not count. Share links and administrators bypass this limit.', 'members' ); ?></p>
					</section>

					<?php if ( '' !== $download_url ) : ?>
						<section class="members-fp-panel" data-members-fp-download-section>
							<h3 class="members-fp-panel__title"><?php esc_html_e( 'Download link', 'members' ); ?></h3>
							<p class="description"><?php esc_html_e( 'Use this URL when you want a link that counts toward the download limit.', 'members' ); ?></p>
							<div class="members-fp-copy-field">
								<input type="text" class="members-fp-copy-field__input" readonly value="<?php echo esc_attr( $download_url ); ?>" aria-label="<?php esc_attr_e( 'Download URL', 'members' ); ?>" data-members-fp-select-on-click />
								<button type="button" class="button" data-members-fp-share-copy><?php esc_html_e( 'Copy', 'members' ); ?></button>
							</div>
							<p class="description members-fp-shortcode-hint">
								<?php esc_html_e( 'Shortcode:', 'members' ); ?>
								<code class="members-fp-shortcode"><?php echo esc_html( $shortcode ); ?></code>
								<button type="button" class="button-link" data-members-fp-shortcode-copy><?php esc_html_e( 'Copy', 'members' ); ?></button>
							</p>
						</section>
					<?php endif; ?>

					<section class="members-fp-panel members-fp-panel--share" data-members-fp-share>
						<h3 class="members-fp-panel__title"><?php esc_html_e( 'Private share links', 'members' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Temporary links that work without logging in.', 'members' ); ?></p>

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

						<p class="members-fp-metabox__share-actions">
							<button type="button" class="button-link<?php echo empty( $tokens ) ? ' is-hidden' : ''; ?>" data-members-fp-share-revoke-all><?php esc_html_e( 'Revoke all links', 'members' ); ?></button>
						</p>

						<p class="members-fp-share-empty<?php echo empty( $tokens ) ? '' : ' is-hidden'; ?>" data-members-fp-share-empty><?php esc_html_e( 'No share links yet.', 'members' ); ?></p>

						<ul class="members-fp-metabox__share-list" data-members-fp-share-list>
							<?php foreach ( $tokens as $token ) : ?>
								<?php
								$share_url = $this->container->get( ShareTokenService::class )->buildShareUrl( (int) $post->ID, $token->token );
								$summary   = $this->container->get( ShareTokenService::class )->formatTokenSummary( $token->expires_at, (int) $token->use_count, (int) $token->max_uses );
								?>
								<li class="members-fp-share-item" data-token-id="<?php echo esc_attr( (string) $token->id ); ?>">
									<input type="text" class="members-fp-copy-field__input members-fp-share-item__url" readonly value="<?php echo esc_attr( $share_url ); ?>" aria-label="<?php esc_attr_e( 'Share link URL', 'members' ); ?>" data-members-fp-select-on-click />
									<span class="description members-fp-share-item__meta"><?php echo esc_html( $summary ); ?></span>
									<div class="members-fp-share-item__actions">
										<button type="button" class="button button-small" data-members-fp-share-copy><?php esc_html_e( 'Copy', 'members' ); ?></button>
										<button type="button" class="button button-small" data-members-fp-share-revoke data-token-id="<?php echo esc_attr( (string) $token->id ); ?>"><?php esc_html_e( 'Revoke', 'members' ); ?></button>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
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
