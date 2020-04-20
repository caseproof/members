<?php
/**
 * Handles the settings screen.
 *
 * @package    Members
 * @subpackage Admin
 * @author     Justin Tadlock <justintadlock@gmail.com>
 * @copyright  Copyright (c) 2009 - 2018, Justin Tadlock
 * @link       https://themehybrid.com/plugins/members
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Members\Admin;

/**
 * Sets up and handles the plugin settings screen.
 *
 * @since  1.0.0
 * @access public
 */
final class Settings_Page {

	/**
	 * Admin page name/ID.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    string
	 */
	public $name = 'members-settings';

	/**
	 * Settings page name.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $settings_page = '';

	/**
	 * Holds an array the settings page views.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    array
	 */
	public $views = array();

	/**
	 * Returns the instance.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return object
	 */
	public static function get_instance() {

		static $instance = null;

		if ( is_null( $instance ) ) {
			$instance = new self;
			$instance->includes();
			$instance->setup_actions();
		}

		return $instance;
	}

	/**
	 * Constructor method.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Loads settings files.
	 *
	 * @since  2.0.0
	 * @access private
	 * @return void
	 */
	private function includes() {

		// Include the settings functions.
		require_once( members_plugin()->dir . 'admin/functions-settings.php' );

		// Load settings view classes.
		require_once( members_plugin()->dir . 'admin/views/class-view.php'         );
		require_once( members_plugin()->dir . 'admin/views/class-view-general.php' );
		require_once( members_plugin()->dir . 'admin/views/class-view-addons.php'  );
	}

	/**
	 * Sets up initial actions.
	 *
	 * @since  2.0.0
	 * @access private
	 * @return void
	 */
	private function setup_actions() {

		add_action( 'admin_menu', array( $this, 'admin_menu' ), 25 );
		add_action( 'wp_ajax_mbrs_toggle_addon', array( $this, 'toggle_addon' ) );
	}

