<?php
/**
 * Handles the add-ons settings view.
 *
 * @package    Members
 * @subpackage Admin
 * @author     The MemberPress Team 
 * @copyright  Copyright (c) 2009 - 2018, The MemberPress Team
 * @link       https://members-plugin.com/
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Members\Admin;

/**
 * Sets up and handles the add-ons settings view.
 *
 * @since  2.0.0
 * @access public
 */
class View_Addons extends View {

	/**
	 * Enqueues scripts/styles.
	 *
	 * @since  2.2.0
	 * @access public
	 * @return void
	 */
	public function enqueue() {
		wp_enqueue_style( 'members-admin' );
		wp_enqueue_script( 'members-settings' );
		wp_localize_script( 'members-settings', 'membersAddons', array(
			'nonce' => wp_create_nonce( 'mbrs_toggle_addon' )
		) );
	}

	/**
	 * Renders the settings page.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function template() {

		require_once( members_plugin()->dir . 'admin/class-addon.php'      );
		require_once( members_plugin()->dir . 'admin/functions-addons.php' );
		add_thickbox();

		do_action( 'members_register_addons' );

		$addons = members_get_addons(); ?>

		<div class="widefat">

			<div class="members-addons">

				<?php if ( $addons ) : ?>

					<?php foreach ( $addons as $addon ) : ?>

						<?php
							if ( $addon->is_memberpress ) {
								if ( ! members_is_memberpress_active() ) {
									$this->addon_card( $addon );
								}
							} else {
								$this->addon_card( $addon );
							}
						?>

					<?php endforeach; ?>

				<?php else : ?>

					<div class="error notice">
						<p>
							<strong><?php esc_html_e( 'There are currently no add-ons to show. Please try again later.', 'members' ); ?></strong>
						</p>
					</div>

				<?php endif; ?>

			</div>

		</div><!-- .widefat -->

		<div id="mp_addon_modal" style="display: none;">
			<?php members_memberpress_upgrade(); ?>
		</div>
		<script>
			jQuery(document).ready(function($) {
				$('.mepr-upgrade-activate-link').on('click', function(e){
					var url = $(this).data('url');
					$('#mepr_cta_upgrade_link').prop('href', url);
				});
			});
		</script>
	<?php }

	/**
	 * Renders an individual add-on plugin card.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function addon_card( $addon ) { ?>

		<div class="plugin-card members-addon plugin-card-<?php echo esc_attr( $addon->name ); ?>">

			<div class="plugin-card-top">

				<div class="name column-name">
					<h3>
						<?php if ( $addon->url ) : ?>
              <?php if ($addon->is_memberpress) : ?>
                <img src="<?php echo members_plugin()->uri . "img/mp-icon-RGB.jpg"; ?>" class="plugin-icon-small" alt="">
              <?php endif; ?>
							<a href="<?php echo esc_url( $addon->url ); ?>" target="_blank">
						<?php endif; ?>

							<?php echo esc_html( $addon->title ); ?>

							<?php if ( file_exists( members_plugin()->dir . "img/{$addon->name}.svg" ) ) : ?>

								<span class="plugin-icon members-svg-link">
									<?php echo file_get_contents( members_plugin()->dir . "img/{$addon->name}.svg" ); ?>
								</span>

							<?php elseif ( $addon->icon_url ) : ?>

								<img class="plugin-icon" src="<?php echo esc_url( $addon->icon_url ); ?>" alt="" />

							<?php endif; ?>

						<?php if ( $addon->url ) : ?>
							</a>
						<?php endif; ?>
					</h3>
				</div>

				<div class="desc column-description" style="margin-right:0;">
					<?php echo wpautop( wp_kses_post( $addon->excerpt ) ); ?>
				</div>

				<div class="addon-activate">
					<?php if ( isset( $addon->is_memberpress ) && true === $addon->is_memberpress ) : ?>
						<span class="activate-toggle" data-addon="<?php echo $addon->name; ?>">
							<a href="#TB_inline?width=600&height=550&inlineId=mp_addon_modal" data-url="<?php echo esc_url( $addon->url ); ?>" class="thickbox mepr-upgrade-activate-link" target="_blank">
								<svg aria-hidden="true" class="" focusable="false" data-prefix="fas" data-icon="toggle-on" class="svg-inline--fa fa-toggle-on fa-w-18" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M384 64H192C86 64 0 150 0 256s86 192 192 192h192c106 0 192-86 192-192S490 64 384 64zm0 320c-70.8 0-128-57.3-128-128 0-70.8 57.3-128 128-128 70.8 0 128 57.3 128 128 0 70.8-57.3 128-128 128z"></path></svg>
								<span class="action-label"><?php echo esc_html__( 'Activate', 'members' ); ?></span>
							</a>
						</span>
					<?php else : ?>
						<span class="activate-toggle activate-addon" data-addon="<?php echo $addon->name; ?>">
							<svg aria-hidden="true" class="<?php echo members_is_addon_active( $addon->name ) ? 'active' : ''; ?>" focusable="false" data-prefix="fas" data-icon="toggle-on" class="svg-inline--fa fa-toggle-on fa-w-18" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M384 64H192C86 64 0 150 0 256s86 192 192 192h192c106 0 192-86 192-192S490 64 384 64zm0 320c-70.8 0-128-57.3-128-128 0-70.8 57.3-128 128-128 70.8 0 128 57.3 128 128 0 70.8-57.3 128-128 128z"></path></svg>
							<span class="action-label"><?php echo members_is_addon_active( $addon->name ) ? esc_html__( 'Active', 'members' ) : esc_html__( 'Activate', 'members' ); ?></span>
						</span>
					<?php endif; ?>
				</div>

			</div><!-- .plugin-card-top -->

		</div><!-- .plugin-card -->

	<?php }

	/**
	 * Adds help tabs.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function add_help_tabs() {

		// Get the current screen.
		$screen = get_current_screen();

		// Roles/Caps help tab.
		$screen->add_help_tab(
			array(
				'id'       => 'overview',
				'title'    => esc_html__( 'Overview', 'members' ),
				'callback' => array( $this, 'help_tab_overview' )
			)
		);

		// Roles/Caps help tab.
		$screen->add_help_tab(
			array(
				'id'       => 'download',
				'title'    => esc_html__( 'Download', 'members' ),
				'callback' => array( $this, 'help_tab_download' )
			)
		);

		// Roles/Caps help tab.
		$screen->add_help_tab(
			array(
				'id'       => 'purchase',
				'title'    => esc_html__( 'Purchase', 'members' ),
				'callback' => array( $this, 'help_tab_purchase' )
			)
		);

		// Set the help sidebar.
		$screen->set_help_sidebar( members_get_help_sidebar_text() );
	}

	/**
	 * Displays the overview help tab.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function help_tab_overview() { ?>

		<p>
			<?php esc_html_e( 'The Add-Ons screen allows you to view available add-ons for the Members plugin. You can download some plugins directly. Others may be available to purchase.', 'members' ); ?>
		</p>
	<?php }

	/**
	 * Displays the download help tab.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function help_tab_download() { ?>

		<p>
			<?php esc_html_e( 'Some plugins may be available for direct download. In such cases, you can click the download button to get a ZIP file of the plugin.', 'members' ); ?>
		</p>
	<?php }

	/**
	 * Displays the purchase help tab.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function help_tab_purchase() { ?>

		<p>
			<?php esc_html_e( 'Some add-ons may require purchase before downloading them. Clicking the purchase button will take you off-site to view the add-on in more detail.', 'members' ); ?>
		</p>
	<?php }
}
