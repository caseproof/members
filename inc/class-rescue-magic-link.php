<?php
/**
 * Administrator Rescue (Magic Link) – secure self-service restore for locked-out admins.
 *
 * @package    Members
 * @subpackage Includes
 * @since      3.2.20
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the secure 'Magic Link' generation and execution for Administrator Rescue.
 *
 * Security measures:
 * - CSRF: Nonce on request form, verified on submit.
 * - Rate limit: 3 attempts per 15 minutes per IP (transient).
 * - Eligibility: Only built-in role `administrator`, or Super Admin on multisite; cloned/custom roles excluded.
 * - Generic responses: Same message for invalid email, no user, not eligible, or rate limited (no enumeration).
 * - Token: HMAC-SHA256 with wp_salt('auth'), 15-min window; valid for current window only.
 * - Timing-safe token check: hash_equals() to prevent timing attacks.
 * - No secret in URL: Only uid and HMAC token in magic link.
 * - Re-verify eligibility before repair when processing the link.
 */
class Members_Rescue_Magic_Link {

	const NONCE_ACTION                 = 'members_rescue_request';
	const RATE_LIMIT_TRANSIENT_PREFIX  = 'members_rescue_ratelimit_';
	const RATE_LIMIT_MAX_ATTEMPTS      = 3;
	const RATE_LIMIT_WINDOW_SECONDS    = 900; // 15 minutes

	public function __construct() {
		add_action( 'login_form_members_rescue', array( $this, 'render_request_form' ) );
		add_action( 'login_form_members_rescue_process', array( $this, 'process_request_form' ) );
		add_action( 'init', array( $this, 'process_rescue_link' ) );
		add_filter( 'login_message', array( $this, 'login_message_rescue_feedback' ) );
	}

	/**
	 * Shows rescue success/invalid message on the default login screen when redirected from magic link.
	 *
	 * @param string $message Existing login message.
	 * @return string
	 */
	public function login_message_rescue_feedback( $message ) {
		if ( ! isset( $_GET['members_rescue'] ) ) {
			return $message;
		}
		$value = sanitize_text_field( wp_unslash( $_GET['members_rescue'] ) );
		if ( 'success' === $value ) {
			return '<p class="message">' . __( 'Your Administrator access has been restored. You can log in below.', 'members' ) . '</p>';
		}
		if ( 'invalid' === $value ) {
			return '<p class="message" id="login_error">' . __( 'That rescue link is invalid or has expired. Please request a new one.', 'members' ) . '</p>';
		}
		return $message;
	}

	/**
	 * Renders the "Administrator Rescue" form on wp-login.php.
	 */
	public function render_request_form() {
		if ( is_user_logged_in() ) {
			wp_safe_redirect( admin_url() );
			exit;
		}

		$message   = '';
		$check_email = isset( $_GET['check_email'] ) ? sanitize_text_field( wp_unslash( $_GET['check_email'] ) ) : '';
		if ( '' !== $check_email ) {
			$message = '<p class="message">' . __( 'If an administrator account exists for that email, we sent a rescue link. Please check your inbox.', 'members' ) . '</p>';
		}

		if ( empty( $message ) ) {
			$message = '<p class="message">' . __( 'Enter your email address to receive a secure link that restores your Administrator role.', 'members' ) . '</p>';
		}

		login_header(
			__( 'Administrator Rescue', 'members' ),
			$message
		);
		?>
		<form name="members_rescue_form" action="<?php echo esc_url( site_url( 'wp-login.php?action=members_rescue_process' ) ); ?>" method="post">
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<p>
				<label for="user_email"><?php esc_html_e( 'Email Address', 'members' ); ?><br />
				<input type="email" name="user_email" id="user_email" class="input" value="" size="20" required /></label>
			</p>
			<p class="submit">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Send Rescue Link', 'members' ); ?>" />
			</p>
		</form>
		<?php
		login_footer();
		exit;
	}

