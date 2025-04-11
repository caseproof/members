<?php

namespace Members\Subscriptions\Migrations;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Initial database schema migration
 */
class Migration_1_0_0 extends Migration {

    /**
     * The version this migration represents
     * 
     * @var string
     */
    protected $version = '1.0.0';
    
    /**
     * Description of what this migration does
     * 
     * @var string
     */
    protected $description = 'Initial database schema creation for subscriptions, transactions and metadata tables';
    
    /**
     * Run the up migration
     * 
     * @return bool True if successful, false otherwise
     */
    public function up() {
        global $wpdb;
        
        // Include dbDelta function
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Subscriptions table
        $table_subscriptions = $wpdb->prefix . 'members_subscriptions';
        $sql_subscriptions = "CREATE TABLE $table_subscriptions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            gateway varchar(50) NOT NULL,
            price decimal(13,4) NOT NULL DEFAULT '0.0000',
            tax_amount decimal(13,4) DEFAULT '0.0000',
            period int(11) DEFAULT NULL,
            period_type varchar(20) DEFAULT NULL,
            trial_days int(11) DEFAULT '0',
            trial_amount decimal(13,4) DEFAULT '0.0000',
            created_at datetime NOT NULL,
            expires_at datetime DEFAULT NULL,
            gateway_subscription_id varchar(255) DEFAULT NULL,
            gateway_customer_id varchar(255) DEFAULT NULL,
            renewal_count int(11) NOT NULL DEFAULT '0',
            last_payment_at datetime DEFAULT NULL,
            next_payment_at datetime DEFAULT NULL,
            cancelled_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY product_id (product_id),
            KEY status (status),
            KEY gateway (gateway)
        ) $charset_collate;";
        
        // Transactions table
        $table_transactions = $wpdb->prefix . 'members_transactions';
        $sql_transactions = "CREATE TABLE $table_transactions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            subscription_id bigint(20) unsigned DEFAULT NULL,
            product_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            gateway varchar(50) NOT NULL,
            amount decimal(13,4) NOT NULL DEFAULT '0.0000',
            tax_rate decimal(6,4) DEFAULT '0.0000',
            tax_amount decimal(13,4) DEFAULT '0.0000',
            total decimal(13,4) NOT NULL DEFAULT '0.0000',
            trans_num varchar(255) DEFAULT NULL,
            gateway_trans_id varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            failed_at datetime DEFAULT NULL,
            refunded_at datetime DEFAULT NULL,
            data longtext DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY subscription_id (subscription_id),
            KEY product_id (product_id),
            KEY status (status),
            KEY gateway (gateway)
        ) $charset_collate;";
        
        // Subscription meta table
        $table_subscriptions_meta = $wpdb->prefix . 'members_subscriptions_meta';
        $sql_subscriptions_meta = "CREATE TABLE $table_subscriptions_meta (
            meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            subscription_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext DEFAULT NULL,
            PRIMARY KEY  (meta_id),
            KEY subscription_id (subscription_id),
            KEY meta_key (meta_key(191))
        ) $charset_collate;";
        
        // Transaction meta table
        $table_transactions_meta = $wpdb->prefix . 'members_transactions_meta';
        $sql_transactions_meta = "CREATE TABLE $table_transactions_meta (
            meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            transaction_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext DEFAULT NULL,
            PRIMARY KEY  (meta_id),
            KEY transaction_id (transaction_id),
            KEY meta_key (meta_key(191))
        ) $charset_collate;";
        
        // Run the SQL queries
        $result1 = dbDelta($sql_subscriptions);
        $result2 = dbDelta($sql_transactions);
        $result3 = dbDelta($sql_subscriptions_meta);
        $result4 = dbDelta($sql_transactions_meta);
        
        // Check if there were any errors
        if (!empty($wpdb->last_error)) {
            $this->log('Error creating database tables: ' . $wpdb->last_error, 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * Run the down migration
     * 
     * @return bool True if successful, false otherwise
     */
    public function down() {
        global $wpdb;
        
        // Drop the tables in reverse order to avoid foreign key constraints
        $table_transactions_meta = $wpdb->prefix . 'members_transactions_meta';
        $table_subscriptions_meta = $wpdb->prefix . 'members_subscriptions_meta';
        $table_transactions = $wpdb->prefix . 'members_transactions';
        $table_subscriptions = $wpdb->prefix . 'members_subscriptions';
        
        $wpdb->query("DROP TABLE IF EXISTS $table_transactions_meta");
        $wpdb->query("DROP TABLE IF EXISTS $table_subscriptions_meta");
        $wpdb->query("DROP TABLE IF EXISTS $table_transactions");
        $wpdb->query("DROP TABLE IF EXISTS $table_subscriptions");
        
        // Check if there were any errors
        if (!empty($wpdb->last_error)) {
            $this->log('Error dropping database tables: ' . $wpdb->last_error, 'error');
            return false;
        }
        
        return true;
    }
}