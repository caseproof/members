<?php
/**
 * This is a diagnostic script to check and fix subscription/transaction creation issues
 */

// Ensure this only runs when directly accessed, not during plugin operation
if (!defined('ABSPATH')) {
    // Set up WordPress environment
    require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/wp-load.php');
}

// Set headers for plain text output
header('Content-Type: text/plain');

// Function to output messages both to browser and error log
function debug_msg($message) {
    echo $message . "\n";
    error_log('[Members Debug] ' . $message);
}

// Check WordPress users
debug_msg("====== USER CHECK ======");
$users = get_users();
debug_msg("WordPress has " . count($users) . " users.");

// Check database tables
debug_msg("\n====== DATABASE TABLE CHECK ======");
global $wpdb;

$required_tables = [
    $wpdb->prefix . 'members_transactions',
    $wpdb->prefix . 'members_subscriptions',
    $wpdb->prefix . 'members_products_meta'
];

debug_msg("Database prefix: " . $wpdb->prefix);
debug_msg("Required tables: " . implode(', ', $required_tables));

// Check if tables exist
foreach($required_tables as $table) {
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($table_exists) {
        debug_msg("✓ Table exists: $table");
        // Show table structure
        $structure = $wpdb->get_results("DESCRIBE $table");
        $columns = [];
        foreach($structure as $column) {
            $columns[] = $column->Field . ' (' . $column->Type . ')';
        }
        debug_msg("   Columns: " . implode(', ', $columns));
        
        // Count records
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        debug_msg("   Records: $count");
    } else {
        debug_msg("✗ Table missing: $table");
        // Attempt to create the table
        debug_msg("   Attempting to create table...");
        
        if ($table == $wpdb->prefix . 'members_transactions') {
            $wpdb->query("
                CREATE TABLE $table (
                    id BIGINT(20) NOT NULL AUTO_INCREMENT,
                    user_id BIGINT(20) NOT NULL,
                    product_id BIGINT(20) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    status VARCHAR(50) NOT NULL,
                    transaction_id VARCHAR(100) NOT NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id)
                ) {$wpdb->get_charset_collate()};
            ");
        } else if ($table == $wpdb->prefix . 'members_subscriptions') {
            $wpdb->query("
                CREATE TABLE $table (
                    id BIGINT(20) NOT NULL AUTO_INCREMENT,
                    user_id BIGINT(20) NOT NULL,
                    product_id BIGINT(20) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    status VARCHAR(50) NOT NULL,
                    subscription_id VARCHAR(100) NOT NULL,
                    period INT(11) NOT NULL,
                    period_type VARCHAR(20) NOT NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id)
                ) {$wpdb->get_charset_collate()};
            ");
        } else if ($table == $wpdb->prefix . 'members_products_meta') {
            $wpdb->query("
                CREATE TABLE $table (
                    id BIGINT(20) NOT NULL AUTO_INCREMENT,
                    product_id BIGINT(20) NOT NULL,
                    meta_key VARCHAR(255) NOT NULL,
                    meta_value LONGTEXT,
                    PRIMARY KEY (id),
                    KEY product_id (product_id),
                    KEY meta_key (meta_key)
                ) {$wpdb->get_charset_collate()};
            ");
        }
        
        // Check if creation was successful
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if ($table_exists) {
            debug_msg("   ✓ Table created successfully");
        } else {
            debug_msg("   ✗ Failed to create table");
        }
    }
}

// Check user meta data for subscription info
debug_msg("\n====== USER META CHECK ======");

if (count($users) > 0) {
    foreach($users as $user) {
        debug_msg("User #{$user->ID}: {$user->user_login}");
        
        // Check subscription meta
        $subscription_meta = get_user_meta($user->ID, '_members_subscription_id', true);
        if ($subscription_meta) {
            debug_msg("   ✓ Has subscription meta: $subscription_meta");
        } else {
            debug_msg("   ✗ No subscription meta found");
        }
        
        // Check transaction meta
        $transaction_meta = get_user_meta($user->ID, '_members_transaction_id', true);
        if ($transaction_meta) {
            debug_msg("   ✓ Has transaction meta: $transaction_meta");
        } else {
            debug_msg("   ✗ No transaction meta found");
        }
        
        // Check for subscription record in database
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}members_subscriptions'")) {
            $db_sub = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}members_subscriptions WHERE user_id = %d", $user->ID));
            if ($db_sub) {
                debug_msg("   ✓ Has subscription in DB: #{$db_sub->id}");
            } else {
                debug_msg("   ✗ No subscription in database");
            }
        }
        
        // Check for transaction record in database
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}members_transactions'")) {
            $db_txn = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}members_transactions WHERE user_id = %d", $user->ID));
            if ($db_txn) {
                debug_msg("   ✓ Has transaction in DB: #{$db_txn->id}");
            } else {
                debug_msg("   ✗ No transaction in database");
            }
        }
        
        // Check for product association
        $product_meta = get_user_meta($user->ID, '_members_subscription_product', true);
        if ($product_meta) {
            $product = get_post($product_meta);
            debug_msg("   Product: " . ($product ? $product->post_title : "Unknown (ID: $product_meta)"));
        }
        
        debug_msg("");
    }
    
    // Offer to create missing records
    if (isset($_GET['fix']) && $_GET['fix'] == 'true') {
        debug_msg("\n====== FIXING RECORDS ======");
        
        foreach($users as $user) {
            $has_subscription = false;
            $has_transaction = false;
            
            // Check if user already has subscription in DB
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}members_subscriptions'")) {
                $has_subscription = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}members_subscriptions WHERE user_id = %d", $user->ID)) > 0;
            }
            
            // Check if user already has transaction in DB
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}members_transactions'")) {
                $has_transaction = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}members_transactions WHERE user_id = %d", $user->ID)) > 0;
            }
            
            // Find user's products through roles
            $user_obj = new WP_User($user->ID);
            $user_roles = $user_obj->roles;
            
            // Get all products
            $products = get_posts(['post_type' => 'members_product', 'numberposts' => -1]);
            $matching_products = [];
            
            foreach($products as $product) {
                $product_roles = get_post_meta($product->ID, '_membership_roles', true);
                if (!is_array($product_roles)) {
                    $product_roles = [];
                }
                
                $common_roles = array_intersect($user_roles, $product_roles);
                if (!empty($common_roles)) {
                    $matching_products[] = $product;
                }
            }
            
            debug_msg("User #{$user->ID}: {$user->user_login}");
            debug_msg("   Matching products: " . count($matching_products));
            
            if (!empty($matching_products)) {
                foreach($matching_products as $product) {
                    debug_msg("   Product: {$product->post_title} (#{$product->ID})");
                    
                    // Get product meta
                    $price = get_post_meta($product->ID, '_price', true);
                    if (empty($price)) $price = 0;
                    
                    $is_recurring = get_post_meta($product->ID, '_recurring', true);
                    $period = get_post_meta($product->ID, '_period', true);
                    if (empty($period)) $period = 1;
                    
                    $period_type = get_post_meta($product->ID, '_period_type', true);
                    if (empty($period_type)) $period_type = 'month';
                    
                    // 1. Create necessary user meta
                    if (!get_user_meta($user->ID, '_members_subscription_id', true)) {
                        $subscription_id = 'manual_sub_' . uniqid();
                        $created_at = current_time('mysql');
                        
                        update_user_meta($user->ID, '_members_subscription_id', $subscription_id);
                        update_user_meta($user->ID, '_members_subscription_product', $product->ID);
                        update_user_meta($user->ID, '_members_subscription_amount', $price);
                        update_user_meta($user->ID, '_members_subscription_date', $created_at);
                        update_user_meta($user->ID, '_members_subscription_status', 'active');
                        update_user_meta($user->ID, '_members_subscription_is_recurring', $is_recurring ? '1' : '0');
                        update_user_meta($user->ID, '_members_subscription_period', $period);
                        update_user_meta($user->ID, '_members_subscription_period_type', $period_type);
                        
                        debug_msg("   ✓ Created subscription user meta");
                    }
                    
                    if (!get_user_meta($user->ID, '_members_transaction_id', true)) {
                        $transaction_id = 'manual_' . uniqid();
                        $created_at = current_time('mysql');
                        
                        update_user_meta($user->ID, '_members_transaction_id', $transaction_id);
                        update_user_meta($user->ID, '_members_transaction_product', $product->ID);
                        update_user_meta($user->ID, '_members_transaction_amount', $price);
                        update_user_meta($user->ID, '_members_transaction_date', $created_at);
                        update_user_meta($user->ID, '_members_transaction_status', 'completed');
                        
                        debug_msg("   ✓ Created transaction user meta");
                    }
                    
                    // 2. Create database records if tables exist
                    if (!$has_subscription && $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}members_subscriptions'")) {
                        $subscription_id = get_user_meta($user->ID, '_members_subscription_id', true);
                        if (empty($subscription_id)) $subscription_id = 'manual_sub_' . uniqid();
                        
                        $created_at = get_user_meta($user->ID, '_members_subscription_date', true);
                        if (empty($created_at)) $created_at = current_time('mysql');
                        
                        $wpdb->insert(
                            $wpdb->prefix . 'members_subscriptions',
                            [
                                'user_id' => $user->ID,
                                'product_id' => $product->ID,
                                'amount' => $price,
                                'status' => 'active',
                                'subscription_id' => $subscription_id,
                                'period' => $period,
                                'period_type' => $period_type,
                                'created_at' => $created_at
                            ],
                            ['%d', '%d', '%f', '%s', '%s', '%d', '%s', '%s']
                        );
                        
                        debug_msg("   ✓ Created subscription database record");
                    }
                    
                    if (!$has_transaction && $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}members_transactions'")) {
                        $transaction_id = get_user_meta($user->ID, '_members_transaction_id', true);
                        if (empty($transaction_id)) $transaction_id = 'manual_' . uniqid();
                        
                        $created_at = get_user_meta($user->ID, '_members_transaction_date', true);
                        if (empty($created_at)) $created_at = current_time('mysql');
                        
                        $wpdb->insert(
                            $wpdb->prefix . 'members_transactions',
                            [
                                'user_id' => $user->ID,
                                'product_id' => $product->ID,
                                'amount' => $price,
                                'status' => 'completed',
                                'transaction_id' => $transaction_id,
                                'created_at' => $created_at
                            ],
                            ['%d', '%d', '%f', '%s', '%s', '%s']
                        );
                        
                        debug_msg("   ✓ Created transaction database record");
                    }
                }
            } else {
                debug_msg("   No matching products found for user roles: " . implode(', ', $user_roles));
            }
            
            debug_msg("");
        }
    } else {
        debug_msg("\nTo fix missing records, append ?fix=true to the URL.");
    }
}

debug_msg("\n====== END OF REPORT ======");
?>