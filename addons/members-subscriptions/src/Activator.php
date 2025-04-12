<?php

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handles activation tasks for the Members Subscriptions addon.
 */
class Activator {

    /**
     * Activate the addon.
     *
     * @return void
     */
    public static function activate() {
        self::setup_database_tables();
        self::setup_capabilities();
        self::setup_roles();
        
        // Register post types then flush rewrite rules
        // This should only happen on activation to avoid performance issues
        self::flush_rewrite_rules();
    }
    
    /**
     * Flush rewrite rules to ensure custom post type URLs work correctly
     * Only called during activation
     *
     * @return void 
     */
    private static function flush_rewrite_rules() {
        // Make sure post types are registered before flushing
        if (class_exists('\\Members\\Subscriptions\\Plugin')) {
            $plugin = \Members\Subscriptions\Plugin::get_instance();
            $plugin->register_post_types();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create necessary database tables for subscriptions and transactions.
     *
     * @return void
     */
    private static function setup_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Subscriptions table
        $subscriptions_table = $wpdb->prefix . 'members_subscriptions';
        $subscriptions_sql = "CREATE TABLE IF NOT EXISTS $subscriptions_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            gateway varchar(100) NOT NULL DEFAULT 'manual',
            status varchar(50) NOT NULL DEFAULT 'pending',
            subscr_id varchar(255) DEFAULT NULL,
            trial tinyint(1) NOT NULL DEFAULT 0,
            trial_days int(11) unsigned NOT NULL DEFAULT 0,
            trial_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            trial_tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            trial_total decimal(10,2) NOT NULL DEFAULT 0.00,
            period_type varchar(20) NOT NULL DEFAULT 'month',
            period int(11) unsigned NOT NULL DEFAULT 1,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            tax_rate decimal(10,2) NOT NULL DEFAULT 0.00,
            tax_desc varchar(255) DEFAULT NULL,
            total decimal(10,2) NOT NULL DEFAULT 0.00,
            cc_last4 varchar(4) DEFAULT NULL,
            cc_exp_month varchar(2) DEFAULT NULL,
            cc_exp_year varchar(4) DEFAULT NULL,
            created_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            renewal_count int(11) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY product_id (product_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Transactions table
        $transactions_table = $wpdb->prefix . 'members_transactions';
        $transactions_sql = "CREATE TABLE IF NOT EXISTS $transactions_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            total decimal(10,2) NOT NULL DEFAULT 0.00,
            tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            tax_rate decimal(10,2) NOT NULL DEFAULT 0.00,
            tax_desc varchar(255) DEFAULT NULL,
            trans_num varchar(255) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            txn_type varchar(50) NOT NULL DEFAULT 'payment',
            gateway varchar(100) NOT NULL DEFAULT 'manual',
            created_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            subscription_id bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY product_id (product_id),
            KEY subscription_id (subscription_id),
            KEY status (status),
            KEY trans_num (trans_num),
            KEY created_at (created_at),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Products table (using custom post type but need a meta table)
        $products_meta_table = $wpdb->prefix . 'members_products_meta';
        $products_meta_sql = "CREATE TABLE IF NOT EXISTS $products_meta_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY meta_key (meta_key(191))
        ) $charset_collate;";
        
        // Transaction meta table
        $transactions_meta_table = $wpdb->prefix . 'members_transactions_meta';
        $transactions_meta_sql = "CREATE TABLE IF NOT EXISTS $transactions_meta_table (
            meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            transaction_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY (meta_id),
            KEY transaction_id (transaction_id),
            KEY meta_key (meta_key(191))
        ) $charset_collate;";
        
        try {
            // Run the queries to create the tables
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            
            // First try using dbDelta
            $results = [];
            $results[] = dbDelta($subscriptions_sql);
            $results[] = dbDelta($transactions_sql);
            $results[] = dbDelta($products_meta_sql);
            $results[] = dbDelta($transactions_meta_sql);
            
            // Log the results
            error_log('Members Subscriptions: Database tables created via dbDelta: ' . json_encode($results));
            
            // Verify tables were created and try direct creation if they weren't
            $tables_to_check = [
                $subscriptions_table,
                $transactions_table,
                $products_meta_table,
                $transactions_meta_table
            ];
            
            foreach ($tables_to_check as $index => $table) {
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
                
                if (!$table_exists) {
                    error_log("Members Subscriptions: Table $table does not exist after dbDelta. Trying direct creation.");
                    
                    // Try direct creation
                    switch ($index) {
                        case 0:
                            $direct_result = $wpdb->query($subscriptions_sql);
                            break;
                        case 1:
                            $direct_result = $wpdb->query($transactions_sql);
                            break;
                        case 2:
                            $direct_result = $wpdb->query($products_meta_sql);
                            break;
                        case 3:
                            $direct_result = $wpdb->query($transactions_meta_sql);
                            break;
                    }
                    
                    error_log("Members Subscriptions: Direct creation of $table result: " . ($direct_result ? 'Success' : 'Failed'));
                }
            }
            
            // Update option to indicate database setup has been attempted
            update_option('members_subscriptions_db_version', \Members\Subscriptions\Plugin::VERSION);
            
        } catch (\Exception $e) {
            error_log('Members Subscriptions: Error creating database tables: ' . $e->getMessage());
            
            // Try manual creation as a last resort
            try {
                $wpdb->query($subscriptions_sql);
                $wpdb->query($transactions_sql);
                $wpdb->query($products_meta_sql);
                $wpdb->query($transactions_meta_sql);
                
                error_log('Members Subscriptions: Attempted direct database table creation after exception');
            } catch (\Exception $e2) {
                error_log('Members Subscriptions: Error in direct table creation: ' . $e2->getMessage());
            }
        }
        
        // Make one final verification
        self::verify_tables_exist();
    }
    
