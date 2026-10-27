<?php
/**
 * "New: AI (MCP)" notice.
 *
 * @package    Members
 * @subpackage Abilities
 * @since      3.3.0
 */
namespace Members\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * One-time, dismissible notice on the Members screens linking to the AI (MCP) settings tab.
 * Only shown on WordPress 6.9+, where the feature works; older sites get it after they update.
 *
 * @since 3.3.0
 */
final class Announcement {

	const USER_META = 'members_mcp_notice_dismissed';
	const ACTION    = 'members_dismiss_mcp_notice';

	/**
	 * Hook the notice up for this admin request.
	 *
	 * @return void
	 */
	public static function maybe_show() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'dismiss' ) );

		if ( ! function_exists( 'wp_register_ability' ) || wp_doing_ajax() || ! current_user_can( Ability::settings_cap() ) ) {
			return;
		}

		if ( get_user_meta( get_current_user_id(), self::USER_META, true ) ) {
			return;
		}

		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
	}

	/**
	 * Print the notice on Members screens (not on the AI tab itself).
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! function_exists( 'members_is_admin_page' ) || ! members_is_admin_page() || members_is_settings_view( 'ai' ) ) {
			return;
		}

		$url = members_get_settings_view_url( 'ai' );
		?>
		<div class="notice notice-info is-dismissible members-mcp-notice" data-nonce="<?php echo esc_attr( wp_create_nonce( self::ACTION ) ); ?>">
			<p>
				<?php
				printf(
					/* translators: %1$s: open link, %2$s: close link. */
					esc_html__( 'New: AI assistants like Claude, Cursor and Codex can now manage your roles, capabilities and content restrictions. %1$sSet up AI (MCP)%2$s', 'members' ),
					'<a href="' . esc_url( $url ) . '">',
					'</a>'
				);
				?>
			</p>
		</div>
		<script>
			( function () {
				document.addEventListener( 'click', function ( e ) {
					var notice = e.target.closest( '.members-mcp-notice' );
					if ( ! notice || ! e.target.closest( '.notice-dismiss, a' ) ) {
						return;
					}
					var body = new FormData();
					body.append( 'action', '<?php echo esc_js( self::ACTION ); ?>' );
					body.append( 'nonce', notice.getAttribute( 'data-nonce' ) );
					fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } );
				} );
			} )();
		</script>
		<?php
	}

	/**
	 * Remember the dismissal.
	 *
	 * @return void
	 */
	public static function dismiss() {
		check_ajax_referer( self::ACTION, 'nonce' );
		update_user_meta( get_current_user_id(), self::USER_META, 1 );
		wp_send_json_success();
	}
}
