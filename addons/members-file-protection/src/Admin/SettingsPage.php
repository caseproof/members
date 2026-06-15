<?php
/**
 * File Protection settings page.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Admin;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Capabilities;
use Members\FileProtection\Container;
use Members\FileProtection\Contracts\ServerConfigInterface;
use Members\FileProtection\Services\ContentPermissionsIntegration;
use Members\FileProtection\Services\NginxServerConfig;
use Members\FileProtection\Services\Settings;

/**
 * Members → File Protection settings screen.
 */
class SettingsPage {

	/** @var Container */
	private $container;

	/**
	 * @param Container $container Service container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
		add_filter( 'members_admin_page_slugs', array( $this, 'register_admin_page_slug' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Registers this screen with the Members admin chrome.
	 *
	 * @param string[] $slugs Page slugs.
	 * @return string[]
	 */
	public function register_admin_page_slug( array $slugs ) {
		$slugs[] = 'members-file-protection';

		return $slugs;
	}

	/**
	 * @return void
	 */
	public function register_menu() {
		$hook = add_submenu_page(
			'members',
			__( 'File Protection', 'members' ),
			__( 'File Protection', 'members' ),
			Capabilities::manage(),
			'members-file-protection',
			array( $this, 'render_page' )
		);

		$this->register_members_admin_screen( $hook );
	}

	/**
	 * Adds the screen ID to Members core admin pages.
	 *
	 * @param string|false $hook Admin page hook suffix.
	 * @return void
	 */
	private function register_members_admin_screen( $hook ) {
		if ( ! $hook || ! class_exists( '\Members\Admin\Settings_Page' ) ) {
			return;
		}

		$settings_page = \Members\Admin\Settings_Page::get_instance();

		if ( ! in_array( $hook, $settings_page->admin_pages, true ) ) {
			$settings_page->admin_pages[] = $hook;
		}
	}

	/**
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'members_fp_settings',
			Settings::OPTION_EXTENSIONS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_extensions' ),
				'default'           => Settings::DEFAULT_EXTENSIONS,
			)
		);

		register_setting(
			'members_fp_settings',
			Settings::OPTION_PROTECT_IMAGES,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => static function ( $value ) {
					return ! empty( $value );
				},
				'default'           => false,
			)
		);

		register_setting(
			'members_fp_settings',
			Settings::OPTION_PROTECT_VIDEO,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => static function ( $value ) {
					return ! empty( $value );
				},
				'default'           => false,
			)
		);

		register_setting(
			'members_fp_settings',
			Settings::OPTION_SIGNED_URL_TTL,
			array(
				'type'              => 'integer',
				'sanitize_callback' => static function ( $value ) {
					return max( 60, (int) $value );
				},
				'default'           => 3600,
			)
		);

		register_setting(
			'members_fp_settings',
			ContentPermissionsIntegration::OPTION_INHERIT,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => static function ( $value ) {
					return ! empty( $value );
				},
				'default'           => false,
			)
		);

		register_setting(
			'members_fp_settings',
			Settings::OPTION_UNAUTHORIZED,
			array(
				'type'              => 'string',
				'sanitize_callback' => static function ( $value ) {
					return 'redirect' === $value ? 'redirect' : '404';
				},
				'default'           => '404',
			)
		);

		register_setting(
			'members_fp_settings',
			Settings::OPTION_REDIRECT_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => static function ( $value ) {
					return is_string( $value ) ? sanitize_text_field( $value ) : '{login_url}';
				},
				'default'           => '{login_url}',
			)
		);
	}

	/**
	 * @param mixed $value Raw extensions value.
	 * @return string
	 */
	public function sanitize_extensions( $value ) {
		$settings = $this->container->get( Settings::class );
		$parsed   = $settings->parseExtensions( is_string( $value ) ? $value : '' );

		if ( empty( $parsed ) ) {
			add_settings_error(
				Settings::OPTION_EXTENSIONS,
				'members_fp_extensions_empty',
				__( 'At least one file type is required.', 'members' )
			);

			return get_option( Settings::OPTION_EXTENSIONS, Settings::DEFAULT_EXTENSIONS );
		}

		$normalized = implode( ',', $parsed );

		return $normalized;
	}