	/**
	 * Validates nonce, rate limit, email; checks eligibility; sends the Magic Link.
	 */
	public function process_request_form() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
			wp_safe_redirect( add_query_arg( 'action', 'members_rescue', wp_login_url() ) );
			exit;
		}

		$ip_key   = self::RATE_LIMIT_TRANSIENT_PREFIX . $this->get_client_ip_hash();
		$attempts = (int) get_transient( $ip_key ) + 1;
		set_transient( $ip_key, $attempts, self::RATE_LIMIT_WINDOW_SECONDS );
		if ( $attempts > self::RATE_LIMIT_MAX_ATTEMPTS ) {
			// Limit exceeded: same generic message, no email. Transient expires in 15 minutes.
			$this->redirect_with_generic_message();
			return;
		}

		$email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			$this->redirect_with_generic_message();
			return;
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$this->redirect_with_generic_message();
			return;
		}

		if ( ! $this->user_can_be_rescued( $user ) ) {
			$this->redirect_with_generic_message();
			return;
		}

		$token = $this->generate_token( $user->ID );
		$link  = add_query_arg(
			array(
				'members_action' => 'rescue_confirm',
				'uid'            => (int) $user->ID,
				'token'          => $token,
			),
			site_url( '/' )
		);

		wp_mail(
			$email,
			__( 'Administrator Rescue Link', 'members' ),
			sprintf(
				/* translators: %s: rescue link URL */
				__( "Use this link to restore your Administrator access. It expires in 15 minutes:\n\n%s\n\nIf you did not request this, ignore this email.", 'members' ),
				$link
			)
		);

		$this->redirect_with_generic_message();
	}

	/**
	 * Whether the user is eligible for rescue.
	 * Only the built-in WordPress role `administrator` is allowed (or Super Admin on multisite).
	 * Cloned or custom roles (e.g. "Administrator (clone)") are excluded so they cannot use rescue.
	 *
	 * @param WP_User $user User object.
	 * @return bool
	 */
	private function user_can_be_rescued( WP_User $user ) {
		$roles = (array) $user->roles;
		if ( in_array( 'administrator', $roles, true ) ) {
			return true;
		}
		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Redirects to rescue form with generic success message (no enumeration).
	 */
	private function redirect_with_generic_message() {
		$url = add_query_arg(
			array(
				'action'      => 'members_rescue',
				'check_email' => '1',
			),
			wp_login_url()
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Returns a hashed client IP for rate limiting.
	 *
	 * @return string
	 */
	private function get_client_ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
		return hash( 'sha256', $ip );
	}

	/**
	 * Validates token and performs the repair.
	 */
	public function process_rescue_link() {
		$action = isset( $_GET['members_action'] ) ? sanitize_text_field( wp_unslash( $_GET['members_action'] ) ) : '';
		if ( 'rescue_confirm' !== $action ) {
			return;
		}

		$uid   = isset( $_GET['uid'] ) ? absint( $_GET['uid'] ) : 0;
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		if ( ! $uid || ! $token ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		if ( ! $this->verify_token( $uid, $token ) ) {
			wp_safe_redirect( add_query_arg( 'members_rescue', 'invalid', wp_login_url() ) );
			exit;
		}

		$user = get_user_by( 'id', $uid );
		if ( ! $user || ! $this->user_can_be_rescued( $user ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$this->repair_admin_role( $user );

		do_action( 'members_after_rescue', $uid );

		wp_safe_redirect( add_query_arg( 'members_rescue', 'success', wp_login_url() ) );
		exit;
	}

	/**
	 * Ensures default WordPress roles exist, adds Members admin caps, and assigns user to Administrator.
	 *
	 * @param WP_User $user User object.
	 */
	private function repair_admin_role( WP_User $user ) {
		if ( ! function_exists( 'populate_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/schema.php';
		}
		populate_roles();

		$this->add_members_administrator_caps();

		$user->set_role( 'administrator' );
	}

	/**
	 * Adds the capabilities the Members plugin grants to Administrator on activation.
	 */
	private function add_members_administrator_caps() {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}
		$role->add_cap( 'restrict_content' );
		$role->add_cap( 'list_roles' );
		if ( ! is_multisite() ) {
			$role->add_cap( 'create_roles' );
			$role->add_cap( 'delete_roles' );
			$role->add_cap( 'edit_roles' );
		}
	}

	/**
	 * Generates time-limited token (delegate to single helper).
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function generate_token( $user_id ) {
		$window = (int) floor( time() / self::RATE_LIMIT_WINDOW_SECONDS );
		return $this->generate_token_for_window( $user_id, $window );
	}

	/**
	 * Verifies token (current 15-minute window only) using timing-safe comparison.
	 * Uses floor() so each window covers a full 15 minutes (e.g. window 0 = [0, 900) seconds).
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Token to verify.
	 * @return bool
	 */
	private function verify_token( $user_id, $token ) {
		$window = (int) floor( time() / self::RATE_LIMIT_WINDOW_SECONDS );
		return hash_equals( $this->generate_token_for_window( $user_id, $window ), $token );
	}

	/**
	 * Generates token for a specific time window.
	 *
	 * @param int $user_id User ID.
	 * @param int $window Time window number.
	 * @return string
	 */
	private function generate_token_for_window( $user_id, $window ) {
		return hash_hmac( 'sha256', (string) $user_id . (string) $window, wp_salt( 'auth' ) );
	}
}
