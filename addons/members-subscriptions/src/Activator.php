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
        
        // Run the queries to create the tables
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($subscriptions_sql);
        dbDelta($transactions_sql);
        dbDelta($products_meta_sql);
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