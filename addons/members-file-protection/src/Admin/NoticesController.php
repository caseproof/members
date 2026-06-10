<?php
/**
 * Admin notices for setup state.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Admin;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Capabilities;
use Members\FileProtection\Container;
use Members\FileProtection\Contracts\ServerConfigInterface;
use Members\FileProtection\Services\NginxServerConfig;
use Members\FileProtection\Services\Settings;

/**
 * Displays contextual admin notices.
 */
class NoticesController {

	/** @var Container */
	private $container;

	/**
	 * @param Container $container Service container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * @return void
	 */
	public function enqueue() {
		if ( ! Capabilities::currentUserCanManage() ) {
			return;
		}

		wp_enqueue_script(
			'members-file-protection-notices',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/notices.js',
			array(),
			'1.0.0',
			true
		);

		wp_localize_script(
			'members-file-protection-notices',
			'membersFileProtectionNotices',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'members_fp_dismiss' ),
			)
		);
	}

	/**
	 * Persists a dismissed notice for the current user.
	 *
	 * @param string $key Notice key.
	 * @return bool
	 */
	public static function dismiss( string $key ): bool {
		$allowed = array( 'multisite', 'offload', 'test_fail', 'htaccess_manual' );

		if ( ! in_array( $key, $allowed, true ) ) {
			return false;
		}

		$user_id   = get_current_user_id();
		$dismissed = get_user_meta( $user_id, 'members_fp_dismissed_notices', true );

		if ( ! is_array( $dismissed ) ) {
			$dismissed = array();
		}

		if ( in_array( $key, $dismissed, true ) ) {
			return true;
		}

		$dismissed[] = $key;

		return (bool) update_user_meta( $user_id, 'members_fp_dismissed_notices', $dismissed );
	}

	/**
	 * @return void
	 */
	public function render_notices() {
		if ( ! Capabilities::currentUserCanManage() ) {
			return;
		}

		if ( is_multisite() && ! $this->is_dismissed( 'multisite' ) ) {
			printf(
				'<div class="notice notice-info is-dismissible" data-members-fp-notice="multisite"><p>%s</p></div>',
				esc_html__( 'File Protection runs per site in multisite. Configure rewrite rules and settings on each site where protection is needed.', 'members' )
			);
		}

		if ( ! empty( $_GET['members_fp_bulk'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of files updated */
						_n( '%d file updated.', '%d files updated.', (int) $_GET['members_fp_bulk'], 'members' ),
						(int) $_GET['members_fp_bulk']
					)
				)
			);
		}

		if ( ! function_exists( 'members_user_has_role' ) ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Members File Protection requires the Members plugin. Please activate Members to continue.', 'members' )
			);
			return;
		}

		$settings = $this->container->get( Settings::class );

		if ( ! $settings->hasPrettyPermalinks() ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				sprintf(
					/* translators: %s: settings URL */
					esc_html__( 'File Protection requires pretty permalinks. Go to %s and select any structure other than Plain.', 'members' ),
					'<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . esc_html__( 'Settings → Permalinks', 'members' ) . '</a>'
				)
			);
		}

		if ( $this->is_offload_active() && ! $this->is_dismissed( 'offload' ) ) {
			printf(
				'<div class="notice notice-info is-dismissible" data-members-fp-notice="offload"><p>%s</p></div>',
				esc_html__( 'Offloaded media detected. Protected files are delivered via time-limited signed URLs after authorization.', 'members' )
			);
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'members_page_members-file-protection' !== $screen->id ) {
			return;
		}

		$server = $this->container->get( ServerConfigInterface::class );

		if ( $server instanceof NginxServerConfig && get_option( 'members_fp_nginx_manual', false ) && ! $server->isActive() ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'Nginx manual configuration has not been verified. Add the location block and run Test.', 'members' )
			);
		}

		if ( 'fail' === get_option( 'members_fp_last_test_result', '' ) && ! $this->is_dismissed( 'test_fail' ) ) {
			printf(
				'<div class="notice notice-error is-dismissible" data-members-fp-notice="test_fail"><p>%s</p></div>',
				esc_html__( 'The last protection test failed. Files may be publicly accessible until rewrite rules are configured.', 'members' )
			);
		}

		if ( (bool) get_option( 'members_fp_htaccess_manual', false ) && ! $this->is_dismissed( 'htaccess_manual' ) ) {
			printf(
				'<div class="notice notice-warning is-dismissible" data-members-fp-notice="htaccess_manual"><p>%s</p></div>',
				esc_html__( 'Could not write to .htaccess automatically. Add the rules shown on this page.', 'members' )
			);
		}
	}

	/**
	 * @return bool
	 */
	private function is_offload_active(): bool {
		return defined( 'AS3CF_VERSION' ) || class_exists( 'Amazon_S3_And_CloudFront', false );
	}

	/**
	 * @param string $key Notice key.
	 * @return bool
	 */
	private function is_dismissed( string $key ): bool {
		$dismissed = get_user_meta( get_current_user_id(), 'members_fp_dismissed_notices', true );
		return is_array( $dismissed ) && in_array( $key, $dismissed, true );
	}
}
