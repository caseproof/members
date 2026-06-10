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
			'1.0.6'
		);

		wp_enqueue_script(
			'members-file-protection-metabox',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/metabox.js',
			array(),
			'1.0.0',
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
					'error'    => __( 'Could not create share link.', 'members' ),
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
			'side',
			'default'
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
		?>
		<div class="members-fp-metabox" data-members-fp-metabox>
			<?php if ( ! $enabled ) : ?>
				<p class="members-fp-metabox__disabled">
					<?php
					printf(
						/* translators: 1: file extension, 2: extension */
						esc_html__( '%1$s protection is disabled. Add %2$s to the protected extensions list to enable protection for this file.', 'members' ),
						esc_html( strtoupper( $extension ) ),
						esc_html( $extension )
					);
					?>
				</p>
				<p><a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Manage extensions', 'members' ); ?></a></p>
			<?php else : ?>
				<p>
					<label class="members-fp-switch">
						<input type="checkbox" name="members_fp_protected" value="1" role="switch" aria-checked="<?php echo $protected ? 'true' : 'false'; ?>" <?php checked( $protected ); ?> data-members-fp-toggle />
						<span><?php esc_html_e( 'Enable file protection', 'members' ); ?></span>
					</label>
				</p>
				<p class="description members-fp-metabox__help"><?php esc_html_e( 'When disabled, this file is publicly accessible by direct URL.', 'members' ); ?></p>

				<div class="members-fp-metabox__roles<?php echo $protected ? '' : ' is-hidden'; ?>" data-members-fp-roles>
					<p><strong><?php esc_html_e( 'Who can access this file?', 'members' ); ?></strong></p>

					<label>
						<input type="checkbox" name="members_fp_all_logged_in" value="1" <?php checked( $all_logged ); ?> data-members-fp-all-logged-in />
						<?php esc_html_e( 'Allow all logged-in users', 'members' ); ?>
					</label>

					<fieldset class="members-fp-metabox__role-list<?php echo $all_logged ? ' is-disabled' : ''; ?>" data-members-fp-role-fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Allowed roles', 'members' ); ?></legend>
						<?php if ( empty( $_wp_roles ) ) : ?>
							<p class="description"><?php esc_html_e( '(No custom roles found.) All logged-in users will have access if the checkbox above is checked.', 'members' ); ?></p>
						<?php else : ?>
							<ul>
								<?php foreach ( $_wp_roles as $role => $name ) : ?>
									<li>
										<label>
											<input
												type="checkbox"
												name="members_fp_roles[]"
												value="<?php echo esc_attr( $role ); ?>"
												<?php checked( in_array( $role, $roles, true ) ); ?>
												<?php disabled( $all_logged ); ?>
												data-members-fp-role
											/>
											<?php echo esc_html( translate_user_role( $name ) ); ?>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</fieldset>

					<div class="notice notice-warning inline members-fp-metabox__warning is-hidden" role="alert" data-members-fp-warning>
						<p><?php esc_html_e( 'No roles selected. All users including logged-in members will be blocked.', 'members' ); ?></p>
					</div>

					<p class="description"><?php esc_html_e( 'Users with manage_options always have access regardless of selection.', 'members' ); ?></p>

					<p>
						<label for="members_fp_download_limit"><strong><?php esc_html_e( 'Download limit per user', 'members' ); ?></strong></label><br />
						<input type="number" min="0" step="1" class="small-text" id="members_fp_download_limit" name="members_fp_download_limit" value="<?php echo esc_attr( (string) $dl_limit ); ?>" />
						<span class="description"><?php esc_html_e( '0 = unlimited. Share links bypass this limit.', 'members' ); ?></span>
					</p>

					<div class="members-fp-metabox__share" data-members-fp-share>
						<p><strong><?php esc_html_e( 'Private share links', 'members' ); ?></strong></p>
						<p class="description"><?php esc_html_e( 'Generate expiring links that grant temporary access without login.', 'members' ); ?></p>
						<p class="members-fp-metabox__share-options">
							<select data-members-fp-share-expires>
								<option value="hour"><?php esc_html_e( '1 hour', 'members' ); ?></option>
								<option value="day" selected><?php esc_html_e( '1 day', 'members' ); ?></option>
								<option value="week"><?php esc_html_e( '1 week', 'members' ); ?></option>
								<option value="month"><?php esc_html_e( '30 days', 'members' ); ?></option>
								<option value="never"><?php esc_html_e( 'Never expires', 'members' ); ?></option>
							</select>
							<input type="number" min="0" step="1" class="small-text" placeholder="<?php esc_attr_e( 'Max uses', 'members' ); ?>" data-members-fp-share-max-uses title="<?php esc_attr_e( 'Max uses (0 = unlimited)', 'members' ); ?>" />
						</p>
						<p class="members-fp-metabox__share-actions">
							<button type="button" class="button" data-members-fp-share-generate><?php esc_html_e( 'Generate link', 'members' ); ?></button>
						</p>
						<ul class="members-fp-metabox__share-list" data-members-fp-share-list>
							<?php foreach ( $tokens as $token ) : ?>
								<?php
								$share_url = $this->container->get( ShareTokenService::class )->buildShareUrl( (int) $post->ID, $token->token );
								$expires   = $token->expires_at ? get_date_from_gmt( $token->expires_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : __( 'Never', 'members' );
								?>
								<li data-token-id="<?php echo esc_attr( (string) $token->id ); ?>">
									<code class="members-fp-share-url"><?php echo esc_html( $share_url ); ?></code>
									<span class="description"><?php echo esc_html( sprintf( __( 'Expires: %s · Uses: %d/%s', 'members' ), $expires, (int) $token->use_count, (int) $token->max_uses > 0 ? (string) $token->max_uses : '∞' ) ); ?></span>
									<button type="button" class="button-link" data-members-fp-share-revoke data-token-id="<?php echo esc_attr( (string) $token->id ); ?>"><?php esc_html_e( 'Revoke', 'members' ); ?></button>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
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
		$repository->setAllowedRoles( (int) $post_id, $roles );
		$repository->setDownloadLimit( (int) $post_id, isset( $_POST['members_fp_download_limit'] ) ? (int) $_POST['members_fp_download_limit'] : 0 );
	}
}
