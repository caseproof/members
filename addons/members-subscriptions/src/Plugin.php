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
        
        // Check if we need to flush rewrite rules
        add_action('admin_init', [$this, 'maybe_flush_rewrite_rules']);
        
        // Add hook to verify database tables when needed
        add_action('admin_notices', [$this, 'check_database_tables']);
    }
    
    /**
     * Check if database tables exist and show notice if missing
     */
    public function check_database_tables() {
        // Only run on our plugin's admin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'members') === false) {
            return;
        }
        
        // Load DB functions if not already loaded
        if (!function_exists('\\Members\\Subscriptions\\verify_database_tables')) {
            require_once __DIR__ . '/functions-db.php';
        }
        
        global $wpdb;
        
        // Tables to check
        $required_tables = [
            \Members\Subscriptions\get_subscriptions_table_name(),
            \Members\Subscriptions\get_transactions_table_name(),
            \Members\Subscriptions\get_transactions_meta_table_name(),
            \Members\Subscriptions\get_products_meta_table_name(),
        ];
        
        $missing_tables = [];
        
        // Check if each table exists
        foreach ($required_tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            
            if (!$table_exists) {
                $missing_tables[] = $table;
            }
        }
        
        // If tables are missing, attempt to create them automatically
        if (!empty($missing_tables)) {
            $tables_created = \Members\Subscriptions\verify_database_tables();
            
            // If tables were created successfully, don't show notice
            if ($tables_created) {
                return;
            }
            
            // Show admin notice if tables couldn't be created
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('Members Subscriptions: Database tables are missing', 'members'); ?></strong>
                </p>
                <p>
                    <?php _e('The plugin requires database tables to function properly. Automatic creation failed. Please deactivate and reactivate the plugin to trigger database setup.', 'members'); ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Conditionally flush rewrite rules
     * Only does this when we detect our version has changed
     */
    public function maybe_flush_rewrite_rules() {
        $version_option = 'members_subscriptions_post_types_version';
        $current_version = get_option($version_option, '0.0.0');
        
        // If this is a new version, flush rewrite rules
        if (version_compare($current_version, self::VERSION, '<')) {
            // Get current global option
            $flush_option = get_option('members_subscriptions_needs_flush', 0);
            
            // Set to current time if not already set
            if (!$flush_option) {
                update_option('members_subscriptions_needs_flush', time());
            }
            
            // Update the version
            update_option($version_option, self::VERSION);
        }
        
        // Check if we need to flush rules
        $needs_flush = get_option('members_subscriptions_needs_flush', 0);
        
        if ($needs_flush) {
            // Only flush once per 5 minutes to avoid performance issues
            if ((time() - $needs_flush) > 300) {
                flush_rewrite_rules();
                delete_option('members_subscriptions_needs_flush');
            }
        }
    }
    
    /**
     * Load required files
     */
    private function load_required_files() {
        // Load logging functions
        require_once __DIR__ . '/functions-logging.php';
        
        // Load database functions
        require_once __DIR__ . '/functions-db.php';
        
        // Load subscription functions
        require_once __DIR__ . '/functions-subscriptions.php';
        
        // Load email functions
        require_once __DIR__ . '/functions-emails.php';
        
        // Load renewal functions
        require_once __DIR__ . '/functions-renewals.php';
        
        // Load product functions
        require_once __DIR__ . '/functions-products.php';
        
        // Load template loader
        require_once __DIR__ . '/class-template-loader.php';
        
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
        // Make sure required classes are loaded first
        if (file_exists(__DIR__ . '/class-logger.php')) {
            require_once __DIR__ . '/class-logger.php';
        }
        
        if (file_exists(__DIR__ . '/functions-logging.php')) {
            require_once __DIR__ . '/functions-logging.php';
        }
        
        // Include migration manager and migrations
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
            
            // Log migration results - only if logging functions are available
            if (function_exists('\\Members\\Subscriptions\\log_message')) {
                foreach ($results as $result) {
                    $log_level = $result['success'] ? 'info' : 'error';
                    log_message($result['message'], $log_level);
                }
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
            
            // Make sure required classes are loaded
            require_once __DIR__ . '/class-logger.php'; // Load Logger class first
            require_once __DIR__ . '/functions-logging.php'; // Load logging functions
            
            // Load migration classes
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
            'public'              => true, // Public to allow front-end viewing
            'publicly_queryable'  => true, // Allow querying
            'exclude_from_search' => false, // Include in searches
            'show_ui'             => true, // Admin UI
            'show_in_nav_menus'   => true, // Allow adding to navigation menus
            'show_in_menu'        => false, // We'll add it as a submenu in register_admin_menu
            'show_in_admin_bar'   => true, // Show in admin bar
            
            // Define a custom capability type
            'capability_type'     => 'post', // Using the standard post capabilities to avoid warnings
            // Override specific capabilities without using map_meta_cap
            'capabilities'        => [
                'publish_posts'          => 'manage_subscription_products',
                'edit_posts'             => 'manage_subscription_products',
                'edit_others_posts'      => 'manage_subscription_products',
                'delete_posts'           => 'manage_subscription_products',
                'delete_others_posts'    => 'manage_subscription_products',
                'read_private_posts'     => 'manage_subscription_products',
                'edit_post'              => 'manage_subscription_products',
                'delete_post'            => 'manage_subscription_products',
                'read_post'              => 'manage_subscription_products',
            ],
            'map_meta_cap'        => false, // Disable meta cap mapping for this post type
            'hierarchical'        => false,
            'rewrite'             => [
                'slug' => 'membership-products',
                'with_front' => false,
                'pages' => true,
                'feeds' => true,
                'ep_mask' => EP_PERMALINK | EP_PAGES, // Ensure endpoints work
            ],
            'menu_position'       => null,
            'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'comments'],
            'has_archive'         => true, // Create an archive page for products
            'show_in_rest'        => true, // Support REST API
            'menu_icon'           => 'dashicons-cart',
            'query_var'           => true, // Allow querying with 'members_product' var
            // Don't use inline meta box registration - we handle this separately in functions-products.php
            'register_meta_box_cb' => null,
        ]);
        
        // Flush rewrite rules only on activation, not on every page load
        // We'll handle this in the Activator class
    }

    /**
     * Register admin menu items
     */
    public function register_admin_menu() {
        // Subscription Products page using custom list table
        add_submenu_page(
            'members',
            __('Subscription Products', 'members'),
            __('Subscription Products', 'members'),
            'manage_subscription_products',
            'members-products',
            [$this, 'render_products_page']
        );
        
        // Also keep the default WP editor product page as a separate menu item
        add_submenu_page(
            'members',
            __('Add/Edit Products', 'members'),
            __('Add/Edit Products', 'members'),
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
     * Render the Products admin page
     */
    public function render_products_page() {
        // Check capabilities before rendering
        if (!current_user_can('manage_subscription_products')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'members'));
        }
        
        // Include the products list table class
        require_once __DIR__ . '/admin/class-products-list-table.php';
        
        // Output the products page (the table is instantiated in the view)
        include __DIR__ . '/admin/views/products-page.php';
    }

    /**
     * Render the Subscriptions admin page
     */
    public function render_subscriptions_page() {
        // Check capabilities before rendering
        if (!current_user_can('view_subscriptions')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'members'));
        }
        
        // Verify database tables exist - create if needed
        \Members\Subscriptions\verify_database_tables();
        
        // Check for export request
        if (isset($_REQUEST['export']) && $_REQUEST['export'] === 'csv') {
            $this->export_subscriptions_csv();
            exit;
        }
        
        // Include the subscription list table class
        require_once __DIR__ . '/admin/class-subscriptions-list-table.php';
        
        // Create an instance of the subscriptions list table
        $subscriptions_table = new admin\Subscriptions_List_Table();
        
        // Handle reactivate action
        if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'reactivate' && 
            isset($_REQUEST['subscription']) && isset($_REQUEST['_wpnonce'])) {
            
            $subscription_id = intval($_REQUEST['subscription']);
            
            if (wp_verify_nonce($_REQUEST['_wpnonce'], 'members_reactivate_subscription')) {
                $this->reactivate_subscription($subscription_id);
            }
        }
        
        // Handle renew action
        if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'renew' && 
            isset($_REQUEST['subscription']) && isset($_REQUEST['_wpnonce'])) {
            
            $subscription_id = intval($_REQUEST['subscription']);
            
            if (wp_verify_nonce($_REQUEST['_wpnonce'], 'members_renew_subscription')) {
                $this->renew_subscription($subscription_id);
            }
        }
        
        $subscriptions_table->prepare_items();
        
        // Output the subscriptions page
        include __DIR__ . '/admin/views/subscriptions-page.php';
    }
    
    /**
     * Reactivate subscription
     *
     * @param int $subscription_id
     * @return void
     */
    private function reactivate_subscription($subscription_id) {
        // Get subscription
        $subscription = \Members\Subscriptions\get_subscription($subscription_id);
        
        if (!$subscription) {
            wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=members-subscriptions')));
            exit;
        }
        
        // Check if subscription is cancelled or expired
        if (!in_array($subscription->status, ['cancelled', 'expired'])) {
            wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=members-subscriptions')));
            exit;
        }
        
        // Calculate new expiration date for expired subscriptions
        $data = [
            'status' => 'active',
        ];
        
        // If expired, set a new expiration date
        if ($subscription->status === 'expired') {
            $data['expires_at'] = \Members\Subscriptions\calculate_subscription_expiration(
                $subscription->period, 
                $subscription->period_type, 
                current_time('mysql')
            );
        }
        
        // Update subscription
        $result = \Members\Subscriptions\update_subscription($subscription_id, $data);
        
        if ($result) {
            // Add membership roles to user
            \Members\Subscriptions\apply_membership_role($subscription->user_id, $subscription->product_id);
            
            // Log event
            \Members\Subscriptions\log_event('subscription_reactivated', [
                'subscription_id' => $subscription_id,
                'user_id' => $subscription->user_id,
                'old_status' => $subscription->status,
                'new_status' => 'active',
            ]);
            
            // Trigger action
            do_action('members_subscription_reactivated', $subscription_id);
            
            // Redirect with success message
            wp_redirect(add_query_arg('message', 'reactivated', admin_url('admin.php?page=members-subscriptions')));
            exit;
        } else {
            // Redirect with error message
            wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=members-subscriptions')));
            exit;
        }
    }
    
    /**
     * Process subscription renewal
     *
     * @param int $subscription_id
     * @return void
     */
    private function renew_subscription($subscription_id) {
        // Get subscription
        $subscription = \Members\Subscriptions\get_subscription($subscription_id);
        
        if (!$subscription) {
            wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=members-subscriptions')));
            exit;
        }
        
        // Check if subscription is active
        if ($subscription->status !== 'active') {
            wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=members-subscriptions')));
            exit;
        }
        
        // Process renewal
        $result = \Members\Subscriptions\process_subscription_renewal($subscription_id);
        
        if ($result && $result['success']) {
            // Redirect with success message
            wp_redirect(add_query_arg('message', 'renewed', admin_url('admin.php?page=members-subscriptions')));
            exit;
        } else {
            // Redirect with error message
            wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=members-subscriptions')));
            exit;
        }
    }
    
    /**
     * Export subscriptions to CSV
     *
     * @return void
     */
    private function export_subscriptions_csv() {
        global $wpdb;
        
        // Security check
        if (!current_user_can('view_subscriptions')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'members'));
        }
        
        // Build query args based on filters
        $args = [];
        
        // User filter
        if (!empty($_REQUEST['user_id'])) {
            $args['user_id'] = intval($_REQUEST['user_id']);
        }
        
        // Product filter
        if (!empty($_REQUEST['product_id'])) {
            $args['product_id'] = intval($_REQUEST['product_id']);
        }
        
        // Status filter
        if (!empty($_REQUEST['status'])) {
            $args['status'] = sanitize_text_field($_REQUEST['status']);
        }
        
        // Gateway filter
        if (!empty($_REQUEST['gateway'])) {
            $args['gateway'] = sanitize_text_field($_REQUEST['gateway']);
        }
        
        // Date range filter for created_at
        $where = '';
        if (!empty($_REQUEST['date_from']) || !empty($_REQUEST['date_to'])) {
            $table_name = \Members\Subscriptions\get_subscriptions_table_name();
            
            if (!empty($_REQUEST['date_from'])) {
                $date_from = sanitize_text_field($_REQUEST['date_from']);
                $where .= $wpdb->prepare(" AND created_at >= %s", $date_from . ' 00:00:00');
            }
            
            if (!empty($_REQUEST['date_to'])) {
                $date_to = sanitize_text_field($_REQUEST['date_to']);
                $where .= $wpdb->prepare(" AND created_at <= %s", $date_to . ' 23:59:59');
            }
        }
        
        // Get subscriptions
        if (!empty($where)) {
            $table_name = \Members\Subscriptions\get_subscriptions_table_name();
            $sql = "SELECT * FROM $table_name WHERE 1=1";
            
            // Add where conditions from args
            if (!empty($args['user_id'])) {
                $sql .= $wpdb->prepare(" AND user_id = %d", $args['user_id']);
            }
            
            if (!empty($args['product_id'])) {
                $sql .= $wpdb->prepare(" AND product_id = %d", $args['product_id']);
            }
            
            if (!empty($args['status'])) {
                $sql .= $wpdb->prepare(" AND status = %s", $args['status']);
            }
            
            if (!empty($args['gateway'])) {
                $sql .= $wpdb->prepare(" AND gateway = %s", $args['gateway']);
            }
            
            // Add where clause for date range
            $sql .= $where;
            
            // Order by created_at desc
            $sql .= " ORDER BY created_at DESC";
            
            $subscriptions = $wpdb->get_results($sql);
        } else {
            $subscriptions = \Members\Subscriptions\get_subscriptions($args);
        }
        
        // Check if expiring filter is set
        if (isset($_REQUEST['expiring']) && $_REQUEST['expiring'] === 'soon') {
            $filtered_subscriptions = [];
            $now = current_time('timestamp');
            $thirty_days_from_now = strtotime('+30 days', $now);
            
            foreach ($subscriptions as $subscription) {
                if ($subscription->status === 'active' && 
                    !empty($subscription->expires_at) && 
                    $subscription->expires_at !== '0000-00-00 00:00:00') {
                    
                    $expires_timestamp = strtotime($subscription->expires_at);
                    
                    if ($expires_timestamp > $now && $expires_timestamp <= $thirty_days_from_now) {
                        $filtered_subscriptions[] = $subscription;
                    }
                }
            }
            
            $subscriptions = $filtered_subscriptions;
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="members-subscriptions-' . date('Y-m-d') . '.csv"');
        
        // Create output handle
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM
        fputs($output, "\xEF\xBB\xBF");
        
        // Define CSV headers
        $headers = [
            'ID',
            'User ID',
            'User Email',
            'User Name',
            'Product ID',
            'Product Name',
            'Status',
            'Gateway',
            'Subscription ID',
            'Price',
            'Tax Amount',
            'Total',
            'Period',
            'Created Date',
            'Expiry Date',
            'Renewal Count',
        ];
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write data rows
        foreach ($subscriptions as $subscription) {
            $user = get_userdata($subscription->user_id);
            $product = get_post($subscription->product_id);
            
            $row = [
                $subscription->id,
                $subscription->user_id,
                $user ? $user->user_email : __('User deleted', 'members'),
                $user ? $user->display_name : __('User deleted', 'members'),
                $subscription->product_id,
                $product ? $product->post_title : __('Product deleted', 'members'),
                $subscription->status,
                $subscription->gateway,
                $subscription->subscr_id,
                $subscription->price,
                $subscription->tax_amount,
                $subscription->total,
                !empty($subscription->period) ? $subscription->period . ' ' . $subscription->period_type : 'N/A',
                $subscription->created_at,
                !empty($subscription->expires_at) && $subscription->expires_at !== '0000-00-00 00:00:00' ? $subscription->expires_at : 'Never',
                $subscription->renewal_count,
            ];
            
            fputcsv($output, $row);
        }
        
        // Close output and exit
        fclose($output);
        exit;
    }

    /**
     * Render the Transactions admin page
     */
    public function render_transactions_page() {
        // Check capabilities before rendering
        if (!current_user_can('view_transactions')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'members'));
        }
        
        // Verify database tables exist - create if needed
        \Members\Subscriptions\verify_database_tables();
        
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
        
        // Verify database tables exist - create if needed
        \Members\Subscriptions\verify_database_tables();
        
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
        if ($is_product_page || strpos($hook, 'members-products') !== false) {
            wp_enqueue_style(
                'members-products-admin',
                plugin_dir_url(dirname(__DIR__)) . 'css/admin/products-style.css',
                [],
                self::VERSION
            );
            
            wp_enqueue_script(
                'members-products-admin',
                plugin_dir_url(dirname(__DIR__)) . 'js/admin-products.js',
                ['jquery'],
                self::VERSION,
                true
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
        
        // Debug log
        error_log('Members Subscriptions: subscription_form_shortcode called with product_id=' . $atts['product_id']);
        
        // Check for valid product_id
        if (empty($atts['product_id'])) {
            // If no product ID provided, get it from the current post
            if (is_singular('members_product')) {
                $atts['product_id'] = get_the_ID();
                error_log('Members Subscriptions: Using current post ID: ' . $atts['product_id']);
            } else {
                error_log('Members Subscriptions: No product ID provided and not on a product page');
                return '<p class="members-error">' . __('Error: No valid product selected.', 'members') . '</p>';
            }
        }
        
        // Ensure product_id is an integer
        $atts['product_id'] = absint($atts['product_id']);
        
        // Verify product exists and is the right type
        $product = get_post($atts['product_id']);
        if (!$product || $product->post_type !== 'members_product') {
            error_log('Members Subscriptions: Invalid product ID: ' . $atts['product_id']);
            return '<p class="members-error">' . __('Error: Invalid product.', 'members') . '</p>';
        }
        
        ob_start();
        include __DIR__ . '/views/subscription-form.php';
        $output = ob_get_clean();
        
        // Debug log
        error_log('Members Subscriptions: Form output length: ' . strlen($output));
        
        return $output;
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