<?php
/**
 * Handles the AI (MCP) settings view.
 *
 * @package    Members
 * @subpackage Admin
 * @since      3.3.0
 */
namespace Members\Admin;

defined('ABSPATH') || exit;

/**
 * AI (MCP) tab: connect AI assistants through the MCP Adapter plugin and choose what they may
 * do. The screen itself is a small React app (src-js/ai) backed by the `members/v1/ai` routes.
 *
 * @since  3.3.0
 * @access public
 */
class View_Ai extends View {

	/**
	 * Enqueues the tab's app.
	 *
	 * @since  3.3.0
	 * @access public
	 * @return void
	 */
	public function enqueue() {

		$asset_file = members_plugin()->dir . 'js/build/ai/index.asset.php';

		// The app needs WordPress 6.9 (Abilities API).
		if ( ! $this->supported() || ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script( 'members-ai', members_plugin()->uri . 'js/build/ai/index.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'members-ai', members_plugin()->uri . 'js/build/ai/style-index.css', array( 'wp-components' ), $asset['version'] );
		wp_style_add_data( 'members-ai', 'rtl', 'replace' );
		wp_set_script_translations( 'members-ai', 'members' );
	}

	/**
	 * Renders the app's mount point.
	 *
	 * @since  3.3.0
	 * @access public
	 * @return void
	 */
	public function template() {

		if ( ! $this->supported() ) : ?>

			<div class="members-ai">
				<div class="notice notice-info inline">
					<p>
						<strong><?php esc_html_e( 'Needs WordPress 6.9 or newer', 'members' ); ?></strong><br />
						<?php esc_html_e( 'AI connections use the Abilities API that arrived in WordPress 6.9. Update WordPress to turn this on.', 'members' ); ?>
					</p>
					<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>"><?php esc_html_e( 'Update WordPress', 'members' ); ?></a></p>
				</div>
			</div>

		<?php return; endif; ?>

		<div id="members-ai-root" class="members-ai"></div>
	<?php }

	/**
	 * Whether this WordPress has the Abilities API.
	 *
	 * @since  3.3.0
	 * @access private
	 * @return bool
	 */
	private function supported() {

		return function_exists( 'wp_register_ability' );
	}
}
