<?php
/**
 * Handles the settings screen.
 *
 * @package    Members
 * @subpackage Admin
 * @author     The MemberPress Team 
 * @copyright  Copyright (c) 2009 - 2018, The MemberPress Team
 * @link       https://members-plugin.com/
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */
namespace Members\Admin;

defined('ABSPATH') || exit;
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
	 * Admin page names.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    array
	 */
	public $admin_pages = array();

	/**
	 * About page name.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $about_page = '';

	/**
	 * Addons page name.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $addons_page = '';

	/**
	 * Payments page name.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $payments_page = '';

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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'msg' => esc_html__( 'You are not allowed to make these changes.', 'members' )
			) );
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
			unset( $this->views[ $name ] );
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

		// Create the settings pages.
		$this->admin_pages = array( 'toplevel_page_members', 'members_page_roles' );
		$this->settings_page = add_submenu_page( 'members', esc_html_x( 'Settings', 'admin screen', 'members' ), esc_html_x( 'Settings', 'admin screen', 'members' ), apply_filters( 'members_settings_capability', 'manage_options' ), 'members-settings', array( $this, 'settings_page' ) );
		$this->admin_pages[] = $this->settings_page;
		$this->addons_page = add_submenu_page( 'members', esc_html_x( 'Add-Ons', 'admin screen', 'members' ), _x( '<span style="color: #8CBD5A;">Add-Ons</span>', 'admin screen', 'members' ), apply_filters( 'members_settings_capability', 'manage_options' ), 'members-settings&view=add-ons', array( $this, 'settings_page' ) );
		$this->admin_pages[] = $this->addons_page;
		if ( ! members_is_memberpress_active() ) { // MemberPress not active
			$this->payments_page = add_submenu_page( 'members', esc_html_x( 'Payments', 'admin screen', 'members' ), esc_html_x( 'Payments', 'admin screen', 'members' ), apply_filters( 'members_settings_capability', 'manage_options' ), 'members-payments', array( $this, 'payments_page' ) );
			$this->admin_pages[] = $this->payments_page;
		}
		$this->about_page = add_submenu_page( 'members', esc_html_x( 'About Us', 'admin screen', 'members' ), esc_html_x( 'About Us', 'admin screen', 'members' ), apply_filters( 'members_settings_capability', 'manage_options' ), 'members-about', array( $this, 'about_page' ) );
		$this->admin_pages[] = $this->about_page;

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

		if ( ! members_is_admin_page() )
			return;

		$view = $this->get_view( members_get_current_settings_view() );

		wp_enqueue_style( 'members-admin' );

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

		$products = array(
			array(
				'slug'        => 'memberpress',
				'name'        => 'MemberPress',
				'icon'        => 'mp-icon-RGB.jpg',
				'description' => 'Build astounding WordPress membership sites, accept payments securely, and control who sees your content — without the difficult setup.',
				'plugin_file' => 'memberpress/memberpress.php',
				'is_active'   => members_is_memberpress_active(),
				'url_title'   => 'https://memberpress.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=memberpress_icon_title',
				'url_learn'   => 'https://memberpress.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=memberpress_learn_more',
				'url_install' => 'https://memberpress.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=memberpress_install',
			),
			array(
				'slug'        => 'pretty-links',
				'name'        => 'Pretty Links',
				'icon'        => 'pl-icon-RGB.jpg',
				'description' => 'Monetize your content effortlessly. Pretty Links helps you unlock more affiliate revenue from the content you already have.',
				'plugin_file' => 'pretty-link/pretty-link.php',
				'is_active'   => is_plugin_active( 'pretty-link/pretty-link.php' ),
				'url_title'   => 'https://prettylinks.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=prettylinks_icon_title',
				'url_learn'   => 'https://prettylinks.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=prettylinks_learn_more',
				'url_install' => 'https://prettylinks.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=prettylinks_install',
			),
			array(
				'slug'        => 'easy-affiliate',
				'name'        => 'Easy Affiliate',
				'icon'        => 'bee.png',
				'description' => 'A full-featured affiliate program plugin for WordPress. Launch your own program to drive traffic, attention, and sales.',
				'plugin_file' => 'affiliate-royale/affiliate-royale.php',
				'is_active'   => is_plugin_active( 'affiliate-royale/affiliate-royale.php' ),
				'url_title'   => 'https://easyaffiliate.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=easyaffiliate_icon_title',
				'url_learn'   => 'https://easyaffiliate.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=easyaffiliate_learn_more',
				'url_install' => 'https://easyaffiliate.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=about_us&utm_content=easyaffiliate_install',
			),
		);

		$plugin_uri = members_plugin()->uri;

		?>

		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

		<div class="wrap members-about">
			<h1 class="screen-reader-text"><?php echo esc_html_x( 'About Us', 'admin screen', 'members' ); ?></h1>

			<article class="members-about__hero">
				<header class="members-about__hero-head">
					<span class="members-about__eyebrow">About · <?php echo esc_html( date( 'Y' ) ); ?></span>
					<h2 class="members-about__title">
						Hello &amp; <em>welcome</em>
						<br>to Members<span class="members-about__title-dot">.</span>
					</h2>
				</header>

				<div class="members-about__body">
					<p class="members-about__lead">
						The simplest WordPress membership and role editor plugin — built by the team at <a href="https://memberpress.com/?utm_source=members_plugin&amp;utm_medium=link&amp;utm_campaign=about_us&amp;utm_content=link_1" target="_blank" rel="noopener">MemberPress</a>.
					</p>
					<p>Over the years we found that most WordPress membership plugins were bloated, buggy, slow, hard to use — and expensive. So we started with a simple goal: build a plugin that's both easy <em>and</em> powerful.</p>
					<p>Our goal is to take the pain out of creating membership sites and make it easy.</p>
					<p>Members is brought to you by the same team behind <a href="https://memberpress.com/?utm_source=members_plugin&amp;utm_medium=link&amp;utm_campaign=about_us&amp;utm_content=link_2" target="_blank" rel="noopener">MemberPress</a>, <a href="https://easyaffiliate.com/?utm_source=members_plugin&amp;utm_medium=link&amp;utm_campaign=about_us&amp;utm_content=link_3" target="_blank" rel="noopener">Easy Affiliate</a>, and <a href="https://prettylinks.com/?utm_source=members_plugin&amp;utm_medium=link&amp;utm_campaign=about_us&amp;utm_content=link_4" target="_blank" rel="noopener">Pretty Links</a>.</p>
					<p>So — you can see we know a thing or two about building products that customers love.</p>
				</div>

				<aside class="members-about__mark">
					<a href="https://memberpress.com/?utm_source=members_plugin&amp;utm_medium=banner&amp;utm_campaign=about_us&amp;utm_content=memberpress_logo_large" target="_blank" rel="noopener">
						<img src="<?php echo esc_url( $plugin_uri . 'img/mp-logo-stacked-RGB.jpg' ); ?>" alt="MemberPress">
					</a>
				</aside>
			</article>

			<section class="members-about__products" aria-label="More from our team">
				<header class="members-about__products-head">
					<h3>From our team</h3>
				</header>

				<div class="members-about__grid">
					<?php foreach ( $products as $p ) :
						$is_installed = array_key_exists( $p['plugin_file'], $installed_plugins );
						if ( $p['is_active'] ) {
							$status_label = 'Active';
							$status_class = 'is-active';
							$cta_label    = 'Learn More';
							$cta_href     = $p['url_learn'];
							$cta_class    = 'is-secondary';
							$cta_target   = '_blank';
						} elseif ( $is_installed ) {
							$status_label = 'Inactive';
							$status_class = 'is-inactive';
							$cta_label    = 'Activate';
							$cta_href     = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $p['plugin_file'] ), 'activate-plugin_' . $p['plugin_file'] );
							$cta_class    = 'is-secondary';
							$cta_target   = '_self';
						} else {
							$status_label = 'Not installed';
							$status_class = 'is-missing';
							$cta_label    = 'Install Plugin';
							$cta_href     = $p['url_install'];
							$cta_class    = 'is-primary';
							$cta_target   = '_blank';
						}
						?>
						<article class="members-about__card" data-slug="<?php echo esc_attr( $p['slug'] ); ?>">
							<header class="members-about__card-head">
								<span class="members-about__card-icon">
									<img src="<?php echo esc_url( $plugin_uri . 'img/' . $p['icon'] ); ?>" alt="">
								</span>
								<h4 class="members-about__card-title">
									<a href="<?php echo esc_url( $p['url_title'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $p['name'] ); ?></a>
								</h4>
							</header>
							<p class="members-about__card-desc"><?php echo esc_html( $p['description'] ); ?></p>
							<footer class="members-about__card-foot">
								<span class="members-about__status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
								<a class="members-about__cta <?php echo esc_attr( $cta_class ); ?>" href="<?php echo esc_url( $cta_href ); ?>"<?php echo $cta_target === '_blank' ? ' target="_blank" rel="noopener"' : ''; ?>>
									<?php echo esc_html( $cta_label ); ?>
									<svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M2 8L8 2M8 2H3.5M8 2V6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
								</a>
							</footer>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
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
