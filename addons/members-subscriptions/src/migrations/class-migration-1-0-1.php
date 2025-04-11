<?php

namespace Members\Subscriptions\Migrations;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Add indexes for performance improvement and logging table
 */
class Migration_1_0_1 extends Migration {

    /**
     * The version this migration represents
     * 
     * @var string
     */
    protected $version = '1.0.1';
    
    /**
     * Description of what this migration does
     * 
     * @var string
     */
    protected $description = 'Add additional indexes for performance and create logging table';
    
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
        
        // Add compound index for subscriptions
        $table_subscriptions = $wpdb->prefix . 'members_subscriptions';
        $wpdb->query("ALTER TABLE $table_subscriptions ADD INDEX user_product (user_id, product_id)");
        
        // Add date indexes for transactions
        $table_transactions = $wpdb->prefix . 'members_transactions';
        $wpdb->query("ALTER TABLE $table_transactions ADD INDEX created_at (created_at)");
        
        // Create logging table
        $table_logs = $wpdb->prefix . 'members_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            context longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql_logs);
        
        // Check if there were any errors
        if (!empty($wpdb->last_error)) {
            $this->log('Error in migration 1.0.1: ' . $wpdb->last_error, 'error');
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
        
        // Remove added indexes
        $table_subscriptions = $wpdb->prefix . 'members_subscriptions';
        $wpdb->query("ALTER TABLE $table_subscriptions DROP INDEX user_product");
        
        $table_transactions = $wpdb->prefix . 'members_transactions';
        $wpdb->query("ALTER TABLE $table_transactions DROP INDEX created_at");
        
        // Drop the logs table
        $table_logs = $wpdb->prefix . 'members_logs';
        $wpdb->query("DROP TABLE IF EXISTS $table_logs");
        
        // Check if there were any errors
        if (!empty($wpdb->last_error)) {
            $this->log('Error in down migration 1.0.1: ' . $wpdb->last_error, 'error');
            return false;
        }
        
        return true;
    }
}