    /**
     * Verify that all required tables exist
     */
    private static function verify_tables_exist() {
        global $wpdb;
        
        $tables_to_check = [
            $wpdb->prefix . 'members_subscriptions',
            $wpdb->prefix . 'members_transactions',
            $wpdb->prefix . 'members_products_meta',
            $wpdb->prefix . 'members_transactions_meta'
        ];
        
        $missing_tables = [];
        
        foreach ($tables_to_check as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            
            if (!$table_exists) {
                $missing_tables[] = $table;
            }
        }
        
        if (!empty($missing_tables)) {
            error_log('Members Subscriptions: These tables are still missing after setup: ' . implode(', ', $missing_tables));
            update_option('members_subscriptions_missing_tables', $missing_tables);
        } else {
            error_log('Members Subscriptions: All required tables exist after setup');
            delete_option('members_subscriptions_missing_tables');
        }
    }

    /**
     * Add subscription-related capabilities to roles.
     *
     * @return void
     */
    private static function setup_capabilities() {
        // Get the administrator role
        $role = get_role('administrator');
        
        if (!empty($role)) {
            // Subscription capabilities
            $role->add_cap('view_subscriptions');
            $role->add_cap('edit_subscriptions');
            $role->add_cap('delete_subscriptions');
            
            // Transaction capabilities
            $role->add_cap('view_transactions');
            $role->add_cap('edit_transactions');
            $role->add_cap('process_refunds');
            
            // Product capabilities
            $role->add_cap('manage_subscription_products');
            
            // Make sure admin has all the required post type capabilities
            $role->add_cap('edit_members_product');
            $role->add_cap('read_members_product');
            $role->add_cap('delete_members_product');
            $role->add_cap('edit_members_products');
            $role->add_cap('edit_others_members_products');
            $role->add_cap('publish_members_products');
            $role->add_cap('read_private_members_products');
            $role->add_cap('delete_members_products');
            
            // Gateway capabilities
            $role->add_cap('manage_payment_gateways');
        }
    }

    /**
     * Setup roles for subscription members.
     *
     * @return void
     */
    private static function setup_roles() {
        // No special roles needed at this time
        // Will leverage existing roles and capabilities system
    }
}