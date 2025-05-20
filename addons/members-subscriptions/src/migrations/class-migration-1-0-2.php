<?php

namespace Members\Subscriptions\Migrations;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Add products meta table
 */
class Migration_1_0_2 extends Migration {

    /**
     * The version this migration represents
     * 
     * @var string
     */
    protected $version = '1.0.2';
    
    /**
     * Description of what this migration does
     * 
     * @var string
     */
    protected $description = 'Create products meta table for storing product settings';
    
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
        
        // Products meta table
        $table_products_meta = $wpdb->prefix . 'members_products_meta';
        $sql_products_meta = "CREATE TABLE $table_products_meta (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY meta_key (meta_key(191))
        ) $charset_collate;";
        
        // Run the SQL query
        $result = dbDelta($sql_products_meta);
        
        // Check if there were any errors
        if (!empty($wpdb->last_error)) {
            $this->log('Error creating products meta table: ' . $wpdb->last_error, 'error');
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
        
        // Drop the products meta table
        $table_products_meta = $wpdb->prefix . 'members_products_meta';
        $wpdb->query("DROP TABLE IF EXISTS $table_products_meta");
        
        // Check if there were any errors
        if (!empty($wpdb->last_error)) {
            $this->log('Error dropping products meta table: ' . $wpdb->last_error, 'error');
            return false;
        }
        
        return true;
    }
}