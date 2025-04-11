<?php

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class for Members Subscriptions
 */
class Plugin {

    /**
     * Plugin version
     */
    const VERSION = '1.0.2';

    /**
     * Plugin instance
     * 
     * @var self
     */
    private static $instance = null;

    /**
     * Get the plugin instance (singleton)
     *
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize the plugin components
     */
    private function init() {
        // Register post types
        add_action('init', [$this, 'register_post_types']);
        
        // Register hooks
        add_action('admin_menu', [$this, 'register_admin_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Initialize database tables and run migrations
        add_action('plugins_loaded', [$this, 'initialize_database']);
        
        // Handle admin actions like database updates
        add_action('admin_init', [$this, 'handle_admin_actions']);
        
        // Initialize gateways
        add_action('init', [$this, 'initialize_gateways']);
        
        // Initialize email system
        add_action('init', [$this, 'initialize_emails']);
        
        // Add shortcodes
        $this->register_shortcodes();
        
        // Load required files
        $this->load_required_files();
    }
    
    /**
     * Load required files
     */
    private function load_required_files() {
        // Load logging functions
        require_once __DIR__ . '/functions-logging.php';
        
        // Load email functions
        require_once __DIR__ . '/functions-emails.php';
        
        // Load renewal functions
        require_once __DIR__ . '/functions-renewals.php';
        
        // Load exception classes
        require_once __DIR__ . '/exceptions/class-members-exception.php';
        require_once __DIR__ . '/exceptions/class-gateway-exception.php';
        require_once __DIR__ . '/exceptions/class-api-exception.php';
        require_once __DIR__ . '/exceptions/class-validation-exception.php';
        require_once __DIR__ . '/exceptions/class-db-exception.php';
    }
    
    /**
     * Initialize database and run migrations
     */
    public function initialize_database() {
        // Include migration manager
        require_once __DIR__ . '/migrations/class-migration.php';
        require_once __DIR__ . '/migrations/class-migration-manager.php';
        require_once __DIR__ . '/migrations/class-migration-1-0-0.php';
        require_once __DIR__ . '/migrations/class-migration-1-0-1.php';
        require_once __DIR__ . '/migrations/class-migration-1-0-2.php';
        
        // Run migrations
        $migration_manager = new Migrations\Migration_Manager();
        $current_version = $migration_manager->get_current_version();
        $latest_version = $migration_manager->get_latest_version();
        
        if (version_compare($current_version, $latest_version, '<')) {
            $results = $migration_manager->migrate();
            
            // Log migration results
            foreach ($results as $result) {
                $log_level = $result['success'] ? 'info' : 'error';
                log_message($result['message'], $log_level);
            }
        }
    }
    
    /**
     * Initialize email system
     */
    public function initialize_emails() {
        // Include base email classes
        require_once __DIR__ . '/emails/class-email.php';
        require_once __DIR__ . '/emails/class-subscription-email.php';
        require_once __DIR__ . '/emails/class-transaction-email.php';
        
        // Include specific email classes
        require_once __DIR__ . '/emails/class-new-subscription-email.php';
        require_once __DIR__ . '/emails/class-cancelled-subscription-email.php';
        require_once __DIR__ . '/emails/class-payment-receipt-email.php';
        require_once __DIR__ . '/emails/class-renewal-reminder-email.php';
        require_once __DIR__ . '/emails/class-renewal-receipt-email.php';
        
        // Include email manager
        require_once __DIR__ . '/emails/class-email-manager.php';
    }
    
    /**
     * Handle admin actions like manual database updates
     */
    public function handle_admin_actions() {
        // Check if we need to run a manual database update
        if (isset($_GET['page']) && $_GET['page'] === 'members-subscriptions' && 
            isset($_GET['action']) && $_GET['action'] === 'update_database') {
            
            // Verify user can manage options
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to perform this action.', 'members'));
            }
            
            // Make sure migration classes are loaded
            require_once __DIR__ . '/migrations/class-migration.php';
            require_once __DIR__ . '/migrations/class-migration-manager.php';
            require_once __DIR__ . '/migrations/class-migration-1-0-0.php';
            require_once __DIR__ . '/migrations/class-migration-1-0-1.php';
            require_once __DIR__ . '/migrations/class-migration-1-0-2.php';
            
            // Run migrations
            $migration_manager = new Migrations\Migration_Manager();
            $results = $migration_manager->migrate();
            
            // Set success/error message
            $success = true;
            foreach ($results as $result) {
                if (!$result['success']) {
                    $success = false;
                    break;
                }
            }
            
            // Redirect back with status
            $redirect_url = admin_url('admin.php?page=members-subscriptions');
            $redirect_url = add_query_arg('db_update', $success ? 'success' : 'error', $redirect_url);
            
            // If successful, remove the notification
            if ($success) {
                // Include the Admin_Notifications class if it's not already loaded
                if (!class_exists('\\Members\\Subscriptions\\Admin_Notifications')) {
                    require_once __DIR__ . '/class-admin-notifications.php';
                }
                
                Admin_Notifications::remove_notification('database_update');
            }
            
            wp_redirect($redirect_url);
            exit;
        }
        
        // Display update success/error message
        if (isset($_GET['page']) && $_GET['page'] === 'members-subscriptions' && isset($_GET['db_update'])) {
            if ($_GET['db_update'] === 'success') {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                        __('Database update completed successfully. You can now add and edit subscription products.', 'members') . 
                        '</p></div>';
                });
            } elseif ($_GET['db_update'] === 'error') {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>' . 
                        __('Database update failed. Please contact support for assistance.', 'members') . 
                        '</p></div>';
                });
            }
        }
    }

    /**
     * Register necessary post types
     */
    public function register_post_types() {
        // Register Subscription Product post type
        register_post_type('members_product', [
            'labels' => [
                'name'               => __('Subscription Products', 'members'),
                'singular_name'      => __('Subscription Product', 'members'),
                'add_new'            => __('Add New Product', 'members'),
                'add_new_item'       => __('Add New Subscription Product', 'members'),
                'edit_item'          => __('Edit Subscription Product', 'members'),
                'new_item'           => __('New Subscription Product', 'members'),
                'view_item'          => __('View Subscription Product', 'members'),
                'search_items'       => __('Search Subscription Products', 'members'),
                'not_found'          => __('No subscription products found', 'members'),
                'not_found_in_trash' => __('No subscription products found in Trash', 'members'),
                'menu_name'          => __('Products', 'members'), // Shorter name for menu
                'all_items'          => __('All Products', 'members'),
            ],
            'public'              => true, // Changed to true to allow frontend viewing
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => false, // We'll add it as a submenu in register_admin_menu
            'capability_type'     => ['members_product', 'members_products'], // Correct singular/plural format
            'capabilities'        => [
                'edit_post'              => 'manage_subscription_products',
                'read_post'              => 'manage_subscription_products',
                'delete_post'            => 'manage_subscription_products',
                'edit_posts'             => 'manage_subscription_products',
                'edit_others_posts'      => 'manage_subscription_products',
                'delete_posts'           => 'manage_subscription_products', // Added this capability
                'publish_posts'          => 'manage_subscription_products',
                'read_private_posts'     => 'manage_subscription_products',
            ],
            'map_meta_cap'        => true, // This is correct, but WordPress will do specific checks
            'hierarchical'        => false,
            'rewrite'             => [
                'slug' => 'membership-products',
                'with_front' => false
            ],
            'menu_position'       => null,
            'supports'            => ['title', 'editor', 'thumbnail'],
            'has_archive'         => false,
            'show_in_rest'        => true,
            'menu_icon'           => 'dashicons-cart',
        ]);
        
        // Flush rewrite rules only on activation, not on every page load
        // We'll handle this in the Activator class
    }

    /**
     * Register admin menu items
     */
    public function register_admin_menu() {
        // Subscription Products page
        add_submenu_page(
            'members',
            __('Subscription Products', 'members'),
            __('Subscription Products', 'members'),
            'manage_subscription_products',
            'edit.php?post_type=members_product',
            null
        );
        
        // Subscriptions page
        add_submenu_page(
            'members',
            __('Subscriptions', 'members'),
            __('Subscriptions', 'members'),
            'view_subscriptions',
            'members-subscriptions',
            [$this, 'render_subscriptions_page']
        );
        
        // Transactions page
        add_submenu_page(
            'members',
            __('Transactions', 'members'),
            __('Transactions', 'members'),
            'view_transactions',
            'members-transactions',
            [$this, 'render_transactions_page']
        );
        
        // Payment Gateways page
        add_submenu_page(
            'members',
            __('Payment Gateways', 'members'),
            __('Payment Gateways', 'members'),
            'manage_payment_gateways',
            'members-gateways',
            [$this, 'render_gateways_page']
        );
    }

    /**
     * Render the Subscriptions admin page
     */
    public function render_subscriptions_page() {
        // Check capabilities before rendering
        if (!current_user_can('view_subscriptions')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'members'));
        }
        
        // Include the subscription list table class
        require_once __DIR__ . '/admin/class-subscriptions-list-table.php';
        
        // Create an instance of the subscriptions list table
        $subscriptions_table = new admin\Subscriptions_List_Table();
        $subscriptions_table->prepare_items();
        
        // Output the subscriptions page
        include __DIR__ . '/admin/views/subscriptions-page.php';
    }

    /**
     * Render the Transactions admin page
     */
    public function render_transactions_page() {
        // Check capabilities before rendering
        if (!current_user_can('view_transactions')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'members'));
        }
        
        // Include the transaction list table class
        require_once __DIR__ . '/admin/class-transactions-list-table.php';
        
        // Create an instance of the transactions list table
        $transactions_table = new admin\Transactions_List_Table();
        $transactions_table->prepare_items();
        
        // Output the transactions page
        include __DIR__ . '/admin/views/transactions-page.php';
    }

    /**
     * Render the Gateways admin page
     */
    public function render_gateways_page() {
        // Check capabilities before rendering
        if (!current_user_can('manage_payment_gateways')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'members'));
        }
        
        // Output the gateways page
        include __DIR__ . '/admin/views/gateways-page.php';
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Determine if this is the product edit or new screen
        $is_product_page = (
            get_current_screen() && 
            get_current_screen()->post_type === 'members_product' && 
            (get_current_screen()->base === 'post' || get_current_screen()->base === 'post-new')
        );
        
        // Common Members plugin pages
        $is_members_page = (
            strpos($hook, 'members-subscriptions') !== false || 
            strpos($hook, 'members-transactions') !== false || 
            strpos($hook, 'members-gateways') !== false
        );
        
        // Only enqueue on our plugin pages
        if (!$is_members_page && !$is_product_page) {
            return;
        }
        
        // Enqueue styles for admin
        wp_enqueue_style(
            'members-subscriptions-admin',
            plugin_dir_url(dirname(__DIR__)) . 'css/admin/admin-style.css',
            [],
            self::VERSION
        );
        
        // Enqueue specific styles for product administration
        if ($is_product_page) {
            wp_enqueue_style(
                'members-products-admin',
                plugin_dir_url(dirname(__DIR__)) . 'css/admin/products-style.css',
                [],
                self::VERSION
            );
        }
        
        wp_enqueue_script(
            'members-subscriptions-admin',
            plugin_dir_url(dirname(__DIR__)) . 'js/admin.js',
            ['jquery'],
            self::VERSION,
            true
        );
        
        // Localize script with necessary data
        wp_localize_script(
            'members-subscriptions-admin',
            'MembersSubscriptionsAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('members-subscriptions-admin-nonce'),
                'i18n' => [
                    'confirmCancel' => __('Are you sure you want to cancel this subscription?', 'members'),
                    'confirmDelete' => __('Are you sure you want to delete this item? This action cannot be undone.', 'members'),
                    'confirmRefund' => __('Are you sure you want to refund this transaction?', 'members'),
                ]
            ]
        );
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        // Enqueue styles for front-end
        wp_enqueue_style(
            'members-subscriptions-frontend',
            plugin_dir_url(dirname(__DIR__)) . 'css/frontend/frontend-style.css',
            [],
            self::VERSION
        );
        
        wp_register_script(
            'members-subscriptions-frontend',
            plugin_dir_url(dirname(__DIR__)) . 'js/frontend.js',
            ['jquery'],
            self::VERSION,
            true
        );
        
        // Localize script with necessary data
        wp_localize_script(
            'members-subscriptions-frontend',
            'MembersSubscriptions',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('members-subscriptions-nonce'),
                'i18n' => [
                    'processing' => __('Processing, please wait...', 'members'),
                    'successSubscription' => __('Your subscription has been processed successfully!', 'members'),
                    'errorPayment' => __('There was an error processing your payment. Please try again.', 'members'),
                    'cardError' => __('Card information is invalid. Please check and try again.', 'members'),
                    'requiredFields' => __('Please fill in all required fields.', 'members'),
                    'confirmCancel' => __('Are you sure you want to cancel this subscription? This action cannot be undone.', 'members'),
                ]
            ]
        );
        
        wp_enqueue_script('members-subscriptions-frontend');
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Register subscription endpoints
        register_rest_route('members/v1', '/subscriptions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_subscriptions'],
            'permission_callback' => function() {
                return current_user_can('view_subscriptions');
            }
        ]);
        
        // Register transaction endpoints
        register_rest_route('members/v1', '/transactions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_transactions'],
            'permission_callback' => function() {
                return current_user_can('view_transactions');
            }
        ]);
    }

    /**
     * REST API callback for getting subscriptions
     */
    public function get_subscriptions($request) {
        // Implement subscription retrieval logic
        return new \WP_REST_Response(['success' => true], 200);
    }

    /**
     * REST API callback for getting transactions
     */
    public function get_transactions($request) {
        // Implement transaction retrieval logic
        return new \WP_REST_Response(['success' => true], 200);
    }

    /**
     * Initialize payment gateways
     */
    public function initialize_gateways() {
        // Load and initialize gateways
        require_once __DIR__ . '/gateways/class-gateway-manager.php';
        gateways\Gateway_Manager::get_instance();
    }

    /**
     * Register shortcodes
     */
    private function register_shortcodes() {
        add_shortcode('members_subscription_form', [$this, 'subscription_form_shortcode']);
        add_shortcode('members_account', [$this, 'account_shortcode']);
    }

    /**
     * Shortcode callback for subscription form
     */
    public function subscription_form_shortcode($atts) {
        $atts = shortcode_atts([
            'product_id' => 0,
        ], $atts, 'members_subscription_form');
        
        ob_start();
        include __DIR__ . '/views/subscription-form.php';
        return ob_get_clean();
    }

    /**
     * Shortcode callback for member account page
     */
    public function account_shortcode($atts) {
        $atts = shortcode_atts([], $atts, 'members_account');
        
        ob_start();
        include __DIR__ . '/views/account.php';
        return ob_get_clean();
    }
}