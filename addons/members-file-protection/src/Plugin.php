<?php
/**
 * Primary add-on plugin class.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Capabilities;

/**
 * Wires services, hooks, and admin components.
 */
class Plugin {

	/**
	 * Add-on directory path.
	 *
	 * @var string
	 */
	private $dir;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * @param string $dir Add-on root directory.
	 */
	public function __construct( $dir ) {
		$this->dir       = trailingslashit( $dir );
		$this->container = new Container( $this->dir );
	}

	/**
	 * Returns the service container.
	 *
	 * @return Container
	 */
	public function container() {
		return $this->container;
	}

	/**
	 * Returns the add-on directory path.
	 *
	 * @param string $file Optional file append.
	 * @return string
	 */
	public function path( $file = '' ) {
		return $file ? $this->dir . ltrim( $file, '/' ) : $this->dir;
	}

	/**
	 * Returns the add-on directory URI.
	 *
	 * @param string $file Optional file append.
	 * @return string
	 */
	public function uri( $file = '' ) {
		$uri = plugin_dir_url( $this->path( 'addon.php' ) );

		return $file ? $uri . ltrim( $file, '/' ) : $uri;
	}

	/**
	 * Bootstraps hooks.
	 *
	 * @return void
	 */
	public function boot() {
		if ( ! $this->members_is_available() ) {
			add_action( 'admin_notices', array( $this, 'members_missing_notice' ) );
			return;
		}

		add_action( 'init', array( $this, 'register_shortcodes' ), 20 );
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'init', array( $this, 'maybe_install_tables' ), 5 );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'parse_request', array( $this, 'maybe_handle_gateway' ), 0 );
		add_action( 'init', array( $this, 'maybe_sync_server_config' ), 1 );
		add_action( 'admin_init', array( $this, 'defer_settings_form_sync' ), 99 );
		add_action( 'update_option_' . Services\Settings::OPTION_PROTECT_IMAGES, array( $this, 'sync_server_config' ) );
		add_action( 'update_option_' . Services\Settings::OPTION_PROTECT_VIDEO, array( $this, 'sync_server_config' ) );
		add_action( 'update_option_' . Services\Settings::OPTION_EXTENSIONS, array( $this, 'sync_server_config' ) );

		$this->container->get( Services\OffloadIntegration::class )->registerHooks();
		$this->container->get( Services\ContentPermissionsIntegration::class )->registerHooks();
		$this->container->get( Services\MaintenanceService::class )->registerHooks();
		Services\MaintenanceService::schedule();

		if ( is_admin() ) {
			new Admin\MetaboxController( $this->container );
			new Admin\SettingsPage( $this->container );
			new Admin\NoticesController( $this->container );
			new Admin\MediaLibraryController( $this->container );
			new Admin\BlockEditorController();
		}

		add_action( 'wp_ajax_members_fp_test_protection', array( $this, 'ajax_test_protection' ) );
		add_action( 'wp_ajax_members_fp_mark_htaccess_done', array( $this, 'ajax_mark_htaccess_done' ) );
		add_action( 'wp_ajax_members_fp_create_share_link', array( $this, 'ajax_create_share_link' ) );
		add_action( 'wp_ajax_members_fp_revoke_share_link', array( $this, 'ajax_revoke_share_link' ) );
		add_action( 'wp_ajax_members_fp_revoke_all_share_links', array( $this, 'ajax_revoke_all_share_links' ) );
		add_action( 'wp_ajax_members_fp_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
	}

	/**
	 * Creates custom database tables when needed.
	 *
	 * @return void
	 */
	public function maybe_install_tables() {
		Services\Installer::maybeInstall();
		$this->ensure_capabilities();
	}

	/**
	 * Ensures the manage capability exists for administrators.
	 *
	 * @return void
	 */
	private function ensure_capabilities() {
		$role = get_role( 'administrator' );

		if ( $role && ! $role->has_cap( Capabilities::CAP_MANAGE ) ) {
			$role->add_cap( Capabilities::CAP_MANAGE );
		}
	}

	/**
	 * Whether Members core functions are available.
	 *
	 * @return bool
	 */
	private function members_is_available() {
		return function_exists( 'members_user_has_role' );
	}

	/**
	 * Admin notice when Members is inactive.
	 *
	 * @return void
	 */
	public function members_missing_notice() {
		if ( ! Capabilities::currentUserCanManage() ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Members File Protection requires the Members plugin. Please activate Members to continue.', 'members' )
		);
	}

	/**
	 * Registers attachment meta for REST and storage.
	 *
	 * @return void
	 */
	public function register_meta() {
		$auth = static function () {
			return Capabilities::currentUserCanManage();
		};

		register_post_meta(
			'attachment',
			'_members_file_protected',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $auth,
				'sanitize_callback' => static function ( $value ) {
					return (bool) $value;
				},
				'default'           => false,
			)
		);

		register_post_meta(
			'attachment',
			'_members_file_all_logged_in',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $auth,
				'sanitize_callback' => static function ( $value ) {
					return (bool) $value;
				},
				'default'           => false,
			)
		);

		register_post_meta(
			'attachment',
			'_members_file_access_role',
			array(
				'type'              => 'string',
				'single'            => false,
				'show_in_rest'      => true,
				'auth_callback'     => $auth,
				'sanitize_callback' => static function ( $value ) {
					$sanitize_role = static function ( $role ) {
						if ( ! is_string( $role ) || '' === $role ) {
							return '';
						}

						return function_exists( 'members_sanitize_role' ) ? members_sanitize_role( $role ) : sanitize_key( $role );
					};

					if ( is_array( $value ) ) {
						return array_values(
							array_filter(
								array_map( $sanitize_role, $value )
							)
						);
					}

					return $sanitize_role( $value );
				},
			)
		);

		register_post_meta(
			'attachment',
			'_members_file_download_limit',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $auth,
				'sanitize_callback' => static function ( $value ) {
					return max( 0, (int) $value );
				},
				'default'           => 0,
			)
		);
	}

	/**
	 * Registers front-end shortcodes.
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( 'members_fp_download', array( $this, 'render_download_shortcode' ) );
	}

	/**
	 * Renders a download link for a protected attachment.
	 *
	 * Usage: [members_fp_download id="123"]Download file[/members_fp_download]
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Link label.
	 * @return string
	 */
	public function render_download_shortcode( $atts, $content = '' ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'class' => '',
			),
			$atts,
			'members_fp_download'
		);

		$attachment_id = (int) $atts['id'];

		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return '';
		}

		$url = members_fp_get_download_url( $attachment_id );

		if ( '' === $url ) {
			return '';
		}

		$label = '' !== trim( (string) $content ) ? trim( (string) $content ) : get_the_title( $attachment_id );
		$class = '' !== $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';

		return sprintf(
			'<a href="%1$s"%2$s download>%3$s</a>',
			esc_url( $url ),
			$class,
			esc_html( $label )
		);
	}

	/**
	 * Registers the gateway query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function register_query_var( $vars ) {
		$vars[] = 'members_fp_gateway';
		$vars[] = 'members_fp_token';
		$vars[] = 'members_fp_download';
		$vars[] = 'dl';
		return $vars;
	}

	/**
	 * Routes gateway requests to the gatekeeper.
	 *
	 * @param \WP $wp WordPress environment.
	 * @return void
	 */
	public function maybe_handle_gateway( $wp ) {
		if ( empty( $wp->query_vars['members_fp_gateway'] ) ) {
			return;
		}

		$path = wp_unslash( $wp->query_vars['members_fp_gateway'] );
		$this->container->get( Gatekeeper::class )->handle( $path );
		exit;
	}

	/**
	 * Writes server rewrite rules when needed.
	 *
	 * @return void
	 */
	public function maybe_sync_server_config() {
		$settings = $this->container->get( Services\Settings::class );
		$server   = $this->container->get( Contracts\ServerConfigInterface::class );
		$exts     = $settings->getExtensions();

		if ( empty( $exts ) ) {
			return;
		}

		if ( ! $server->matchesExtensions( $exts ) ) {
			$server->write( $exts );
		}
	}

	/**
	 * Regenerates rewrite rules after the full settings form saves.
	 *
	 * @return void
	 */
	public function defer_settings_form_sync() {
		if ( empty( $_POST['option_page'] ) || 'members_fp_settings' !== sanitize_key( wp_unslash( $_POST['option_page'] ) ) ) {
			return;
		}

		add_action( 'shutdown', array( $this, 'sync_server_config' ) );
	}

	/**
	 * Regenerates server rewrite rules from current settings.
	 *
	 * @return void
	 */
	public function sync_server_config() {
		$settings = $this->container->get( Services\Settings::class );
		$server   = $this->container->get( Contracts\ServerConfigInterface::class );
		$server->write( $settings->getExtensions() );
	}

	/**
	 * AJAX handler for the test protection button.
	 *
	 * @return void
	 */
	public function ajax_test_protection() {
		check_ajax_referer( 'members_fp_test', 'nonce' );

		if ( ! Capabilities::currentUserCanManage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'members' ) ) );
		}

		$server  = $this->container->get( Contracts\ServerConfigInterface::class );
		$active  = $server->isActive();
		$message = $active
			? __( 'Protection is active — direct file access is blocked.', 'members' )
			: __( 'Protection is not active — files may be publicly accessible. Check that rewrite rules are applied.', 'members' );

		update_option( 'members_fp_last_test_result', $active ? 'pass' : 'fail' );

		if ( $active ) {
			delete_option( 'members_fp_nginx_manual' );
		}

		wp_send_json_success(
			array(
				'active'  => $active,
				'message' => $message,
			)
		);
	}

	/**
	 * AJAX handler for marking manual htaccess as done.
	 *
	 * @return void
	 */
	public function ajax_mark_htaccess_done() {
		check_ajax_referer( 'members_fp_test', 'nonce' );

		if ( ! Capabilities::currentUserCanManage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'members' ) ) );
		}

		delete_option( 'members_fp_htaccess_manual' );

		$server = $this->container->get( Contracts\ServerConfigInterface::class );
		$active = $server->isActive();

		update_option( 'members_fp_last_test_result', $active ? 'pass' : 'fail' );

		wp_send_json_success(
			array(
				'active'  => $active,
				'message' => $active
					? __( 'Protection is active — direct file access is blocked.', 'members' )
					: __( 'Protection is not active — files may be publicly accessible. Check that rewrite rules are applied.', 'members' ),
			)
		);
	}

	/**
	 * Creates a private share link for an attachment.
	 *
	 * @return void
	 */
	public function ajax_create_share_link() {
		check_ajax_referer( 'members_fp_metabox', 'nonce' );

		if ( ! Capabilities::currentUserCanManage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'members' ) ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
		$expires       = isset( $_POST['expires'] ) ? sanitize_key( wp_unslash( $_POST['expires'] ) ) : 'day';
		$max_uses      = isset( $_POST['max_uses'] ) ? (int) $_POST['max_uses'] : 0;

		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'members' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'members' ) ) );
		}

		$map = array(
			'hour'  => HOUR_IN_SECONDS,
			'day'   => DAY_IN_SECONDS,
			'week'  => WEEK_IN_SECONDS,
			'month' => MONTH_IN_SECONDS,
			'never' => 0,
		);

		$expires_in = isset( $map[ $expires ] ) ? $map[ $expires ] : DAY_IN_SECONDS;
		$service    = $this->container->get( Services\ShareTokenService::class );
		$link = $service->create( $attachment_id, $expires_in, $max_uses );

		if ( null === $link ) {
			wp_send_json_error( array( 'message' => __( 'Could not create share link.', 'members' ) ) );
		}

		wp_send_json_success( $link );
	}

	/**
	 * Revokes a share link token.
	 *
	 * @return void
	 */
	public function ajax_revoke_share_link() {
		check_ajax_referer( 'members_fp_metabox', 'nonce' );

		if ( ! Capabilities::currentUserCanManage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'members' ) ) );
		}

		$token_id = isset( $_POST['token_id'] ) ? (int) $_POST['token_id'] : 0;

		if ( $token_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid token.', 'members' ) ) );
		}

		$service       = $this->container->get( Services\ShareTokenService::class );
		$attachment_id = $service->getTokenAttachmentId( $token_id );

		if ( null === $attachment_id || ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'members' ) ) );
		}

		$service->revoke( $token_id );
		wp_send_json_success();
	}

	/**
	 * Revokes all share link tokens for an attachment.
	 *
	 * @return void
	 */
	public function ajax_revoke_all_share_links() {
		check_ajax_referer( 'members_fp_metabox', 'nonce' );

		if ( ! Capabilities::currentUserCanManage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'members' ) ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;

		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'members' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'members' ) ) );
		}

		$service = $this->container->get( Services\ShareTokenService::class );
		$deleted   = $service->revokeAllForAttachment( $attachment_id );

		wp_send_json_success(
			array(
				'deleted' => $deleted,
			)
		);
	}

	/**
	 * Persists dismissal of a contextual admin notice.
	 *
	 * @return void
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( 'members_fp_dismiss', 'nonce' );

		if ( ! Capabilities::currentUserCanManage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'members' ) ) );
		}

		$key = isset( $_POST['notice'] ) ? sanitize_key( wp_unslash( $_POST['notice'] ) ) : '';

		if ( ! Admin\NoticesController::dismiss( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid notice.', 'members' ) ) );
		}

		wp_send_json_success();
	}
}