	/**
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( 'members_page_members-file-protection' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'members-admin' );

		wp_enqueue_style(
			'members-file-protection-admin',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/admin.css',
			array( 'members-admin' ),
			'1.0.7'
		);

		wp_enqueue_script(
			'members-file-protection-settings',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/settings.js',
			array(),
			'1.0.4',
			true
		);

		wp_localize_script(
			'members-file-protection-settings',
			'membersFileProtection',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'members_fp_test' ),
				'i18n'    => array(
					'testing' => __( 'Testing…', 'members' ),
					'test'    => __( 'Test', 'members' ),
					'copied'  => __( 'Copied!', 'members' ),
					'copy'    => __( 'Copy to clipboard', 'members' ),
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public function render_page() {
		if ( ! Capabilities::currentUserCanManage() ) {
			return;
		}

		$settings = $this->container->get( Settings::class );
		$server   = $this->container->get( ServerConfigInterface::class );
		$exts     = get_option( Settings::OPTION_EXTENSIONS, Settings::DEFAULT_EXTENSIONS );
		$behavior = $settings->getUnauthorizedBehavior();
		$redirect = $settings->getRedirectUrl();
		$block    = $server->getConfigBlock( $settings->getExtensions() );
		$active   = $server->isActive();
		$label    = $server->getServerLabel();
		$status   = $active ? '✓' : '⚠';
		$manual   = (bool) get_option( 'members_fp_htaccess_manual', false );
		$is_nginx = $server instanceof NginxServerConfig;
		?>
		<div class="wrap members-fp-settings">
			<h1><?php esc_html_e( 'File Protection', 'members' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'members_fp_settings' ); ?>

				<h2><?php esc_html_e( 'Protected file types', 'members' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Which file extensions should be protected when marked in the Media Library.', 'members' ); ?></p>
				<p>
					<input type="text" class="large-text code" name="<?php echo esc_attr( Settings::OPTION_EXTENSIONS ); ?>" value="<?php echo esc_attr( is_array( $exts ) ? implode( ',', $exts ) : $exts ); ?>" />
				</p>
				<p>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_PROTECT_IMAGES ); ?>" value="1" <?php checked( $settings->protectImages() ); ?> data-members-fp-protect-images />
						<?php esc_html_e( 'Also protect image files (jpg, jpeg, png, gif, webp)', 'members' ); ?>
					</label>
				</p>
				<p class="description members-fp-settings__image-warning<?php echo $settings->protectImages() ? '' : ' is-hidden'; ?>" data-members-fp-image-warning>
					<?php esc_html_e( 'Image protection includes all WordPress-generated thumbnail and resized variants.', 'members' ); ?>
				</p>
				<p>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_PROTECT_VIDEO ); ?>" value="1" <?php checked( $settings->protectVideo() ); ?> data-members-fp-protect-video />
						<?php esc_html_e( 'Also protect video files (mp4, m4v, webm, mov)', 'members' ); ?>
					</label>
				</p>
				<p class="description members-fp-settings__video-note<?php echo $settings->protectVideo() ? '' : ' is-hidden'; ?>" data-members-fp-video-note>
					<?php esc_html_e( 'Video protection uses role-based access control through the gatekeeper. This is not DRM — for true streaming protection use a dedicated video host.', 'members' ); ?>
				</p>

				<?php if ( function_exists( 'members_content_permissions_enabled' ) && members_content_permissions_enabled() ) : ?>
					<p>
						<label>
							<input type="hidden" name="<?php echo esc_attr( ContentPermissionsIntegration::OPTION_INHERIT ); ?>" value="0" />
							<input type="checkbox" name="<?php echo esc_attr( ContentPermissionsIntegration::OPTION_INHERIT ); ?>" value="1" <?php checked( (bool) get_option( ContentPermissionsIntegration::OPTION_INHERIT, false ) ); ?> />
							<?php esc_html_e( 'Inherit access rules from Content Permissions on posts that use this file', 'members' ); ?>
						</label>
					</p>
					<p class="description"><?php esc_html_e( 'When a file is not individually protected, access follows the permissions of posts that embed it.', 'members' ); ?></p>
				<?php endif; ?>

				<hr />

				<h2><?php esc_html_e( 'Object storage', 'members' ); ?></h2>
				<p class="description"><?php esc_html_e( 'When WP Offload Media is active, authorized users receive time-limited signed URLs for protected offloaded files.', 'members' ); ?></p>
				<p>
					<label for="members_fp_signed_url_ttl"><?php esc_html_e( 'Signed URL lifetime (seconds)', 'members' ); ?></label><br />
					<input type="number" min="60" step="60" class="small-text" id="members_fp_signed_url_ttl" name="<?php echo esc_attr( Settings::OPTION_SIGNED_URL_TTL ); ?>" value="<?php echo esc_attr( (string) $settings->getSignedUrlTtl() ); ?>" />
				</p>

				<hr />

				<h2><?php esc_html_e( 'Unauthorized access', 'members' ); ?></h2>
				<p class="description"><?php esc_html_e( 'What should happen when a user tries to access a protected file without permission.', 'members' ); ?></p>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Unauthorized access behavior', 'members' ); ?></legend>
					<p>
						<label>
							<input type="radio" name="<?php echo esc_attr( Settings::OPTION_UNAUTHORIZED ); ?>" value="404" <?php checked( '404', $behavior ); ?> data-members-fp-behavior />
							<?php esc_html_e( 'Return 404 Not Found (recommended)', 'members' ); ?>
						</label>
					</p>
					<p>
						<label>
							<input type="radio" name="<?php echo esc_attr( Settings::OPTION_UNAUTHORIZED ); ?>" value="redirect" <?php checked( 'redirect', $behavior ); ?> data-members-fp-behavior />
							<?php esc_html_e( 'Redirect to URL', 'members' ); ?>
						</label>
					</p>
					<div class="members-fp-settings__redirect<?php echo 'redirect' === $behavior ? '' : ' is-hidden'; ?>" data-members-fp-redirect-field>
						<input type="text" class="large-text code" name="<?php echo esc_attr( Settings::OPTION_REDIRECT_URL ); ?>" value="<?php echo esc_attr( $redirect ); ?>" placeholder="https://example.com/login" />
						<p class="description"><?php esc_html_e( 'Use {login_url} to redirect to the WordPress login page.', 'members' ); ?></p>
					</div>
				</fieldset>

				<?php submit_button( __( 'Save changes', 'members' ) ); ?>
			</form>

			<hr />

			<h2>
				<?php esc_html_e( 'Server configuration', 'members' ); ?>
				<span class="members-fp-settings__server-label"><?php echo esc_html( $label . ' ' . $status ); ?></span>
			</h2>

			<?php if ( $is_nginx ) : ?>
				<p><?php esc_html_e( 'Nginx requires manual server configuration. Add the following location block to your server config, then click Test.', 'members' ); ?></p>
				<p class="description"><?php esc_html_e( 'Update this block when you change protected extensions. CDN and reverse-proxy caches should bypass storing protected file responses.', 'members' ); ?></p>
				<pre class="members-fp-code" role="region" aria-label="<?php esc_attr_e( 'Server configuration code', 'members' ); ?>"><code><?php echo esc_html( $block ); ?></code></pre>
				<p>
					<button type="button" class="button" data-members-fp-copy aria-label="<?php esc_attr_e( 'Copy server configuration to clipboard', 'members' ); ?>"><?php esc_html_e( 'Copy to clipboard', 'members' ); ?></button>
					<button type="button" class="button" data-members-fp-test><?php esc_html_e( 'Test', 'members' ); ?></button>
				</p>
			<?php elseif ( $manual ) : ?>
				<p><?php esc_html_e( 'Could not write to .htaccess automatically. Add the following rules manually:', 'members' ); ?></p>
				<pre class="members-fp-code" role="region" aria-label="<?php esc_attr_e( 'Server configuration code', 'members' ); ?>"><code><?php echo esc_html( $block ); ?></code></pre>
				<p>
					<button type="button" class="button" data-members-fp-copy><?php esc_html_e( 'Copy to clipboard', 'members' ); ?></button>
					<button type="button" class="button" data-members-fp-mark-done><?php esc_html_e( 'Mark as done', 'members' ); ?></button>
					<button type="button" class="button" data-members-fp-test><?php esc_html_e( 'Test', 'members' ); ?></button>
				</p>
			<?php else : ?>
				<p>
					<?php echo $active ? esc_html__( 'Rewrite rules are active.', 'members' ) : esc_html__( 'Rewrite rules are not active.', 'members' ); ?>
					<button type="button" class="button" data-members-fp-test style="margin-left:8px"><?php esc_html_e( 'Test', 'members' ); ?></button>
				</p>
				<?php if ( $active ) : ?>
					<p class="description"><?php esc_html_e( '.htaccess was updated automatically.', 'members' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<div class="members-fp-settings__test-result is-hidden" aria-live="polite" data-members-fp-test-result></div>
		</div>
		<?php
	}
}