	/**
	 * AJAX call to toggle an addon off and on
	 *
	 * @return void
	 */
	public function toggle_addon() {
		
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'mbrs_toggle_addon' ) ) {
			die();
		}

		$addon = ! empty( $_POST['addon'] ) ? sanitize_text_field( $_POST['addon'] ) : false;

		if ( false === $addon ) {
			wp_send_json_error( array(
				'msg' => esc_html__( 'No add-on provided.', 'members' )
			) );
		}

		// Grab the currently active add-ons
		$active_addons = get_option( 'members_active_addons', array() );

		if ( ! in_array( $addon, $active_addons ) ) { // Activate the addon
			$active_addons[] = $addon;
			$response = array(
				'status' => 'active',
				'action_label' => esc_html__( 'Active', 'members' ),
				'msg' => esc_html__( 'Add-on activated', 'members' )
			);

			// Run the add-on's activation hook
			members_plugin()->run_addon_activator( $addon );

		} else { // Deactivate the addon
			$key = array_search( $addon, $active_addons );
			unset( $active_addons[$key] );
			$response = array(
				'status' => 'inactive',
				'action_label' => esc_html__( 'Activate', 'members' ),
				'msg' => esc_html__( 'Add-on deactivated', 'members' )
			);
		}

		update_option( 'members_active_addons', $active_addons );

		wp_send_json_success( $response );
	}

	/**
	 * Register a view.
	 *
	 * @since  2.0.0
	 * @access public
	 * @param  object  $view
	 * @return void
	 */
	public function register_view( $view ) {

		if ( ! $this->view_exists( $view->name ) )
			$this->views[ $view->name ] = $view;
	}

	/**
	 * Unregister a view.
	 *
	 * @since  2.0.0
	 * @access public
	 * @param  string  $name
	 * @return void
	 */
	public function unregister_view( $name ) {

		if ( $this->view_exists( $name ) )
			unset( $this->view[ $name ] );
	}

	/**
	 * Get a view object
	 *
	 * @since  2.0.0
	 * @access public
	 * @param  string  $name
	 * @return object
	 */
	public function get_view( $name ) {

		return $this->view_exists( $name ) ? $this->views[ $name ] : false;
	}

	/**
	 * Check if a view exists.
	 *
	 * @since  2.0.0
	 * @access public
	 * @param  string  $name
	 * @return bool
	 */
	public function view_exists( $name ) {

		return isset( $this->views[ $name ] );
	}

	/**
	 * Sets up custom admin menus.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function admin_menu() {

		// Create the settings page.
		$this->settings_page = add_submenu_page( 'members', esc_html_x( 'Settings', 'admin screen', 'members' ), esc_html_x( 'Settings', 'admin screen', 'members' ), apply_filters( 'members_settings_capability', 'manage_options' ), 'members-settings', array( $this, 'settings_page' ) );
		$this->addons_page = add_submenu_page( 'members', esc_html_x( 'Add-Ons', 'admin screen', 'members' ), _x( '<span style="color: #8CBD5A;">Add-Ons</span>', 'admin screen', 'members' ), apply_filters( 'members_settings_capability', 'manage_options' ), 'members-settings&view=add-ons', array( $this, 'settings_page' ) );
		if ( ! is_plugin_active( 'memberpress/memberpress.php' ) ) {
			$this->payments_page = add_submenu_page( 'members', esc_html_x( 'Payments', 'admin screen', 'members' ), esc_html_x( 'Payments', 'admin screen', 'members' ), apply_filters( 'members_settings_capability', 'manage_options' ), 'members-payments', array( $this, 'payments_page' ) );
		}
		$this->about_page = add_submenu_page( 'members', esc_html_x( 'About Us', 'admin screen', 'members' ), esc_html_x( 'About Us', 'admin screen', 'members' ), apply_filters( 'members_settings_capability', 'manage_options' ), 'members-about', array( $this, 'about_page' ) );

		if ( $this->settings_page ) {

			do_action( 'members_register_settings_views', $this );

			uasort( $this->views, 'members_priority_sort' );

			// Register setings.
			add_action( 'admin_init', array( $this, 'register_settings' ) );

			// Page load callback.
			add_action( "load-{$this->settings_page}", array( $this, 'load' ) );

			// Enqueue scripts/styles.
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		}
	}

	/**
	 * Runs on page load.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function load() {

		// Print custom styles.
		add_action( 'admin_head', array( $this, 'print_styles' ) );

		// Add help tabs for the current view.
		$view = $this->get_view( members_get_current_settings_view() );

		if ( $view ) {
			$view->load();
			$view->add_help_tabs();
		}
	}

	/**
	 * Print styles to the header.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function print_styles() { ?>

		<style type="text/css">
			
		</style>
	<?php }

	/**
	 * Enqueue scripts/styles.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $hook_suffix
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {

		if ( $this->settings_page !== $hook_suffix )
			return;

		$view = $this->get_view( members_get_current_settings_view() );

		if ( $view )
			$view->enqueue();
	}

	/**
	 * Registers the plugin settings.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	function register_settings() {

		foreach ( $this->views as $view )
			$view->register_settings();
	}

	/**
	 * Renders the settings page.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function settings_page() { ?>

		<div class="wrap">
			<h1><?php echo esc_html_x( 'Members', 'admin screen', 'members' ); ?></h1>
			<div class="wp-filter">
				<?php echo $this->filter_links(); ?>
			</div>
			<?php $this->get_view( members_get_current_settings_view() )->template(); ?>
		</div><!-- wrap -->
	<?php }

	/**
	 * Renders the payments page.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function payments_page() { 

		wp_enqueue_style( 'members-admin' );
		wp_enqueue_script( 'members-settings' );

		?>

		<div class="wrap">
			<h1><?php echo esc_html_x( 'Payments', 'admin screen', 'members' ); ?></h1>
			<div class="mepr-upgrade-table">
				<?php members_memberpress_upgrade( 'https://memberpress.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=payments&utm_content=payments_page' ); ?>
				<table class="wp-list-table widefat fixed striped mepr_dummy_txns">
					<thead>
						<tr>
							<th scope="col" class="manage-column column-col_id column-primary"><a href=""><span>Id</span></a></th>
							<th scope="col" class="manage-column column-col_id column-primary"><a href=""><span>Transaction</span></a></th>
							<th scope="col" class="manage-column column-col_id column-primary"><a href=""><span>Subscription</span></a></th>
							<th scope="col" class="manage-column column-col_id column-primary"><a href=""><span>Status</span></a></th>
							<th scope="col" class="manage-column column-col_id column-primary"><a href=""><span>Membership</span></a></th>
							<th scope="col" class="manage-column column-col_id column-primary"><a href=""><span>Net</span></a></th>
							<th scope="col" class="manage-column column-col_id column-primary"><a href=""><span>Tax</span></a></th>
							<th scope="col" class="manage-column column-col_id column-primary"><a href=""><span>Total</span></a></th>
							<th scope="col" class="manage-column column-col_id column-primary"><a href=""><span>Name</span></a></th>
							<th scope="col" class="manage-column column-col_id column-primary"><a href=""><span>User</span></a></th>
							<th scope="col" class="manage-column column-col_id column-primary"><a href=""><span>Gateway</span></a></th>
							<th scope="col" class="manage-column column-col_id column-primary"><a href=""><span>Created On</span></a></th>
							<th scope="col" class="manage-column column-col_id column-primary"><a href=""><span>Expires On</span></a></th>
						</tr>
					</thead>
					<tbody id="the-list">
						<tr class="alternate">
							<td class="col_id column-col_id">1</td>
							<td class="col_trans_num column-col_trans_num">
								<a href="">1</a>
							</td>
							<td class="col_subscr_id column-col_subscr_id">None</td>
							<td class="col_status column-col_status">
								<div class="status_initial">
									<a href="" title="Change transaction's status">Complete</a>
								</div>
							</td>
							<td class="col_product column-col_product"><a href="">Your Membership</a></td>
							<td class="col_net column-col_net">$20.00</td>
							<td class="col_tax column-col_tax">$0.00</td>
							<td class="col_total column-col_total">$20.00</td>
							<td class="col_propername column-col_propername">Your Customer</td>
							<td class="col_user_login column-col_user_login"><a href="#" title="View member's profile">user</a></td>
							<td class="col_payment_system column-col_payment_system">Payment Method</td>
							<td class="col_created_at column-col_created_at">January 27, 2020</td>
							<td class="col_expires_at column-col_expires_at">Never</td>
						</tr>
						<tr class="">
							<td class="col_id column-col_id">2</td>
							<td class="col_trans_num column-col_trans_num">
								<a href="">2</a>
							</td>
							<td class="col_subscr_id column-col_subscr_id">None</td>
							<td class="col_status column-col_status">
								<div class="status_initial">
									<a href="" title="Change transaction's status">Complete</a>
								</div>
							</td>
							<td class="col_product column-col_product"><a href="">Your Membership</a></td>
							<td class="col_net column-col_net">$20.00</td>
							<td class="col_tax column-col_tax">$0.00</td>
							<td class="col_total column-col_total">$20.00</td>
							<td class="col_propername column-col_propername">Your Customer</td>
							<td class="col_user_login column-col_user_login"><a href="#" title="View member's profile">user</a></td>
							<td class="col_payment_system column-col_payment_system">Payment Method</td>
							<td class="col_created_at column-col_created_at">January 27, 2020</td>
							<td class="col_expires_at column-col_expires_at">Never</td>
						</tr>
						<tr class="alternate">
							<td class="col_id column-col_id">3</td>
							<td class="col_trans_num column-col_trans_num">
								<a href="">3</a>
							</td>
							<td class="col_subscr_id column-col_subscr_id">None</td>
							<td class="col_status column-col_status">
								<div class="status_initial">
									<a href="" title="Change transaction's status">Complete</a>
								</div>
							</td>
							<td class="col_product column-col_product"><a href="">Your Membership</a></td>
							<td class="col_net column-col_net">$20.00</td>
							<td class="col_tax column-col_tax">$0.00</td>
							<td class="col_total column-col_total">$20.00</td>
							<td class="col_propername column-col_propername">Your Customer</td>
							<td class="col_user_login column-col_user_login"><a href="#" title="View member's profile">user</a></td>
							<td class="col_payment_system column-col_payment_system">Payment Method</td>
							<td class="col_created_at column-col_created_at">January 27, 2020</td>
							<td class="col_expires_at column-col_expires_at">Never</td>
						</tr>
						<tr class="">
							<td class="col_id column-col_id">4</td>
							<td class="col_trans_num column-col_trans_num">
								<a href="">4</a>
							</td>
							<td class="col_subscr_id column-col_subscr_id">None</td>
							<td class="col_status column-col_status">
								<div class="status_initial">
									<a href="" title="Change transaction's status">Complete</a>
								</div>
							</td>
							<td class="col_product column-col_product"><a href="">Your Membership</a></td>
							<td class="col_net column-col_net">$20.00</td>
							<td class="col_tax column-col_tax">$0.00</td>
							<td class="col_total column-col_total">$20.00</td>
							<td class="col_propername column-col_propername">Your Customer</td>
							<td class="col_user_login column-col_user_login"><a href="#" title="View member's profile">user</a></td>
							<td class="col_payment_system column-col_payment_system">Payment Method</td>
							<td class="col_created_at column-col_created_at">January 27, 2020</td>
							<td class="col_expires_at column-col_expires_at">Never</td>
						</tr>
						<tr class="alternate">
							<td class="col_id column-col_id">5</td>
							<td class="col_trans_num column-col_trans_num">
								<a href="">5</a>
							</td>
							<td class="col_subscr_id column-col_subscr_id">None</td>
							<td class="col_status column-col_status">
								<div class="status_initial">
									<a href="" title="Change transaction's status">Complete</a>
								</div>
							</td>
							<td class="col_product column-col_product"><a href="">Your Membership</a></td>
							<td class="col_net column-col_net">$20.00</td>
							<td class="col_tax column-col_tax">$0.00</td>
							<td class="col_total column-col_total">$20.00</td>
							<td class="col_propername column-col_propername">Your Customer</td>
							<td class="col_user_login column-col_user_login"><a href="#" title="View member's profile">user</a></td>
							<td class="col_payment_system column-col_payment_system">Payment Method</td>
							<td class="col_created_at column-col_created_at">January 27, 2020</td>
							<td class="col_expires_at column-col_expires_at">Never</td>
						</tr>
						<tr class="">
							<td class="col_id column-col_id">6</td>
							<td class="col_trans_num column-col_trans_num">
								<a href="">6</a>
							</td>
							<td class="col_subscr_id column-col_subscr_id">None</td>
							<td class="col_status column-col_status">
								<div class="status_initial">
									<a href="" title="Change transaction's status">Complete</a>
								</div>
							</td>
							<td class="col_product column-col_product"><a href="">Your Membership</a></td>
							<td class="col_net column-col_net">$20.00</td>
							<td class="col_tax column-col_tax">$0.00</td>
							<td class="col_total column-col_total">$20.00</td>
							<td class="col_propername column-col_propername">Your Customer</td>
							<td class="col_user_login column-col_user_login"><a href="#" title="View member's profile">user</a></td>
							<td class="col_payment_system column-col_payment_system">Payment Method</td>
							<td class="col_created_at column-col_created_at">January 27, 2020</td>
							<td class="col_expires_at column-col_expires_at">Never</td>
						</tr>
						<tr class="alternate">
							<td class="col_id column-col_id">7</td>
							<td class="col_trans_num column-col_trans_num">
								<a href="">7</a>
							</td>
							<td class="col_subscr_id column-col_subscr_id">None</td>
							<td class="col_status column-col_status">
								<div class="status_initial">
									<a href="" title="Change transaction's status">Complete</a>
								</div>
							</td>
							<td class="col_product column-col_product"><a href="">Your Membership</a></td>
							<td class="col_net column-col_net">$20.00</td>
							<td class="col_tax column-col_tax">$0.00</td>
							<td class="col_total column-col_total">$20.00</td>
							<td class="col_propername column-col_propername">Your Customer</td>
							<td class="col_user_login column-col_user_login"><a href="#" title="View member's profile">user</a></td>
							<td class="col_payment_system column-col_payment_system">Payment Method</td>
							<td class="col_created_at column-col_created_at">January 27, 2020</td>
							<td class="col_expires_at column-col_expires_at">Never</td>
						</tr>
						<tr class="">
							<td class="col_id column-col_id">8</td>
							<td class="col_trans_num column-col_trans_num">
								<a href="">8</a>
							</td>
							<td class="col_subscr_id column-col_subscr_id">None</td>
							<td class="col_status column-col_status">
								<div class="status_initial">
									<a href="" title="Change transaction's status">Complete</a>
								</div>
							</td>
							<td class="col_product column-col_product"><a href="">Your Membership</a></td>
							<td class="col_net column-col_net">$20.00</td>
							<td class="col_tax column-col_tax">$0.00</td>
							<td class="col_total column-col_total">$20.00</td>
							<td class="col_propername column-col_propername">Your Customer</td>
							<td class="col_user_login column-col_user_login"><a href="#" title="View member's profile">user</a></td>
							<td class="col_payment_system column-col_payment_system">Payment Method</td>
							<td class="col_created_at column-col_created_at">January 27, 2020</td>
							<td class="col_expires_at column-col_expires_at">Never</td>
						</tr>
						<tr class="alternate">
							<td class="col_id column-col_id">9</td>
							<td class="col_trans_num column-col_trans_num">
								<a href="">9</a>
							</td>
							<td class="col_subscr_id column-col_subscr_id">None</td>
							<td class="col_status column-col_status">
								<div class="status_initial">
									<a href="" title="Change transaction's status">Complete</a>
								</div>
							</td>
							<td class="col_product column-col_product"><a href="">Your Membership</a></td>
							<td class="col_net column-col_net">$20.00</td>
							<td class="col_tax column-col_tax">$0.00</td>
							<td class="col_total column-col_total">$20.00</td>
							<td class="col_propername column-col_propername">Your Customer</td>
							<td class="col_user_login column-col_user_login"><a href="#" title="View member's profile">user</a></td>
							<td class="col_payment_system column-col_payment_system">Payment Method</td>
							<td class="col_created_at column-col_created_at">January 27, 2020</td>
							<td class="col_expires_at column-col_expires_at">Never</td>
						</tr>
						<tr class="">
							<td class="col_id column-col_id">10</td>
							<td class="col_trans_num column-col_trans_num">
								<a href="">10</a>
							</td>
							<td class="col_subscr_id column-col_subscr_id">None</td>
							<td class="col_status column-col_status">
								<div class="status_initial">
									<a href="" title="Change transaction's status">Complete</a>
								</div>
							</td>
							<td class="col_product column-col_product"><a href="">Your Membership</a></td>
							<td class="col_net column-col_net">$20.00</td>
							<td class="col_tax column-col_tax">$0.00</td>
							<td class="col_total column-col_total">$20.00</td>
							<td class="col_propername column-col_propername">Your Customer</td>
							<td class="col_user_login column-col_user_login"><a href="#" title="View member's profile">user</a></td>
							<td class="col_payment_system column-col_payment_system">Payment Method</td>
							<td class="col_created_at column-col_created_at">January 27, 2020</td>
							<td class="col_expires_at column-col_expires_at">Never</td>
						</tr>
					</tbody>

				</table>
			</div>
		</div><!-- wrap -->
	<?php }

	/**
	 * Renders the about page.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function about_page() {

		$installed_plugins = get_plugins();

		wp_enqueue_style( 'members-admin' );
		wp_enqueue_script( 'members-settings' );

		?>

		<div class="wrap">
			<h1><?php echo esc_html_x( 'About Us', 'admin screen', 'members' ); ?></h1>
			<div class="welcome-panel">
				<div class="welcome-panel-content memberpress-about">
					<div class="welcome-panel-column-container">
						<div class="mp-desc">
							<p style="font-weight: bold;">Hello and welcome to Members by <a href="https://memberpress.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=link_1" target="_blank">MemberPress</a>, the simplest WordPress membership and role editor plugin. Our team here at MemberPress builds software that helps you to easily add powerful membership features to your website in minutes.</p>
							<p>Over the years, we found that most WordPress membership plugins were bloated, buggy, slow, very hard to use and expensive. So, we started with a simple goal: build a WordPress membership plugin that’s both easy and powerful.</p>
							<p>Our goal is to take the pain out of creating membership sites and make it easy.</p>
							<p>Members is brought to you by the same team that’s behind the most powerful, full-featured membership plugin, <a href="https://memberpress.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=link_2" target="_blank">MemberPress</a>, the best Affiliate Program plugin, <a href="https://affiliateroyale.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=link_3" target="_blank">Affiliate Royale</a>, and the best Affiliate Link Management plugin on the market, <a href="https://prettylinks.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=link_4" target="_blank">Pretty Links</a>.</p>
							<p>So, you can see that we know a thing or two about building great products that customers love.</p>
						</div>
						<div class="mp-logo-wrap">
							<a href="https://memberpress.com/?utm_source=members_plugin&utm_medium=banner&utm_campaign=about_us&utm_content=memberpress_logo_large">
								<img src="<?php echo members_plugin()->uri . "img/mp-logo-stacked-RGB.jpg"; ?>" class="mp-logo" alt="">
							</a>
						</div>	
					</div>
				</div>
			</div>
			<div class="members-about-addons">
				<div class="members-plugin-card plugin-card plugin-card-memberpress" style="margin-left: 0;">
					<div class="plugin-card-top">
						<div class="name column-name">
							<h3>
								<a href="https://memberpress.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=memberpress_icon_title" target="_blank" rel="noopener noreferrer">
									MemberPress <img src="<?php echo members_plugin()->uri . "img/mp-icon-RGB.jpg"; ?>" class="plugin-icon" alt="">
								</a>
							</h3>
						</div>
						<div class="desc column-description">
							<p>MemberPress will help you build astounding WordPress membership sites, accept credit cards securely, control who sees your content and sell digital downloads... all without the difficult setup.</p>
						</div>
					</div>
					<div class="plugin-card-bottom">
						<?php if ( is_plugin_active( 'memberpress/memberpress.php' ) ) : // Installed and active ?>
							<div class="column-rating column-status">Status: <span class="active">Active</span></div>
							<div class="column-updated"><a href="https://memberpress.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=memberpress_learn_more" target="_blank" class="button button-secondary">Learn More</a></div>
						<?php elseif ( array_key_exists( 'memberpress/memberpress.php', $installed_plugins ) ) : // Installed but inactive ?>
							<div class="column-rating column-status">Status: <span class="inactive">Inactive</span></div>
							<div class="column-updated"><a href="<?php echo wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=memberpress/memberpress.php' ), 'activate-plugin_memberpress/memberpress.php' ); ?>" class="button button-secondary">Activate</a></div>
						<?php else : // Not installed ?>
							<div class="column-rating column-status">Status: Not Installed</div>
							<div class="column-updated"><a href="https://memberpress.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=memberpress_install" target="_blank" class="button button-primary">Install Plugin</a></div>
						<?php endif; ?>
					</div>
				</div>

				<div class="members-plugin-card plugin-card plugin-card-pretty-links">
					<div class="plugin-card-top">
						<div class="name column-name">
							<h3>
								<a href="https://prettylinks.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=prettylinks_icon_title" target="_blank" rel="noopener noreferrer">
									Pretty Links <img src="<?php echo members_plugin()->uri . "img/pl-icon-RGB.jpg"; ?>" class="plugin-icon" alt="">
								</a>
							</h3>
						</div>
						<div class="desc column-description">
							<p>The easiest way to monetize your content. Are you tired of managing affiliate offers manually? Pretty Links helps you unlock more affiliate revenue from your existing content ... it’s like a surprise inheritance!</p>
						</div>
					</div>
					<div class="plugin-card-bottom">
						<?php if ( is_plugin_active( 'pretty-link/pretty-link.php' ) ) : // Installed and active ?>
							<div class="column-rating column-status">Status: <span class="active">Active</span></div>
							<div class="column-updated"><a href="https://prettylinks.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=prettylinks_learn_more" target="_blank" class="button button-secondary">Learn More</a></div>
						<?php elseif ( array_key_exists( 'pretty-link/pretty-link.php', $installed_plugins ) ) : // Installed but inactive ?>
							<div class="column-rating column-status">Status: <span class="inactive">Inactive</span></div>
							<div class="column-updated"><a href="<?php echo wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=pretty-link/pretty-link.php' ), 'activate-plugin_pretty-link/pretty-link.php' ); ?>" class="button button-secondary">Activate</a></div>
						<?php else : // Not installed ?>
							<div class="column-rating column-status">Status: Not Installed</div>
							<div class="column-updated"><a href="https://prettylinks.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=prettylinks_install" target="_blank" class="button button-primary">Install Plugin</a></div>
						<?php endif; ?>
					</div>
				</div>

				<div class="members-plugin-card plugin-card plugin-card-affiliate-royale" style="margin-right: 0;">
					<div class="plugin-card-top">
						<div class="name column-name">
							<h3>
								<a href="https://affiliateroyale.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=affiliateroyale_icon_title" target="_blank" rel="noopener noreferrer">
									Affiliate Royale <img src="<?php echo members_plugin()->uri . "img/affiliate_blue-01.png"; ?>" class="plugin-icon" alt="">
								</a>
							</h3>
						</div>
						<div class="desc column-description">
							<p>Affiliate Royale is a full-featured Affiliate Program plugin for WordPress. Use it to start an Affiliate Program for your products to dramatically increase traffic, attention and sales.</p>
						</div>
					</div>
					<div class="plugin-card-bottom">
						<?php if ( is_plugin_active( 'affiliate-royale/affiliate-royale.php' ) ) : // Installed and active ?>
							<div class="column-rating column-status">Status: <span class="active">Active</span></div>
							<div class="column-updated"><a href="https://affiliateroyale.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=affiliateroyale_learn_more" target="_blank" class="button button-secondary">Learn More</a></div>
						<?php elseif ( array_key_exists( 'affiliate-royale/affiliate-royale.php', $installed_plugins ) ) : // Installed but inactive ?>
							<div class="column-rating column-status">Status: <span class="inactive">Inactive</span></div>
							<div class="column-updated"><a href="<?php echo wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=affiliate-royale/affiliate-royale.php' ), 'activate-plugin_affiliate-royale/affiliate-royale.php' ); ?>" class="button button-secondary">Activate</a></div>
						<?php else : // Not installed ?>
							<div class="column-rating column-status">Status: Not Installed</div>
							<div class="column-updated"><a href="https://affiliateroyale.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=affiliateroyale_install" target="_blank" class="button button-primary">Install Plugin</a></div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div><!-- wrap -->
	<?php }

	/**
	 * Outputs the list of views.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	private function filter_links() { ?>

		<ul class="filter-links">

			<?php foreach ( $this->views as $view ) :

				// Determine current class.
				$class = $view->name === members_get_current_settings_view() ? 'class="current"' : '';

				// Get the URL.
				$url = members_get_settings_view_url( $view->name );

				if ( 'general' === $view->name )
					$url = remove_query_arg( 'view', $url ); ?>

				<li class="<?php echo sanitize_html_class( $view->name ); ?>">
					<a href="<?php echo esc_url( $url ); ?>" <?php echo $class; ?>><?php echo esc_html( $view->label ); ?></a>
				</li>

			<?php endforeach; ?>

		</ul>
	<?php }

	/**
	 * Adds help tabs.
	 *
	 * @since      1.0.0
	 * @deprecated 2.0.0
	 * @access     public
	 * @return     void
	 */
	public function add_help_tabs() {}
}

Settings_Page::get_instance();
