<?php
/**
 * Debug & Repair Tool for Members Subscriptions
 * 
 * Usage: 
 * 1. Include in the plugin directory
 * 2. Visit the URL with one of these parameters:
 *    - ?check=1 - Check tables and display subscription/transaction data
 *    - ?repair=1 - Attempt to repair database tables
 *    - ?recreate=1 - Force recreate all tables (warning: may lose data)
 *    - ?migrate=1 - Migrate data from fallbacks (user meta/options) to tables
 *    - ?user_id=X - Filter results for a specific user ID
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Safety check - prevent running outside WordPress
if (!function_exists('add_action')) {
    die('This script must be run within WordPress.');
}

// Check if user has administrator privileges
if (!current_user_can('administrator')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

// Set flag that we're running the debug script
define('MEMBERS_SUBSCRIPTIONS_DEBUG', true);

// Load necessary files
require_once dirname(__FILE__) . '/src/functions-db.php';

// Helper function to format output
function output_section_header($title) {
    echo '<div style="margin: 20px 0 10px; padding: 5px 10px; background: #f0f0f0; border-left: 4px solid #0073aa;">';
    echo '<h3 style="margin: 5px 0;">' . esc_html($title) . '</h3>';
    echo '</div>';
}

// Helper function to format results
function output_result($message, $success = true) {
    $color = $success ? '#46b450' : '#dc3232';
    echo '<div style="margin: 5px 0; padding: 10px; border-left: 4px solid ' . $color . ';">';
    echo $message;
    echo '</div>';
}

// Stylesheet for the page
echo '<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        color: #444;
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    h2 {
        color: #23282d;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    table {
        border-collapse: collapse;
        width: 100%;
        margin-bottom: 20px;
    }
    th, td {
        text-align: left;
        padding: 8px;
        border: 1px solid #ddd;
    }
    th {
        background-color: #f8f8f8;
    }
    tr:nth-child(even) {
        background-color: #f2f2f2;
    }
    .success {
        color: #46b450;
    }
    .error {
        color: #dc3232;
    }
    .warning {
        color: #ffb900;
    }
    .button {
        display: inline-block;
        text-decoration: none;
        font-size: 13px;
        line-height: 2.15384615;
        min-height: 30px;
        margin: 0 10px 0 0;
        padding: 0 10px;
        cursor: pointer;
        border-width: 1px;
        border-style: solid;
        -webkit-appearance: none;
        border-radius: 3px;
        white-space: nowrap;
        box-sizing: border-box;
        color: #0071a1;
        border-color: #0071a1;
        background: #f3f5f6;
        vertical-align: top;
    }
    .button-primary {
        background: #0071a1;
        border-color: #0071a1;
        color: #fff;
        text-decoration: none;
    }
    .button-danger {
        background: #dc3232;
        border-color: #dc3232;
        color: #fff;
        text-decoration: none;
    }
    .nav-tab-wrapper {
        border-bottom: 1px solid #ccc;
        margin: 0;
        padding-top: 9px;
        padding-bottom: 0;
        line-height: inherit;
    }
    .nav-tab {
        border: 1px solid #ccc;
        border-bottom: none;
        background: #e5e5e5;
        color: #555;
        font-size: 14px;
        line-height: 1.71428571;
        font-weight: 600;
        padding: 8px 10px;
        text-decoration: none;
        margin-right: 5px;
    }
    .nav-tab-active {
        background: #fff;
        color: #444;
        border-bottom: 1px solid #fff;
    }
    section {
        margin-top: 20px;
    }
    pre {
        background: #f5f5f5;
        padding: 10px;
        border: 1px solid #ddd;
        overflow: auto;
        max-height: 300px;
    }
</style>';

// Page header
echo '<div class="wrap">';
echo '<h2>Members Subscriptions Debug & Repair Tool</h2>';
echo '<p>Use this tool to diagnose and repair database issues with the Members Subscriptions plugin.</p>';

// Actions bar
echo '<div style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 3px;">';
echo '<a href="?check=1" class="button">Check Database</a>';
echo '<a href="?repair=1" class="button button-primary" onclick="return confirm(\'This will attempt to repair database tables. Continue?\');">Repair Tables</a>';
echo '<a href="?recreate=1" class="button button-danger" onclick="return confirm(\'WARNING: This will force recreation of all tables and may cause data loss. Continue only if repairs fail.\');">Force Recreation</a>';
echo '<a href="?migrate=1" class="button" onclick="return confirm(\'This will migrate data from fallbacks to database tables. Continue?\');">Migrate Fallback Data</a>';

// Display user filter if a user_id is provided
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($user_id) {
    $user = get_userdata($user_id);
    if ($user) {
        echo '<span style="margin-left: 20px;">Filtering for user: <strong>' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</strong></span>';
        echo ' <a href="?' . http_build_query(array_diff_key($_GET, ['user_id' => ''])) . '" class="button">Clear Filter</a>';
    }
} else {
    echo '<form style="display: inline-block; margin-left: 20px;" method="get">';
    foreach ($_GET as $key => $value) {
        if ($key !== 'user_id') {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        }
    }
    echo '<input type="number" name="user_id" placeholder="Filter by User ID" style="width: 150px; padding: 5px;">';
    echo '<button type="submit" class="button">Filter</button>';
    echo '</form>';
}

echo '</div>';

// Basic information about the environment
output_section_header('Environment Information');
echo '<table>';
echo '<tr><th style="width: 200px;">WordPress Version</th><td>' . esc_html(get_bloginfo('version')) . '</td></tr>';
echo '<tr><th>PHP Version</th><td>' . esc_html(PHP_VERSION) . '</td></tr>';
echo '<tr><th>MySQL Version</th><td>' . esc_html($GLOBALS['wpdb']->db_version()) . '</td></tr>';
echo '<tr><th>Table Prefix</th><td>' . esc_html($GLOBALS['wpdb']->prefix) . '</td></tr>';
echo '<tr><th>Plugin Version</th><td>' . (defined('\Members\Subscriptions\Plugin::VERSION') ? esc_html(\Members\Subscriptions\Plugin::VERSION) : 'Unknown') . '</td></tr>';
echo '</table>';

// Database Tables Check
output_section_header('Database Tables');

global $wpdb;
$required_tables = [
    \Members\Subscriptions\get_subscriptions_table_name(),
    \Members\Subscriptions\get_transactions_table_name(),
    \Members\Subscriptions\get_transactions_meta_table_name(),
    \Members\Subscriptions\get_products_meta_table_name(),
];

echo '<table>';
echo '<tr>
        <th>Table Name</th>
        <th>Status</th>
        <th>Records</th>
        <th>Structure</th>
        <th>Last Error</th>
      </tr>';

$all_tables_ok = true;
$tables_need_repair = false;

foreach ($required_tables as $table) {
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    $table_status = "Unknown";
    $record_count = "N/A";
    $structure_status = "N/A";
    $last_error = "";
    
    if ($table_exists) {
        try {
            // Check if table is readable
            $record_count_result = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            
            if ($record_count_result !== null) {
                $record_count = $record_count_result;
                $table_status = "<span class='success'>OK</span>";
            } else {
                $table_status = "<span class='error'>Error</span>";
                $last_error = $wpdb->last_error;
                $tables_need_repair = true;
                $all_tables_ok = false;
            }
            
            // Check table structure
            $columns = $wpdb->get_results("DESCRIBE $table", ARRAY_A);
            
            if (is_array($columns)) {
                // Expected minimum column counts
                $expected_columns = [
                    \Members\Subscriptions\get_subscriptions_table_name() => 23,
                    \Members\Subscriptions\get_transactions_table_name() => 15,
                    \Members\Subscriptions\get_products_meta_table_name() => 4,
                    \Members\Subscriptions\get_transactions_meta_table_name() => 4,
                ];
                
                $column_count = count($columns);
                
                if ($column_count >= $expected_columns[$table]) {
                    $structure_status = "<span class='success'>OK ($column_count columns)</span>";
                } else {
                    $structure_status = "<span class='error'>Incomplete ($column_count of {$expected_columns[$table]} expected columns)</span>";
                    $tables_need_repair = true;
                }
            } else {
                $structure_status = "<span class='error'>Could not check</span>";
                $last_error = $wpdb->last_error;
                $tables_need_repair = true;
            }
        } catch (\Exception $e) {
            $table_status = "<span class='error'>Exception</span>";
            $last_error = $e->getMessage();
            $tables_need_repair = true;
            $all_tables_ok = false;
        }
    } else {
        $table_status = "<span class='error'>Missing</span>";
        $tables_need_repair = true;
        $all_tables_ok = false;
    }
    
    echo "<tr>
            <td>" . esc_html($table) . "</td>
            <td>$table_status</td>
            <td>$record_count</td>
            <td>$structure_status</td>
            <td>" . esc_html($last_error) . "</td>
          </tr>";
}

echo '</table>';

// Add a summary and repair button
if ($all_tables_ok) {
    output_result('<strong>All database tables are present and appear to be working correctly.</strong>', true);
} else {
    output_result('<strong>Database issues detected.</strong> Click the Repair button above to attempt to fix these issues.', false);
}

// Check for database repair action
if (isset($_GET['repair'])) {
    output_section_header('Database Repair');
    
    // Try to verify/create tables
    echo '<p>Attempting to repair database tables...</p>';
    
    // Try normal verification first
    $verification_result = \Members\Subscriptions\verify_database_tables();
    
    if ($verification_result) {
        output_result('<strong>Success!</strong> Database tables were created/repaired successfully.', true);
    } else {
        output_result('<strong>Initial repair attempt failed.</strong> Trying direct table creation...', false);
        
        // Try direct creation
        \Members\Subscriptions\create_tables_directly();
        
        // Check if tables exist now
        $final_check = \Members\Subscriptions\check_tables_exist();
        
        if ($final_check) {
            output_result('<strong>Success!</strong> Database tables were created using direct SQL method.', true);
        } else {
            output_result('<strong>All repair attempts failed.</strong> You may need to try the "Force Recreation" option or check your database permissions.', false);
        }
    }
    
    echo '<p><a href="?check=1' . ($user_id ? '&user_id=' . $user_id : '') . '" class="button button-primary">Check Tables Again</a></p>';
}

// Check for table recreation action
if (isset($_GET['recreate'])) {
    output_section_header('Force Table Recreation');
    
    echo '<p>Attempting to force recreation of all database tables...</p>';
    echo '<p><strong>Warning:</strong> This operation drops and recreates tables, which may result in data loss if tables already exist.</p>';
    
    // Force recreation
    $recreation_result = \Members\Subscriptions\verify_database_tables(true);
    
    if ($recreation_result) {
        output_result('<strong>Success!</strong> Database tables were dropped and recreated successfully.', true);
    } else {
        output_result('<strong>Recreation failed.</strong> Trying direct table creation...', false);
        
        // Try direct creation
        \Members\Subscriptions\create_tables_directly();
        
        // Check if tables exist now
        $final_check = \Members\Subscriptions\check_tables_exist();
        
        if ($final_check) {
            output_result('<strong>Success!</strong> Database tables were recreated using direct SQL method.', true);
        } else {
            output_result('<strong>All recreation attempts failed.</strong> Please check your database permissions or contact support.', false);
        }
    }
    
    echo '<p><a href="?check=1' . ($user_id ? '&user_id=' . $user_id : '') . '" class="button button-primary">Check Tables Again</a></p>';
}

// Check for migrate action
if (isset($_GET['migrate'])) {
    output_section_header('Migrate Fallback Data');
    
    echo '<p>Looking for subscription and transaction data stored in user meta or options to migrate to database tables...</p>';
    
    // First make sure tables exist
    $tables_exist = \Members\Subscriptions\check_tables_exist();
    
    if (!$tables_exist) {
        output_result('<strong>Error:</strong> Database tables do not exist. Please repair or recreate tables first.', false);
    } else {
        // Look for data in user meta
        global $wpdb;
        
        // Find users with subscription data in meta
        $users_with_sub_data = $wpdb->get_col("
            SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = '_members_subscription_data' 
            OR meta_key = '_members_subscriptions'
        ");
        
        // Find users with transaction data in meta
        $users_with_txn_data = $wpdb->get_col("
            SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = '_members_transaction_data' 
            OR meta_key = '_members_transaction_id'
        ");
        
        // Get global option data
        $global_subs = get_option('members_subscription_users', []);
        
        echo '<p>Found:</p>';
        echo '<ul>';
        echo '<li>' . count($users_with_sub_data) . ' user(s) with subscription data in user meta</li>';
        echo '<li>' . count($users_with_txn_data) . ' user(s) with transaction data in user meta</li>';
        echo '<li>' . count($global_subs) . ' user(s) with subscription data in global option</li>';
        echo '</ul>';
        
        $migration_count = 0;
        
        // Process user meta subscriptions
        foreach ($users_with_sub_data as $user_id) {
            // Get subscription data from meta
            $sub_data = get_user_meta($user_id, '_members_subscription_data', true);
            
            if (empty($sub_data) || !is_array($sub_data)) {
                $sub_data = get_user_meta($user_id, '_members_subscriptions', true);
            }
            
            if (!empty($sub_data) && is_array($sub_data)) {
                // Check if this user already has subscriptions in the database
                $existing_subs = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM " . \Members\Subscriptions\get_subscriptions_table_name() . " WHERE user_id = %d",
                    $user_id
                ));
                
                if (empty($existing_subs)) {
                    // Create subscription in database
                    $sub_id = \Members\Subscriptions\create_subscription($sub_data);
                    
                    if ($sub_id) {
                        $migration_count++;
                    }
                }
            }
        }
        
        // Process user meta transactions
        foreach ($users_with_txn_data as $user_id) {
            // Get transaction data from meta
            $txn_data = get_user_meta($user_id, '_members_transaction_data', true);
            
            if (!empty($txn_data) && is_array($txn_data)) {
                // Check if this transaction already exists by trans_num if provided
                $exists = false;
                
                if (!empty($txn_data['trans_num'])) {
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM " . \Members\Subscriptions\get_transactions_table_name() . " WHERE trans_num = %s",
                        $txn_data['trans_num']
                    ));
                }
                
                if (empty($exists)) {
                    // Create transaction in database
                    $txn_id = \Members\Subscriptions\create_transaction($txn_data);
                    
                    if ($txn_id) {
                        $migration_count++;
                    }
                }
            }
        }
        
        // Process global option data
        foreach ($global_subs as $user_id => $sub_info) {
            if (is_array($sub_info) && !empty($sub_info['product_id'])) {
                // Check if user already has subscriptions
                $existing_subs = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM " . \Members\Subscriptions\get_subscriptions_table_name() . " WHERE user_id = %d",
                    $user_id
                ));
                
                if (empty($existing_subs)) {
                    // Format data for subscription creation
                    $sub_data = [
                        'user_id' => $user_id,
                        'product_id' => $sub_info['product_id'],
                        'status' => !empty($sub_info['active']) ? 'active' : 'expired',
                        'gateway' => 'manual',
                        'subscr_id' => !empty($sub_info['subscription_id']) ? $sub_info['subscription_id'] : 'migrated-' . uniqid(),
                        'created_at' => !empty($sub_info['created_at']) ? $sub_info['created_at'] : current_time('mysql'),
                    ];
                    
                    // Create subscription
                    $sub_id = \Members\Subscriptions\create_subscription($sub_data);
                    
                    if ($sub_id) {
                        $migration_count++;
                        
                        // Also create a transaction
                        $txn_data = [
                            'user_id' => $user_id,
                            'product_id' => $sub_info['product_id'],
                            'status' => 'complete',
                            'gateway' => 'manual',
                            'trans_num' => 'migrated-' . uniqid(),
                            'created_at' => !empty($sub_info['created_at']) ? $sub_info['created_at'] : current_time('mysql'),
                            'subscription_id' => $sub_id,
                        ];
                        
                        \Members\Subscriptions\create_transaction($txn_data);
                    }
                }
            }
        }
        
        if ($migration_count > 0) {
            output_result("<strong>Success!</strong> Migrated $migration_count records from fallback storage to database tables.", true);
        } else {
            output_result("<strong>No new records migrated.</strong> Either no valid fallback data was found or records already exist in the database.", false);
        }
    }
    
    echo '<p><a href="?check=1' . ($user_id ? '&user_id=' . $user_id : '') . '" class="button button-primary">Check Data Again</a></p>';
}

// Display subscription data on check action or by default
if (isset($_GET['check']) || (!isset($_GET['repair']) && !isset($_GET['recreate']) && !isset($_GET['migrate']))) {
    // Display subscriptions
    output_section_header('Subscriptions');
    
    if (function_exists('\Members\Subscriptions\get_subscriptions')) {
        $args = [];
        if ($user_id) {
            $args['user_id'] = $user_id;
        }
        
        $subscriptions = \Members\Subscriptions\get_subscriptions($args);
        
        if (empty($subscriptions)) {
            echo '<p>No subscriptions found in database tables.</p>';
            
            // Check for fallback in user meta
            if ($user_id) {
                $user_meta_sub = get_user_meta($user_id, '_members_subscription_data', true);
                if (!empty($user_meta_sub)) {
                    echo '<div style="margin: 15px 0; padding: 15px; background: #fef8ee; border: 1px solid #ffb900; border-left: 4px solid #ffb900;">';
                    echo '<h4 style="margin-top: 0;">Found subscription data in user meta:</h4>';
                    echo '<pre>';
                    print_r($user_meta_sub);
                    echo '</pre>';
                    echo '<p><a href="?migrate=1' . ($user_id ? '&user_id=' . $user_id : '') . '" class="button">Migrate This Data</a></p>';
                    echo '</div>';
                }
            }
        } else {
            echo '<table>';
            echo '<tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Product</th>
                    <th>Status</th>
                    <th>Gateway</th>
                    <th>Amount</th>
                    <th>Period</th>
                    <th>Created</th>
                    <th>Expires</th>
                </tr>';
            
            foreach ($subscriptions as $sub) {
                $user = get_userdata($sub->user_id);
                $user_info = $user ? $user->display_name . ' (' . $user->user_email . ')' : "User ID: {$sub->user_id}";
                
                $product = get_post($sub->product_id);
                $product_title = $product ? $product->post_title : "Product ID: {$sub->product_id}";
                
                $period_text = $sub->period . ' ' . $sub->period_type . ($sub->period > 1 ? 's' : '');
                
                $status_class = '';
                switch ($sub->status) {
                    case 'active': $status_class = 'success'; break;
                    case 'cancelled': 
                    case 'expired': $status_class = 'error'; break;
                    case 'pending': $status_class = 'warning'; break;
                }
                
                echo "<tr>";
                echo "<td>{$sub->id}</td>";
                echo "<td><a href='?check=1&user_id={$sub->user_id}'>" . esc_html($user_info) . "</a></td>";
                echo "<td>" . esc_html($product_title) . "</td>";
                echo "<td class='$status_class'>{$sub->status}</td>";
                echo "<td>{$sub->gateway}</td>";
                echo "<td>" . (!empty($sub->price) ? '$' . number_format($sub->price, 2) : 'Free') . "</td>";
                echo "<td>" . esc_html($period_text) . "</td>";
                echo "<td>" . esc_html($sub->created_at) . "</td>";
                echo "<td>" . ($sub->expires_at ? esc_html($sub->expires_at) : 'Never') . "</td>";
                echo "</tr>";
            }
            
            echo '</table>';
        }
    } else {
        echo '<p class="error">Function get_subscriptions() not available</p>';
    }
    
    // Display transactions
    output_section_header('Transactions');
    
    if (function_exists('\Members\Subscriptions\get_transactions')) {
        $args = [];
        if ($user_id) {
            $args['user_id'] = $user_id;
        }
        
        $transactions = \Members\Subscriptions\get_transactions($args);
        
        if (empty($transactions)) {
            echo '<p>No transactions found in database tables.</p>';
            
            // Check for fallback in user meta
            if ($user_id) {
                $user_meta_txn = get_user_meta($user_id, '_members_transaction_data', true);
                if (!empty($user_meta_txn)) {
                    echo '<div style="margin: 15px 0; padding: 15px; background: #fef8ee; border: 1px solid #ffb900; border-left: 4px solid #ffb900;">';
                    echo '<h4 style="margin-top: 0;">Found transaction data in user meta:</h4>';
                    echo '<pre>';
                    print_r($user_meta_txn);
                    echo '</pre>';
                    echo '<p><a href="?migrate=1' . ($user_id ? '&user_id=' . $user_id : '') . '" class="button">Migrate This Data</a></p>';
                    echo '</div>';
                }
            }
        } else {
            echo '<table>';
            echo '<tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Product</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Type</th>
                    <th>Gateway</th>
                    <th>Transaction #</th>
                    <th>Created</th>
                </tr>';
            
            foreach ($transactions as $txn) {
                $user = get_userdata($txn->user_id);
                $user_info = $user ? $user->display_name . ' (' . $user->user_email . ')' : "User ID: {$txn->user_id}";
                
                $product = get_post($txn->product_id);
                $product_title = $product ? $product->post_title : "Product ID: {$txn->product_id}";
                
                $status_class = '';
                switch ($txn->status) {
                    case 'complete': $status_class = 'success'; break;
                    case 'failed': 
                    case 'refunded': $status_class = 'error'; break;
                    case 'pending': $status_class = 'warning'; break;
                }
                
                echo "<tr>";
                echo "<td>{$txn->id}</td>";
                echo "<td><a href='?check=1&user_id={$txn->user_id}'>" . esc_html($user_info) . "</a></td>";
                echo "<td>" . esc_html($product_title) . "</td>";
                echo "<td>$" . number_format($txn->amount, 2) . "</td>";
                echo "<td class='$status_class'>{$txn->status}</td>";
                echo "<td>{$txn->txn_type}</td>";
                echo "<td>{$txn->gateway}</td>";
                echo "<td>{$txn->trans_num}</td>";
                echo "<td>" . esc_html($txn->created_at) . "</td>";
                echo "</tr>";
            }
            
            echo '</table>';
        }
    } else {
        echo '<p class="error">Function get_transactions() not available</p>';
    }
    
    // Check for global options fallback
    output_section_header('Global Fallback Storage');
    
    $all_subscriptions = get_option('members_subscription_users', []);
    if (!empty($all_subscriptions)) {
        echo '<div style="margin: 15px 0; padding: 15px; background: #fef8ee; border: 1px solid #ffb900; border-left: 4px solid #ffb900;">';
        echo '<h4 style="margin-top: 0;">Found subscription data in global option:</h4>';
        
        // Filter for specific user if needed
        if ($user_id && isset($all_subscriptions[$user_id])) {
            echo '<pre>';
            print_r($all_subscriptions[$user_id]);
            echo '</pre>';
        } else if (!$user_id) {
            echo '<p>Total users in global storage: ' . count($all_subscriptions) . '</p>';
            echo '<div style="max-height: 300px; overflow-y: auto; background: #f8f8f8; padding: 10px; border: 1px solid #ddd;">';
            foreach ($all_subscriptions as $user_id => $data) {
                $user = get_userdata($user_id);
                $user_info = $user ? $user->display_name . ' (' . $user->user_email . ')' : "User ID: $user_id";
                echo '<p><strong><a href="?check=1&user_id=' . esc_attr($user_id) . '">' . esc_html($user_info) . '</a></strong>:</p>';
                echo '<pre style="margin-left: 20px;">';
                print_r($data);
                echo '</pre>';
            }
            echo '</div>';
        } else {
            echo '<p>No data found for this user in global storage.</p>';
        }
        
        echo '<p><a href="?migrate=1' . ($user_id ? '&user_id=' . $user_id : '') . '" class="button">Migrate This Data</a></p>';
        echo '</div>';
    } else {
        echo '<p>No data found in global subscription fallback storage.</p>';
    }
}

echo '</div>'; // Close wrap
echo '<hr>';
echo '<p><small>Debug & Repair Tool v1.0 | Generated on: ' . date('Y-m-d H:i:s') . '</small></p>';