<?php

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Get the subscriptions database table name
 *
 * @return string
 */
function get_subscriptions_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'members_subscriptions';
}

/**
 * Get the transactions database table name
 *
 * @return string
 */
function get_transactions_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'members_transactions';
}

/**
 * Get the products meta database table name
 *
 * @return string
 */
function get_products_meta_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'members_products_meta';
}

/**
 * Get the transactions meta database table name
 *
 * @return string
 */
function get_transactions_meta_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'members_transactions_meta';
}

/**
 * Create a new subscription in the database
 *
 * @param array $data Subscription data
 * @return int|false The new subscription ID or false on failure
 */
function create_subscription($data) {
    global $wpdb;
    
    // Verify database tables exist before attempting to insert
    if (!verify_database_tables()) {
        error_log('Members Subscriptions: Failed to create database tables for subscriptions');
        return false;
    }
    
    // Make sure required fields are set
    $data = wp_parse_args($data, [
        'user_id'        => 0,
        'product_id'     => 0,
        'gateway'        => 'manual',
        'status'         => 'pending',
        'subscr_id'      => '',
        'trial'          => 0,
        'trial_days'     => 0,
        'trial_amount'   => 0.00,
        'trial_tax_amount' => 0.00,
        'trial_total'    => 0.00,
        'period_type'    => 'month',
        'period'         => 1,
        'price'          => 0.00,
        'tax_amount'     => 0.00,
        'tax_rate'       => 0.00,
        'tax_desc'       => '',
        'total'          => 0.00,
        'cc_last4'       => '',
        'cc_exp_month'   => '',
        'cc_exp_year'    => '',
        'created_at'     => current_time('mysql'),
        'expires_at'     => null,
        'renewal_count'  => 0,
    ]);
    
    // Validate required fields
    if (empty($data['user_id']) || empty($data['product_id'])) {
        error_log('Members Subscriptions: Missing required fields for subscription creation');
        return false;
    }
    
    try {
        // Insert subscription
        $result = $wpdb->insert(
            get_subscriptions_table_name(),
            $data,
            [
                '%d', // user_id
                '%d', // product_id
                '%s', // gateway
                '%s', // status
                '%s', // subscr_id
                '%d', // trial
                '%d', // trial_days
                '%f', // trial_amount
                '%f', // trial_tax_amount
                '%f', // trial_total
                '%s', // period_type
                '%d', // period
                '%f', // price
                '%f', // tax_amount
                '%f', // tax_rate
                '%s', // tax_desc
                '%f', // total
                '%s', // cc_last4
                '%s', // cc_exp_month
                '%s', // cc_exp_year
                '%s', // created_at
                '%s', // expires_at
                '%d', // renewal_count
            ]
        );
        
        if (!$result) {
            error_log('Members Subscriptions: Failed to insert subscription. DB Error: ' . $wpdb->last_error);
            return false;
        }
        
        $insert_id = $wpdb->insert_id;
        if (!$insert_id) {
            error_log('Members Subscriptions: Failed to get insert_id for subscription');
            return false;
        }
        
        // Also store as user meta as a backup
        update_user_meta($data['user_id'], '_members_subscription_data', $data);
        update_user_meta($data['user_id'], '_members_subscription_id', $insert_id);
        
        error_log('Members Subscriptions: Successfully created subscription with ID: ' . $insert_id);
        return $insert_id;
    } catch (\Exception $e) {
        error_log('Members Subscriptions: Exception when creating subscription: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update a subscription in the database
 *
 * @param int   $subscription_id Subscription ID
 * @param array $data           Subscription data to update
 * @return bool Whether the update was successful
 */
function update_subscription($subscription_id, $data) {
    global $wpdb;
    
    // Make sure we have a valid subscription ID
    if (empty($subscription_id)) {
        return false;
    }
    
    // Update subscription
    $result = $wpdb->update(
        get_subscriptions_table_name(),
        $data,
        ['id' => $subscription_id],
        null,
        ['%d']
    );
    
    return $result !== false;
}

/**
 * Get a subscription from the database
 *
 * @param int $subscription_id Subscription ID
 * @return object|null Subscription object or null if not found
 */
function get_subscription($subscription_id) {
    global $wpdb;
    
    return db_operation_with_verification(function($id) use ($wpdb) {
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . get_subscriptions_table_name() . " WHERE id = %d",
                $id
            )
        );
    }, [$subscription_id], null);
}

/**
 * Get subscriptions from the database
 *
 * @param array $args Query arguments
 * @return array Array of subscription objects
 */
function get_subscriptions($args = []) {
    global $wpdb;
    
    return db_operation_with_verification(function($query_args) use ($wpdb) {
        $defaults = [
            'user_id'    => 0,
            'product_id' => 0,
            'status'     => '',
            'gateway'    => '',
            'orderby'    => 'id',
            'order'      => 'DESC',
            'limit'      => 0,
            'offset'     => 0,
        ];
        
        $args = wp_parse_args($query_args, $defaults);
        
        // Build where clause
        $where = "WHERE 1=1";
        
        if (!empty($args['user_id'])) {
            $where .= $wpdb->prepare(" AND user_id = %d", $args['user_id']);
        }
        
        if (!empty($args['product_id'])) {
            $where .= $wpdb->prepare(" AND product_id = %d", $args['product_id']);
        }
        
        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(" AND status = %s", $args['status']);
        }
        
        if (!empty($args['gateway'])) {
            $where .= $wpdb->prepare(" AND gateway = %s", $args['gateway']);
        }
        
        // Build order clause
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'id DESC';
        }
        
        // Build limit clause
        $limit = '';
        if (!empty($args['limit'])) {
            $limit = $wpdb->prepare("LIMIT %d, %d", $args['offset'], $args['limit']);
        }
        
        // Execute query
        $sql = "SELECT * FROM " . get_subscriptions_table_name() . " $where ORDER BY $orderby $limit";
        
        return $wpdb->get_results($sql);
    }, [$args], []);
}

/**
 * Count subscriptions in the database
 *
 * @param array $args Query arguments
 * @return int Subscription count
 */
function count_subscriptions($args = []) {
    global $wpdb;
    
    $defaults = [
        'user_id'    => 0,
        'product_id' => 0,
        'status'     => '',
        'gateway'    => '',
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // Build where clause
    $where = "WHERE 1=1";
    
    if (!empty($args['user_id'])) {
        $where .= $wpdb->prepare(" AND user_id = %d", $args['user_id']);
    }
    
    if (!empty($args['product_id'])) {
        $where .= $wpdb->prepare(" AND product_id = %d", $args['product_id']);
    }
    
    if (!empty($args['status'])) {
        $where .= $wpdb->prepare(" AND status = %s", $args['status']);
    }
    
    if (!empty($args['gateway'])) {
        $where .= $wpdb->prepare(" AND gateway = %s", $args['gateway']);
    }
    
    // Execute query
    $sql = "SELECT COUNT(*) FROM " . get_subscriptions_table_name() . " $where";
    
    return (int) $wpdb->get_var($sql);
}

/**
 * Create a new transaction in the database
 *
 * @param array $data Transaction data
 * @return int|false The new transaction ID or false on failure
 */
function create_transaction($data) {
    global $wpdb;
    
    // Verify database tables exist before attempting to insert
    if (!verify_database_tables()) {
        error_log('Members Subscriptions: Failed to create database tables for transactions');
        return false;
    }
    
    // Make sure required fields are set
    $data = wp_parse_args($data, [
        'user_id'        => 0,
        'product_id'     => 0,
        'amount'         => 0.00,
        'total'          => 0.00,
        'tax_amount'     => 0.00,
        'tax_rate'       => 0.00,
        'tax_desc'       => '',
        'trans_num'      => uniqid('members-txn-'),
        'status'         => 'pending',
        'txn_type'       => 'payment',
        'gateway'        => 'manual',
        'created_at'     => current_time('mysql'),
        'expires_at'     => null,
        'subscription_id' => 0,
    ]);
    
    // Validate required fields
    if (empty($data['user_id']) || empty($data['product_id']) || empty($data['trans_num'])) {
        error_log('Members Subscriptions: Missing required fields for transaction creation');
        return false;
    }
    
    try {
        // Insert transaction
        $result = $wpdb->insert(
            get_transactions_table_name(),
            $data,
            [
                '%d', // user_id
                '%d', // product_id
                '%f', // amount
                '%f', // total
                '%f', // tax_amount
                '%f', // tax_rate
                '%s', // tax_desc
                '%s', // trans_num
                '%s', // status
                '%s', // txn_type
                '%s', // gateway
                '%s', // created_at
                '%s', // expires_at
                '%d', // subscription_id
            ]
        );
        
        if (!$result) {
            error_log('Members Subscriptions: Failed to insert transaction. DB Error: ' . $wpdb->last_error);
            return false;
        }
        
        $insert_id = $wpdb->insert_id;
        if (!$insert_id) {
            error_log('Members Subscriptions: Failed to get insert_id for transaction');
            return false;
        }
        
        // Also store as user meta as a backup
        update_user_meta($data['user_id'], '_members_transaction_data', $data);
        update_user_meta($data['user_id'], '_members_transaction_id', $insert_id);
        
        error_log('Members Subscriptions: Successfully created transaction with ID: ' . $insert_id);
        return $insert_id;
    } catch (\Exception $e) {
        error_log('Members Subscriptions: Exception when creating transaction: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update a transaction in the database
 *
 * @param int   $transaction_id Transaction ID
 * @param array $data          Transaction data to update
 * @return bool Whether the update was successful
 */
function update_transaction($transaction_id, $data) {
    global $wpdb;
    
    // Make sure we have a valid transaction ID
    if (empty($transaction_id)) {
        return false;
    }
    
    // Update transaction
    $result = $wpdb->update(
        get_transactions_table_name(),
        $data,
        ['id' => $transaction_id],
        null,
        ['%d']
    );
    
    return $result !== false;
}

/**
 * Get a transaction from the database
 *
 * @param int $transaction_id Transaction ID
 * @return object|null Transaction object or null if not found
 */
function get_transaction($transaction_id) {
    global $wpdb;
    
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM " . get_transactions_table_name() . " WHERE id = %d",
            $transaction_id
        )
    );
}

/**
 * Get a transaction by transaction number
 *
 * @param string $trans_num Transaction number
 * @return object|null Transaction object or null if not found
 */
function get_transaction_by_trans_num($trans_num) {
    global $wpdb;
    
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM " . get_transactions_table_name() . " WHERE trans_num = %s",
            $trans_num
        )
    );
}

/**
 * Get transactions from the database
 *
 * @param array $args Query arguments
 * @return array Array of transaction objects
 */
function get_transactions($args = []) {
    global $wpdb;
    
    $defaults = [
        'user_id'         => 0,
        'product_id'      => 0,
        'subscription_id' => 0,
        'status'          => '',
        'txn_type'        => '',
        'gateway'         => '',
        'orderby'         => 'id',
        'order'           => 'DESC',
        'limit'           => 0,
        'offset'          => 0,
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // Build where clause
    $where = "WHERE 1=1";
    
    if (!empty($args['user_id'])) {
        $where .= $wpdb->prepare(" AND user_id = %d", $args['user_id']);
    }
    
    if (!empty($args['product_id'])) {
        $where .= $wpdb->prepare(" AND product_id = %d", $args['product_id']);
    }
    
    if (!empty($args['subscription_id'])) {
        $where .= $wpdb->prepare(" AND subscription_id = %d", $args['subscription_id']);
    }
    
    if (!empty($args['status'])) {
        $where .= $wpdb->prepare(" AND status = %s", $args['status']);
    }
    
    if (!empty($args['txn_type'])) {
        $where .= $wpdb->prepare(" AND txn_type = %s", $args['txn_type']);
    }
    
    if (!empty($args['gateway'])) {
        $where .= $wpdb->prepare(" AND gateway = %s", $args['gateway']);
    }
    
    // Build order clause
    $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
    if (!$orderby) {
        $orderby = 'id DESC';
    }
    
    // Build limit clause
    $limit = '';
    if (!empty($args['limit'])) {
        $limit = $wpdb->prepare("LIMIT %d, %d", $args['offset'], $args['limit']);
    }
    
    // Execute query
    $sql = "SELECT * FROM " . get_transactions_table_name() . " $where ORDER BY $orderby $limit";
    
    return $wpdb->get_results($sql);
}

/**
 * Count transactions in the database
 *
 * @param array $args Query arguments
 * @return int Transaction count
 */
function count_transactions($args = []) {
    global $wpdb;
    
    $defaults = [
        'user_id'         => 0,
        'product_id'      => 0,
        'subscription_id' => 0,
        'status'          => '',
        'txn_type'        => '',
        'gateway'         => '',
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // Build where clause
    $where = "WHERE 1=1";
    
    if (!empty($args['user_id'])) {
        $where .= $wpdb->prepare(" AND user_id = %d", $args['user_id']);
    }
    
    if (!empty($args['product_id'])) {
        $where .= $wpdb->prepare(" AND product_id = %d", $args['product_id']);
    }
    
    if (!empty($args['subscription_id'])) {
        $where .= $wpdb->prepare(" AND subscription_id = %d", $args['subscription_id']);
    }
    
    if (!empty($args['status'])) {
        $where .= $wpdb->prepare(" AND status = %s", $args['status']);
    }
    
    if (!empty($args['txn_type'])) {
        $where .= $wpdb->prepare(" AND txn_type = %s", $args['txn_type']);
    }
    
    if (!empty($args['gateway'])) {
        $where .= $wpdb->prepare(" AND gateway = %s", $args['gateway']);
    }
    
    // Execute query
    $sql = "SELECT COUNT(*) FROM " . get_transactions_table_name() . " $where";
    
    return (int) $wpdb->get_var($sql);
}

/**
 * Update product meta data
 *
 * @param int    $product_id Product ID
 * @param string $key        Meta key
 * @param mixed  $value      Meta value
 * @return bool Whether the update was successful
 */
function update_product_meta($product_id, $key, $value) {
    // Always update regular post meta as a fallback
    update_post_meta($product_id, $key, $value);
    
    global $wpdb;
    
    // Check if the table exists
    $table_name = get_products_meta_table_name();
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if (!$table_exists) {
        // If the table doesn't exist, we can't update custom meta
        // But the post meta is already updated above, so return true
        return true;
    }
    
    // Check if meta exists
    $meta_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM " . $table_name . " WHERE product_id = %d AND meta_key = %s",
            $product_id,
            $key
        )
    );
    
    if ($meta_id) {
        // Update existing meta
        return $wpdb->update(
            $table_name,
            ['meta_value' => maybe_serialize($value)],
            ['id' => $meta_id],
            ['%s'],
            ['%d']
        ) !== false;
    } else {
        // Insert new meta
        return $wpdb->insert(
            $table_name,
            [
                'product_id' => $product_id,
                'meta_key'   => $key,
                'meta_value' => maybe_serialize($value),
            ],
            ['%d', '%s', '%s']
        ) !== false;
    }
}

/**
 * Get product meta data
 *
 * @param int    $product_id Product ID
 * @param string $key        Meta key
 * @param mixed  $default    Default value if meta doesn't exist
 * @return mixed Meta value
 */
function get_product_meta($product_id, $key, $default = '') {
    global $wpdb;
    
    // Get meta from custom table
    $table_name = get_products_meta_table_name();
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if ($table_exists) {
        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM $table_name WHERE product_id = %d AND meta_key = %s",
                $product_id,
                $key
            )
        );
        
        if ($value !== null) {
            $unserialized = maybe_unserialize($value);
            return $unserialized;
        }
    }
    
    // Fallback to post meta if custom table doesn't exist or value not found
    $value = get_post_meta($product_id, $key, true);
    
    if (empty($value) && $value !== '0' && $value !== 0) {
        return $default;
    }
    
    return $value;
}

/**
 * Delete product meta data
 *
 * @param int    $product_id Product ID
 * @param string $key        Meta key
 * @return bool Whether the deletion was successful
 */
function delete_product_meta($product_id, $key) {
    global $wpdb;
    
    return $wpdb->delete(
        get_products_meta_table_name(),
        [
            'product_id' => $product_id,
            'meta_key'   => $key,
        ],
        ['%d', '%s']
    ) !== false;
}

/**
 * Update transaction meta data
 *
 * @param int    $transaction_id Transaction ID
 * @param string $key           Meta key
 * @param mixed  $value         Meta value
 * @return bool Whether the update was successful
 */
function update_transaction_meta($transaction_id, $key, $value) {
    global $wpdb;
    
    // Check if meta exists
    $meta_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT meta_id FROM " . get_transactions_meta_table_name() . " WHERE transaction_id = %d AND meta_key = %s",
            $transaction_id,
            $key
        )
    );
    
    if ($meta_id) {
        // Update existing meta
        return $wpdb->update(
            get_transactions_meta_table_name(),
            ['meta_value' => maybe_serialize($value)],
            ['meta_id' => $meta_id],
            ['%s'],
            ['%d']
        ) !== false;
    } else {
        // Insert new meta
        return $wpdb->insert(
            get_transactions_meta_table_name(),
            [
                'transaction_id' => $transaction_id,
                'meta_key'       => $key,
                'meta_value'     => maybe_serialize($value),
            ],
            ['%d', '%s', '%s']
        ) !== false;
    }
}

/**
 * Get transaction meta data
 *
 * @param int    $transaction_id Transaction ID
 * @param string $key           Meta key
 * @param bool   $single        Whether to return a single value
 * @return mixed Meta value(s)
 */
function get_transaction_meta($transaction_id, $key, $single = true) {
    global $wpdb;
    
    $meta_values = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT meta_value FROM " . get_transactions_meta_table_name() . " WHERE transaction_id = %d AND meta_key = %s",
            $transaction_id,
            $key
        )
    );
    
    if (empty($meta_values)) {
        return $single ? '' : [];
    }
    
    $meta_values = array_map('maybe_unserialize', $meta_values);
    
    return $single ? $meta_values[0] : $meta_values;
}

/**
 * Delete transaction meta data
 *
 * @param int    $transaction_id Transaction ID
 * @param string $key           Meta key
 * @return bool Whether the deletion was successful
 */
function delete_transaction_meta($transaction_id, $key) {
    global $wpdb;
    
    return $wpdb->delete(
        get_transactions_meta_table_name(),
        [
            'transaction_id' => $transaction_id,
            'meta_key'       => $key,
        ],
        ['%d', '%s']
    ) !== false;
}

/**
 * Get all transaction meta for a specific transaction
 *
 * @param int $transaction_id Transaction ID
 * @return array Array of meta objects
 */
function get_transaction_meta_all($transaction_id) {
    global $wpdb;
    
    $meta = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT meta_key, meta_value FROM " . get_transactions_meta_table_name() . " WHERE transaction_id = %d",
            $transaction_id
        )
    );
    
    $meta_array = [];
    
    foreach ($meta as $item) {
        $meta_array[$item->meta_key] = maybe_unserialize($item->meta_value);
    }
    
    return $meta_array;
}

/**
 * Check if database tables exist and create them if they don't
 * 
 * @param bool $force_recreation Force tables to be recreated even if they exist
 * @return bool True if all tables exist or were created successfully
 */
function verify_database_tables($force_recreation = false) {
    global $wpdb;
    
    // Tables to check
    $required_tables = [
        get_subscriptions_table_name(),
        get_transactions_table_name(),
        get_transactions_meta_table_name(),
        get_products_meta_table_name(),
    ];
    
    $missing_tables = [];
    $existing_tables = [];
    
    // Check if each table exists
    foreach ($required_tables as $table) {
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        
        if (!$table_exists) {
            $missing_tables[] = $table;
        } else {
            $existing_tables[] = $table;
            
            // For existing tables, check if they have the correct structure
            if ($force_recreation) {
                // If forcing recreation, add to missing tables
                $missing_tables[] = $table;
            }
        }
    }
    
    // If all tables exist and we're not forcing recreation, return true
    if (empty($missing_tables) && !$force_recreation) {
        error_log('Members Subscriptions: All required database tables exist');
        return true;
    }
    
    // Log missing tables
    if (!empty($missing_tables)) {
        error_log('Members Subscriptions: Tables to create/recreate: ' . implode(', ', $missing_tables));
    }
    
    // Try multiple methods to create tables
    $creation_methods = [
        'migrations' => function() use ($missing_tables, $existing_tables, $force_recreation) {
            try {
                // Try to include migration manager and migrations to create tables
                $migration_files = [
                    dirname(__FILE__) . '/migrations/class-migration.php',
                    dirname(__FILE__) . '/migrations/class-migration-manager.php',
                    dirname(__FILE__) . '/migrations/class-migration-1-0-0.php',
                    dirname(__FILE__) . '/migrations/class-migration-1-0-1.php',
                    dirname(__FILE__) . '/migrations/class-migration-1-0-2.php',
                ];
                
                $all_files_exist = true;
                foreach ($migration_files as $file) {
                    if (!file_exists($file)) {
                        error_log('Members Subscriptions: Migration file not found: ' . $file);
                        $all_files_exist = false;
                        break;
                    }
                    require_once $file;
                }
                
                if (!$all_files_exist) {
                    return false;
                }
                
                // Run migrations
                $migration_manager = new Migrations\Migration_Manager();
                $results = $migration_manager->migrate();
                
                // Check if all migrations were successful
                $all_success = true;
                foreach ($results as $result) {
                    if (!$result['success']) {
                        error_log('Members Subscriptions: Migration failed: ' . ($result['message'] ?? 'Unknown error'));
                        $all_success = false;
                        break;
                    }
                }
                
                return $all_success;
            } catch (\Exception $e) {
                error_log('Members Subscriptions: Exception during migrations: ' . $e->getMessage());
                return false;
            }
        },
        'dbdelta' => function() use ($missing_tables, $existing_tables, $force_recreation) {
            try {
                // If we're forcing recreation, drop existing tables first
                global $wpdb;
                if ($force_recreation) {
                    foreach ($existing_tables as $table) {
                        $wpdb->query("DROP TABLE IF EXISTS $table");
                        error_log("Members Subscriptions: Dropped table $table for recreation");
                    }
                }
                
                // Load dbDelta
                if (!function_exists('dbDelta')) {
                    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                }
                
                // Create table schemas
                $charset_collate = $wpdb->get_charset_collate();
                
                // Subscriptions table
                $subscriptions_table = "CREATE TABLE " . get_subscriptions_table_name() . " (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    user_id bigint(20) NOT NULL,
                    product_id bigint(20) NOT NULL,
                    gateway varchar(50) NOT NULL,
                    status varchar(50) NOT NULL DEFAULT 'pending',
                    subscr_id varchar(100) NOT NULL,
                    trial tinyint(1) NOT NULL DEFAULT 0,
                    trial_days int(11) NOT NULL DEFAULT 0,
                    trial_amount decimal(10,2) NOT NULL DEFAULT 0.00,
                    trial_tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
                    trial_total decimal(10,2) NOT NULL DEFAULT 0.00,
                    period_type varchar(20) NOT NULL DEFAULT 'month',
                    period int(11) NOT NULL DEFAULT 1,
                    price decimal(10,2) NOT NULL DEFAULT 0.00,
                    tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
                    tax_rate decimal(10,2) NOT NULL DEFAULT 0.00,
                    tax_desc varchar(255) NOT NULL DEFAULT '',
                    total decimal(10,2) NOT NULL DEFAULT 0.00,
                    cc_last4 varchar(4) NOT NULL DEFAULT '',
                    cc_exp_month varchar(2) NOT NULL DEFAULT '',
                    cc_exp_year varchar(4) NOT NULL DEFAULT '',
                    created_at datetime NOT NULL,
                    expires_at datetime DEFAULT NULL,
                    renewal_count int(11) NOT NULL DEFAULT 0,
                    PRIMARY KEY  (id),
                    KEY user_id (user_id),
                    KEY product_id (product_id),
                    KEY status (status),
                    KEY gateway (gateway)
                ) $charset_collate;";
                
                // Transactions table
                $transactions_table = "CREATE TABLE " . get_transactions_table_name() . " (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    user_id bigint(20) NOT NULL,
                    product_id bigint(20) NOT NULL,
                    amount decimal(10,2) NOT NULL DEFAULT 0.00,
                    total decimal(10,2) NOT NULL DEFAULT 0.00,
                    tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
                    tax_rate decimal(10,2) NOT NULL DEFAULT 0.00,
                    tax_desc varchar(255) NOT NULL DEFAULT '',
                    trans_num varchar(100) NOT NULL,
                    status varchar(50) NOT NULL DEFAULT 'pending',
                    txn_type varchar(50) NOT NULL DEFAULT 'payment',
                    gateway varchar(50) NOT NULL,
                    created_at datetime NOT NULL,
                    expires_at datetime DEFAULT NULL,
                    subscription_id bigint(20) NOT NULL DEFAULT 0,
                    PRIMARY KEY  (id),
                    KEY user_id (user_id),
                    KEY product_id (product_id),
                    KEY status (status),
                    KEY gateway (gateway),
                    KEY subscription_id (subscription_id)
                ) $charset_collate;";
                
                // Products meta table
                $products_meta_table = "CREATE TABLE " . get_products_meta_table_name() . " (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    product_id bigint(20) NOT NULL,
                    meta_key varchar(255) NOT NULL,
                    meta_value longtext NOT NULL,
                    PRIMARY KEY  (id),
                    KEY product_id (product_id),
                    KEY meta_key (meta_key(191))
                ) $charset_collate;";
                
                // Transactions meta table
                $transactions_meta_table = "CREATE TABLE " . get_transactions_meta_table_name() . " (
                    meta_id bigint(20) NOT NULL AUTO_INCREMENT,
                    transaction_id bigint(20) NOT NULL,
                    meta_key varchar(255) NOT NULL,
                    meta_value longtext NOT NULL,
                    PRIMARY KEY  (meta_id),
                    KEY transaction_id (transaction_id),
                    KEY meta_key (meta_key(191))
                ) $charset_collate;";
                
                $dbdelta_result = dbDelta($subscriptions_table);
                $dbdelta_result .= dbDelta($transactions_table);
                $dbdelta_result .= dbDelta($products_meta_table);
                $dbdelta_result .= dbDelta($transactions_meta_table);
                
                error_log('Members Subscriptions: dbDelta executed: ' . print_r($dbdelta_result, true));
                
                // Check if tables were created
                return check_tables_exist();
            } catch (\Exception $e) {
                error_log('Members Subscriptions: Exception during dbDelta: ' . $e->getMessage());
                return false;
            }
        },
        'direct_sql' => function() use ($missing_tables, $existing_tables, $force_recreation) {
            try {
                // If we're forcing recreation, drop existing tables first
                global $wpdb;
                
                foreach ($missing_tables as $table) {
                    // Drop the table if it exists (even if it's in $missing_tables, it might exist but be missing required columns)
                    $wpdb->query("DROP TABLE IF EXISTS $table");
                    error_log("Members Subscriptions: Attempting to drop table $table before creation");
                }
                
                // Create table schemas
                $charset_collate = $wpdb->get_charset_collate();
                
                // Subscriptions table
                $subscriptions_table = "CREATE TABLE " . get_subscriptions_table_name() . " (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    user_id bigint(20) NOT NULL,
                    product_id bigint(20) NOT NULL,
                    gateway varchar(50) NOT NULL,
                    status varchar(50) NOT NULL DEFAULT 'pending',
                    subscr_id varchar(100) NOT NULL,
                    trial tinyint(1) NOT NULL DEFAULT 0,
                    trial_days int(11) NOT NULL DEFAULT 0,
                    trial_amount decimal(10,2) NOT NULL DEFAULT 0.00,
                    trial_tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
                    trial_total decimal(10,2) NOT NULL DEFAULT 0.00,
                    period_type varchar(20) NOT NULL DEFAULT 'month',
                    period int(11) NOT NULL DEFAULT 1,
                    price decimal(10,2) NOT NULL DEFAULT 0.00,
                    tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
                    tax_rate decimal(10,2) NOT NULL DEFAULT 0.00,
                    tax_desc varchar(255) NOT NULL DEFAULT '',
                    total decimal(10,2) NOT NULL DEFAULT 0.00,
                    cc_last4 varchar(4) NOT NULL DEFAULT '',
                    cc_exp_month varchar(2) NOT NULL DEFAULT '',
                    cc_exp_year varchar(4) NOT NULL DEFAULT '',
                    created_at datetime NOT NULL,
                    expires_at datetime DEFAULT NULL,
                    renewal_count int(11) NOT NULL DEFAULT 0,
                    PRIMARY KEY  (id),
                    KEY user_id (user_id),
                    KEY product_id (product_id),
                    KEY status (status),
                    KEY gateway (gateway)
                ) $charset_collate;";
                
                // Transactions table
                $transactions_table = "CREATE TABLE " . get_transactions_table_name() . " (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    user_id bigint(20) NOT NULL,
                    product_id bigint(20) NOT NULL,
                    amount decimal(10,2) NOT NULL DEFAULT 0.00,
                    total decimal(10,2) NOT NULL DEFAULT 0.00,
                    tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
                    tax_rate decimal(10,2) NOT NULL DEFAULT 0.00,
                    tax_desc varchar(255) NOT NULL DEFAULT '',
                    trans_num varchar(100) NOT NULL,
                    status varchar(50) NOT NULL DEFAULT 'pending',
                    txn_type varchar(50) NOT NULL DEFAULT 'payment',
                    gateway varchar(50) NOT NULL,
                    created_at datetime NOT NULL,
                    expires_at datetime DEFAULT NULL,
                    subscription_id bigint(20) NOT NULL DEFAULT 0,
                    PRIMARY KEY  (id),
                    KEY user_id (user_id),
                    KEY product_id (product_id),
                    KEY status (status),
                    KEY gateway (gateway),
                    KEY subscription_id (subscription_id)
                ) $charset_collate;";
                
                // Products meta table
                $products_meta_table = "CREATE TABLE " . get_products_meta_table_name() . " (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    product_id bigint(20) NOT NULL,
                    meta_key varchar(255) NOT NULL,
                    meta_value longtext NOT NULL,
                    PRIMARY KEY  (id),
                    KEY product_id (product_id),
                    KEY meta_key (meta_key(191))
                ) $charset_collate;";
                
                // Transactions meta table
                $transactions_meta_table = "CREATE TABLE " . get_transactions_meta_table_name() . " (
                    meta_id bigint(20) NOT NULL AUTO_INCREMENT,
                    transaction_id bigint(20) NOT NULL,
                    meta_key varchar(255) NOT NULL,
                    meta_value longtext NOT NULL,
                    PRIMARY KEY  (meta_id),
                    KEY transaction_id (transaction_id),
                    KEY meta_key (meta_key(191))
                ) $charset_collate;";
                
                // Execute queries
                $wpdb->query($subscriptions_table);
                $wpdb->query($transactions_table);
                $wpdb->query($products_meta_table);
                $wpdb->query($transactions_meta_table);
                
                error_log('Members Subscriptions: Direct SQL executed for table creation');
                
                // Check if tables were created
                return check_tables_exist();
            } catch (\Exception $e) {
                error_log('Members Subscriptions: Exception during direct SQL: ' . $e->getMessage());
                return false;
            }
        }
    ];
    
    // Try each method in order until one succeeds
    foreach ($creation_methods as $method_name => $method) {
        error_log("Members Subscriptions: Attempting table creation using $method_name");
        $success = $method();
        
        if ($success) {
            error_log("Members Subscriptions: Table creation succeeded using $method_name");
            return true;
        }
        
        error_log("Members Subscriptions: Table creation failed using $method_name, trying next method");
    }
    
    // If we get here, all methods failed
    error_log('Members Subscriptions: All table creation methods failed');
    
    // One last check
    $final_check = check_tables_exist();
    
    // Store missing tables in an option for admin notification
    $final_missing = [];
    if (!$final_check) {
        foreach ($required_tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            if (!$table_exists) {
                $final_missing[] = $table;
            }
        }
        update_option('members_subscriptions_missing_tables', $final_missing);
    } else {
        delete_option('members_subscriptions_missing_tables');
    }
    
    return $final_check;
}

/**
 * Create database tables directly without using migrations
 */
function create_tables_directly() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    error_log('Members Subscriptions: Creating tables directly');
    
    // Subscriptions table
    $subscriptions_table = "CREATE TABLE " . get_subscriptions_table_name() . " (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        product_id bigint(20) NOT NULL,
        gateway varchar(50) NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'pending',
        subscr_id varchar(100) NOT NULL,
        trial tinyint(1) NOT NULL DEFAULT 0,
        trial_days int(11) NOT NULL DEFAULT 0,
        trial_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        trial_tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        trial_total decimal(10,2) NOT NULL DEFAULT 0.00,
        period_type varchar(20) NOT NULL DEFAULT 'month',
        period int(11) NOT NULL DEFAULT 1,
        price decimal(10,2) NOT NULL DEFAULT 0.00,
        tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        tax_rate decimal(10,2) NOT NULL DEFAULT 0.00,
        tax_desc varchar(255) NOT NULL DEFAULT '',
        total decimal(10,2) NOT NULL DEFAULT 0.00,
        cc_last4 varchar(4) NOT NULL DEFAULT '',
        cc_exp_month varchar(2) NOT NULL DEFAULT '',
        cc_exp_year varchar(4) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        expires_at datetime DEFAULT NULL,
        renewal_count int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY product_id (product_id),
        KEY status (status),
        KEY gateway (gateway)
    ) $charset_collate;";
    
    // Transactions table
    $transactions_table = "CREATE TABLE " . get_transactions_table_name() . " (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        product_id bigint(20) NOT NULL,
        amount decimal(10,2) NOT NULL DEFAULT 0.00,
        total decimal(10,2) NOT NULL DEFAULT 0.00,
        tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        tax_rate decimal(10,2) NOT NULL DEFAULT 0.00,
        tax_desc varchar(255) NOT NULL DEFAULT '',
        trans_num varchar(100) NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'pending',
        txn_type varchar(50) NOT NULL DEFAULT 'payment',
        gateway varchar(50) NOT NULL,
        created_at datetime NOT NULL,
        expires_at datetime DEFAULT NULL,
        subscription_id bigint(20) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY product_id (product_id),
        KEY status (status),
        KEY gateway (gateway),
        KEY subscription_id (subscription_id)
    ) $charset_collate;";
    
    // Products meta table
    $products_meta_table = "CREATE TABLE " . get_products_meta_table_name() . " (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        product_id bigint(20) NOT NULL,
        meta_key varchar(255) NOT NULL,
        meta_value longtext NOT NULL,
        PRIMARY KEY  (id),
        KEY product_id (product_id),
        KEY meta_key (meta_key(191))
    ) $charset_collate;";
    
    // Transactions meta table
    $transactions_meta_table = "CREATE TABLE " . get_transactions_meta_table_name() . " (
        meta_id bigint(20) NOT NULL AUTO_INCREMENT,
        transaction_id bigint(20) NOT NULL,
        meta_key varchar(255) NOT NULL,
        meta_value longtext NOT NULL,
        PRIMARY KEY  (meta_id),
        KEY transaction_id (transaction_id),
        KEY meta_key (meta_key(191))
    ) $charset_collate;";
    
    // Execute queries
    try {
        // Ensure dbDelta function is available
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        dbDelta($subscriptions_table);
        dbDelta($transactions_table);
        dbDelta($products_meta_table);
        dbDelta($transactions_meta_table);
        
        error_log('Members Subscriptions: Tables created directly with dbDelta');
        return true;
    } catch (\Exception $e) {
        // Fall back to direct query execution if dbDelta fails
        error_log('Members Subscriptions: dbDelta failed: ' . $e->getMessage() . ', trying direct execution');
        try {
            // Drop tables if they exist but are incomplete
            $wpdb->query("DROP TABLE IF EXISTS " . get_subscriptions_table_name());
            $wpdb->query("DROP TABLE IF EXISTS " . get_transactions_table_name());
            $wpdb->query("DROP TABLE IF EXISTS " . get_products_meta_table_name());
            $wpdb->query("DROP TABLE IF EXISTS " . get_transactions_meta_table_name());
            
            // Create tables
            $wpdb->query($subscriptions_table);
            $wpdb->query($transactions_table);
            $wpdb->query($products_meta_table);
            $wpdb->query($transactions_meta_table);
            
            error_log('Members Subscriptions: Tables created with direct SQL');
            return true;
        } catch (\Exception $e2) {
            error_log('Members Subscriptions: Direct table creation failed: ' . $e2->getMessage());
            return false;
        }
    }
}

/**
 * Check if all required tables exist
 * 
 * @return bool True if all tables exist
 */
function check_tables_exist() {
    global $wpdb;
    
    // Tables to check
    $required_tables = [
        get_subscriptions_table_name(),
        get_transactions_table_name(),
        get_transactions_meta_table_name(),
        get_products_meta_table_name(),
    ];
    
    // Check if each table exists
    foreach ($required_tables as $table) {
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        
        if (!$table_exists) {
            error_log('Members Subscriptions: Table still missing after creation attempts: ' . $table);
            return false;
        }
    }
    
    error_log('Members Subscriptions: All required tables exist');
    return true;
}

/**
 * Adds admin notices for database issues and provides tools to fix them
 */
function maybe_add_database_admin_notice() {
    global $wpdb;
    
    // Limit checks to admin pages to avoid performance impact on frontend
    if (!is_admin()) {
        return;
    }
    
    // Only run this check once per hour
    if (get_transient('members_subscriptions_tables_checked') && !isset($_GET['members_force_table_check'])) {
        return;
    }
    
    // Set transient to prevent repeated checks
    set_transient('members_subscriptions_tables_checked', true, HOUR_IN_SECONDS);
    
    // Check if tables exist
    $missing_tables = [];
    $problematic_tables = [];
    $required_tables = [
        get_subscriptions_table_name(),
        get_transactions_table_name(),
        get_transactions_meta_table_name(),
        get_products_meta_table_name(),
    ];
    
    // Store diagnostic information for admin notice
    $diagnostics = [
        'plugin_version' => defined('\Members\Subscriptions\Plugin::VERSION') ? \Members\Subscriptions\Plugin::VERSION : 'Unknown',
        'wp_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'mysql_version' => $wpdb->db_version(),
        'table_prefix' => $wpdb->prefix,
        'database_host' => DB_HOST,
        'active_plugins' => get_option('active_plugins'),
        'multisite' => is_multisite() ? 'Yes' : 'No',
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
    ];
    
    // Additional checks for each table
    foreach ($required_tables as $table) {
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        
        if (!$table_exists) {
            $missing_tables[] = $table;
        } else {
            // Table exists, but check if it has the correct structure
            $table_status = [];
            
            try {
                // Check for basic table structure
                $columns = $wpdb->get_results("DESCRIBE $table", ARRAY_A);
                $column_count = is_array($columns) ? count($columns) : 0;
                
                // Expected column counts for each table
                $expected_columns = [
                    get_subscriptions_table_name() => 23,
                    get_transactions_table_name() => 15,
                    get_products_meta_table_name() => 4,
                    get_transactions_meta_table_name() => 4,
                ];
                
                // If column count doesn't match, record as problematic
                if ($column_count < ($expected_columns[$table] ?? 1)) {
                    $table_status['error'] = "Table has only $column_count columns (expected at least {$expected_columns[$table]})";
                    $problematic_tables[$table] = $table_status;
                } else {
                    // Check if we can do a basic query against the table
                    $test_query_result = null;
                    
                    if (strpos($table, 'subscriptions') !== false) {
                        $test_query_result = $wpdb->query("SELECT COUNT(*) FROM $table LIMIT 1");
                    } elseif (strpos($table, 'transactions') !== false) {
                        $test_query_result = $wpdb->query("SELECT COUNT(*) FROM $table LIMIT 1");
                    } elseif (strpos($table, 'products_meta') !== false) {
                        $test_query_result = $wpdb->query("SELECT COUNT(*) FROM $table LIMIT 1");
                    } elseif (strpos($table, 'transactions_meta') !== false) {
                        $test_query_result = $wpdb->query("SELECT COUNT(*) FROM $table LIMIT 1");
                    }
                    
                    if ($test_query_result === false) {
                        $table_status['error'] = "Table exists but query failed: " . $wpdb->last_error;
                        $problematic_tables[$table] = $table_status;
                    }
                }
            } catch (\Exception $e) {
                $table_status['error'] = "Exception checking table: " . $e->getMessage();
                $problematic_tables[$table] = $table_status;
            }
        }
    }
    
    // Check for existing records in the tables
    $table_counts = [];
    foreach ($required_tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'")) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            $table_counts[$table] = $count !== null ? (int)$count : 'Error';
        } else {
            $table_counts[$table] = 'Missing';
        }
    }
    $diagnostics['table_counts'] = $table_counts;
    
    // If tables are missing or problematic, add an admin notice
    if (!empty($missing_tables) || !empty($problematic_tables)) {
        $combined_issues = array_merge($missing_tables, array_keys($problematic_tables));
        
        // Set a flag so we know there are problems
        update_option('members_subscriptions_db_issues', [
            'missing_tables' => $missing_tables,
            'problematic_tables' => $problematic_tables,
            'diagnostics' => $diagnostics,
            'timestamp' => time(),
        ]);
        
        add_action('admin_notices', function() use ($missing_tables, $problematic_tables, $diagnostics) {
            $screen = get_current_screen();
            $is_members_page = $screen && (strpos($screen->id, 'members') !== false);
            
            // Always use error notice for missing tables, warning for problematic tables
            $notice_type = !empty($missing_tables) ? 'error' : 'warning';
            
            ?>
            <div class="notice notice-<?php echo $notice_type; ?> is-dismissible">
                <h3 style="margin-top: 0.5em;">Members Subscriptions: Database Issues Detected</h3>
                
                <?php if (!empty($missing_tables)): ?>
                    <p><strong>Missing Tables:</strong> <?php echo esc_html(implode(', ', $missing_tables)); ?></p>
                    <p>This will prevent subscriptions and transactions from being stored properly. Please fix this issue to ensure proper plugin functionality.</p>
                <?php endif; ?>
                
                <?php if (!empty($problematic_tables)): ?>
                    <p><strong>Problematic Tables:</strong> <?php echo esc_html(implode(', ', array_keys($problematic_tables))); ?></p>
                    <ul>
                        <?php foreach ($problematic_tables as $table => $status): ?>
                            <li><?php echo esc_html($table); ?>: <?php echo esc_html($status['error']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <p>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=members_create_missing_tables'), 'members_create_tables')); ?>" class="button button-primary">
                        Attempt to Fix Tables Now
                    </a>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=members_force_recreate_tables'), 'members_force_recreate')); ?>" class="button" style="margin-left: 10px;">
                        Force Recreation of All Tables
                    </a>
                    <button type="button" id="members-toggle-diagnostics" class="button button-secondary" style="margin-left: 10px;">
                        Show Diagnostic Information
                    </button>
                </p>
                
                <div id="members-diagnostics-info" style="display: none; margin-top: 10px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; max-height: 300px; overflow-y: auto;">
                    <h4 style="margin-top: 0;">Diagnostic Information:</h4>
                    <table class="widefat striped" style="width: auto;">
                        <tr><th>Plugin Version</th><td><?php echo esc_html($diagnostics['plugin_version']); ?></td></tr>
                        <tr><th>WordPress Version</th><td><?php echo esc_html($diagnostics['wp_version']); ?></td></tr>
                        <tr><th>PHP Version</th><td><?php echo esc_html($diagnostics['php_version']); ?></td></tr>
                        <tr><th>MySQL Version</th><td><?php echo esc_html($diagnostics['mysql_version']); ?></td></tr>
                        <tr><th>Table Prefix</th><td><?php echo esc_html($diagnostics['table_prefix']); ?></td></tr>
                        <tr><th>Multisite</th><td><?php echo esc_html($diagnostics['multisite']); ?></td></tr>
                        <tr><th>Memory Limit</th><td><?php echo esc_html($diagnostics['memory_limit']); ?></td></tr>
                        <tr><th>Max Execution Time</th><td><?php echo esc_html($diagnostics['max_execution_time']); ?></td></tr>
                        <tr>
                            <th>Table Counts</th>
                            <td>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <?php foreach ($diagnostics['table_counts'] as $table => $count): ?>
                                        <li><?php echo esc_html(basename($table)); ?>: <?php echo esc_html($count); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const toggleButton = document.getElementById('members-toggle-diagnostics');
                    const diagnosticsDiv = document.getElementById('members-diagnostics-info');
                    
                    if (toggleButton && diagnosticsDiv) {
                        toggleButton.addEventListener('click', function() {
                            if (diagnosticsDiv.style.display === 'none') {
                                diagnosticsDiv.style.display = 'block';
                                toggleButton.textContent = 'Hide Diagnostic Information';
                            } else {
                                diagnosticsDiv.style.display = 'none';
                                toggleButton.textContent = 'Show Diagnostic Information';
                            }
                        });
                    }
                });
                </script>
            </div>
            <?php
        });
        
        // Register the admin action to create tables
        add_action('admin_post_members_create_missing_tables', function() {
            // Verify nonce
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'members_create_tables')) {
                wp_die('Security check failed.');
            }
            
            // Create tables
            $result = verify_database_tables();
            if (!$result) {
                // Try direct creation
                create_tables_directly();
            }
            
            // Clear the transient to check tables again
            delete_transient('members_subscriptions_tables_checked');
            
            // Redirect back
            wp_redirect(add_query_arg(['members_tables_created' => '1', 'members_force_table_check' => '1'], admin_url('index.php')));
            exit;
        });
        
        // Register action to force recreate all tables
        add_action('admin_post_members_force_recreate_tables', function() {
            // Verify nonce
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'members_force_recreate')) {
                wp_die('Security check failed.');
            }
            
            // Force recreation of all tables
            $result = verify_database_tables(true);
            
            // Clear the transient to check tables again
            delete_transient('members_subscriptions_tables_checked');
            
            // Redirect back
            wp_redirect(add_query_arg(['members_tables_recreated' => '1', 'members_force_table_check' => '1'], admin_url('index.php')));
            exit;
        });
        
        // Add debug tools page action
        add_action('admin_post_members_debug_tables', function() {
            // Verify nonce
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'members_debug_tables')) {
                wp_die('Security check failed.');
            }
            
            // Get all tables with structure
            global $wpdb;
            $debug_info = [];
            
            $required_tables = [
                get_subscriptions_table_name(),
                get_transactions_table_name(),
                get_transactions_meta_table_name(),
                get_products_meta_table_name(),
            ];
            
            foreach ($required_tables as $table) {
                $debug_info[$table] = [
                    'exists' => (bool)$wpdb->get_var("SHOW TABLES LIKE '$table'"),
                    'columns' => [],
                    'records' => 0,
                    'errors' => [],
                ];
                
                if ($debug_info[$table]['exists']) {
                    try {
                        $columns = $wpdb->get_results("DESCRIBE $table", ARRAY_A);
                        $debug_info[$table]['columns'] = $columns;
                        
                        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                        $debug_info[$table]['records'] = (int)$count;
                        
                        // Get a sample record
                        if ($count > 0) {
                            $sample = $wpdb->get_row("SELECT * FROM $table LIMIT 1", ARRAY_A);
                            $debug_info[$table]['sample'] = $sample;
                        }
                    } catch (\Exception $e) {
                        $debug_info[$table]['errors'][] = $e->getMessage();
                    }
                }
            }
            
            // Output as JSON
            header('Content-Type: application/json');
            echo json_encode($debug_info, JSON_PRETTY_PRINT);
            exit;
        });
    } else {
        // No issues detected, clear any stored issues
        delete_option('members_subscriptions_db_issues');
    }
    
    // Add notice for successful table creation/recreation
    add_action('admin_notices', function() {
        if (isset($_GET['members_tables_created'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>Members Subscriptions:</strong> Attempted to create missing database tables.</p>
                <?php if (isset($_GET['members_force_table_check'])): ?>
                    <p>The system will automatically check if the tables were created successfully. If you still see error notices, please try the "Force Recreation" option or contact support.</p>
                <?php endif; ?>
            </div>
            <?php
        }
        
        if (isset($_GET['members_tables_recreated'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>Members Subscriptions:</strong> Attempted to recreate all database tables.</p>
                <?php if (isset($_GET['members_force_table_check'])): ?>
                    <p>The system will automatically check if the tables were recreated successfully. If you still see error notices, you may need to deactivate and reactivate the plugin, or contact support.</p>
                <?php endif; ?>
            </div>
            <?php
        }
    });
}

// Hook the admin notice function
add_action('admin_init', __NAMESPACE__ . '\maybe_add_database_admin_notice');

/**
 * Run a database operation with auto-verification of tables
 * If tables don't exist, it will attempt to create them before running the operation
 * 
 * @param callable $callback The database operation to run
 * @param array $args Arguments to pass to the callback
 * @param mixed $default Default value to return if operation fails
 * @param int $max_retries Maximum number of retry attempts (default: 2)
 * @return mixed Result of callback or default value on failure
 */
function db_operation_with_verification($callback, $args = [], $default = false, $max_retries = 2) {
    global $wpdb;
    
    // Check if callback is valid
    if (!is_callable($callback)) {
        error_log('Members Subscriptions: Invalid callback in db_operation_with_verification');
        return $default;
    }
    
    // Track retry attempts
    $attempt = 0;
    $last_error = '';
    
    // First, verify that tables exist before attempting the operation
    if (!verify_database_tables()) {
        error_log('Members Subscriptions: Tables do not exist or could not be created initially in db_operation_with_verification');
        // Continue anyway - we'll try to handle errors below
    }
    
    // Try the operation with retries
    while ($attempt <= $max_retries) {
        try {
            // Clear any previous errors
            $wpdb->last_error = '';
            
            // Try to run the callback
            $result = call_user_func_array($callback, $args);
            
            // If error occurs due to missing table
            if (!empty($wpdb->last_error)) {
                $last_error = $wpdb->last_error;
                
                if (strpos($wpdb->last_error, "doesn't exist") !== false || 
                    strpos($wpdb->last_error, "doesn't exist") !== false ||
                    strpos($wpdb->last_error, "no such table") !== false ||
                    strpos($wpdb->last_error, "Unknown table") !== false ||
                    strpos($wpdb->last_error, "Table") !== false) {
                        
                    error_log('Members Subscriptions: Table missing error detected on attempt ' . ($attempt + 1) . ': ' . $wpdb->last_error);
                    
                    // Force recreation on subsequent attempts
                    $force = ($attempt > 0);
                    
                    // Verify and create tables again
                    $tables_created = verify_database_tables($force);
                    
                    if (!$tables_created) {
                        error_log('Members Subscriptions: Tables still not created after verification attempt ' . ($attempt + 1));
                        
                        // Try the specific method that's most likely to succeed
                        if ($attempt == 0) {
                            error_log('Members Subscriptions: Trying direct table creation');
                            create_tables_directly();
                        } else {
                            // On second+ attempt, try to drop and recreate the specific table mentioned in the error
                            $error_message = $wpdb->last_error;
                            
                            // Extract table name from error message
                            if (preg_match("/'([^']+)'/", $error_message, $matches)) {
                                $problematic_table = $matches[1];
                                error_log('Members Subscriptions: Attempting to recreate specific problematic table: ' . $problematic_table);
                                
                                try {
                                    // Direct drop and recreate of the specific table
                                    $wpdb->query("DROP TABLE IF EXISTS $problematic_table");
                                    
                                    // Get the table creation SQL
                                    $table_sql = '';
                                    if (strpos($problematic_table, 'subscriptions') !== false) {
                                        $table_sql = get_subscriptions_table_sql();
                                    } elseif (strpos($problematic_table, 'transactions_meta') !== false) {
                                        $table_sql = get_transactions_meta_table_sql();
                                    } elseif (strpos($problematic_table, 'transactions') !== false) {
                                        $table_sql = get_transactions_table_sql();
                                    } elseif (strpos($problematic_table, 'products_meta') !== false) {
                                        $table_sql = get_products_meta_table_sql();
                                    }
                                    
                                    if (!empty($table_sql)) {
                                        $wpdb->query($table_sql);
                                        error_log('Members Subscriptions: Recreated specific table: ' . $problematic_table);
                                    }
                                } catch (\Exception $e) {
                                    error_log('Members Subscriptions: Error recreating specific table: ' . $e->getMessage());
                                }
                            }
                        }
                    }
                    
                    // Increment the attempt counter and try again in the next loop iteration
                    $attempt++;
                    continue;
                } else {
                    // Different kind of database error, just log it
                    error_log('Members Subscriptions: Database error in operation: ' . $wpdb->last_error);
                    // But still return the result, as it might be valid despite the error
                    return $result;
                }
            }
            
            // If we got here with no errors, return the successful result
            return $result;
            
        } catch (\Exception $e) {
            // Log the error
            $last_error = $e->getMessage();
            error_log('Members Subscriptions: Exception in db_operation_with_verification attempt ' . ($attempt + 1) . ': ' . $e->getMessage());
            
            // Try to create tables before trying again
            verify_database_tables(true);
            
            // Increment attempt counter
            $attempt++;
            
            // Don't continue if we've exhausted our retries
            if ($attempt > $max_retries) {
                error_log('Members Subscriptions: Maximum retry attempts reached');
                break;
            }
        }
    }
    
    // If we get here, all attempts failed
    error_log('Members Subscriptions: All database operation attempts failed. Last error: ' . $last_error);
    
    // Fallback to user meta approach
    if (is_array($args) && !empty($args) && isset($args[0])) {
        // If the first argument is a user ID, try to get data from user meta
        $potential_user_id = $args[0];
        if (is_numeric($potential_user_id)) {
            $user_id = intval($potential_user_id);
            error_log('Members Subscriptions: Attempting to fall back to user meta for user ID: ' . $user_id);
            
            // Try to determine operation type from the callback and return appropriate user meta
            $callback_string = '';
            if (is_array($callback)) {
                $callback_string = is_object($callback[0]) ? get_class($callback[0]) : (string)$callback[0];
                $callback_string .= '->' . $callback[1];
            } elseif (is_string($callback)) {
                $callback_string = $callback;
            }
            
            // Based on callback name, return appropriate user meta
            if (strpos($callback_string, 'get_subscription') !== false) {
                $subscription_data = get_user_meta($user_id, '_members_subscription_data', true);
                if (!empty($subscription_data)) {
                    error_log('Members Subscriptions: Found subscription data in user meta');
                    return (object)$subscription_data;
                }
            } elseif (strpos($callback_string, 'get_transaction') !== false) {
                $transaction_data = get_user_meta($user_id, '_members_transaction_data', true);
                if (!empty($transaction_data)) {
                    error_log('Members Subscriptions: Found transaction data in user meta');
                    return (object)$transaction_data;
                }
            } elseif (strpos($callback_string, 'get_subscriptions') !== false) {
                $subscription_data = get_user_meta($user_id, '_members_subscription_data', true);
                if (!empty($subscription_data)) {
                    error_log('Members Subscriptions: Found subscription data in user meta');
                    return [(object)$subscription_data];
                }
            } elseif (strpos($callback_string, 'get_transactions') !== false) {
                $transaction_data = get_user_meta($user_id, '_members_transaction_data', true);
                if (!empty($transaction_data)) {
                    error_log('Members Subscriptions: Found transaction data in user meta');
                    return [(object)$transaction_data];
                }
            }
        }
    }
    
    // Final fallback - check global site options
    if (strpos($last_error, 'subscription') !== false) {
        $all_subscriptions = get_option('members_subscription_users', []);
        if (!empty($all_subscriptions)) {
            error_log('Members Subscriptions: Found subscriptions in global option');
            return $default; // Return default, caller should check global option
        }
    }
    
    // All fallbacks failed, return default
    return $default;
}

/**
 * Get SQL for creating the subscriptions table
 *
 * @return string SQL for creating the subscriptions table
 */
function get_subscriptions_table_sql() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    return "CREATE TABLE " . get_subscriptions_table_name() . " (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        product_id bigint(20) NOT NULL,
        gateway varchar(50) NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'pending',
        subscr_id varchar(100) NOT NULL,
        trial tinyint(1) NOT NULL DEFAULT 0,
        trial_days int(11) NOT NULL DEFAULT 0,
        trial_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        trial_tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        trial_total decimal(10,2) NOT NULL DEFAULT 0.00,
        period_type varchar(20) NOT NULL DEFAULT 'month',
        period int(11) NOT NULL DEFAULT 1,
        price decimal(10,2) NOT NULL DEFAULT 0.00,
        tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        tax_rate decimal(10,2) NOT NULL DEFAULT 0.00,
        tax_desc varchar(255) NOT NULL DEFAULT '',
        total decimal(10,2) NOT NULL DEFAULT 0.00,
        cc_last4 varchar(4) NOT NULL DEFAULT '',
        cc_exp_month varchar(2) NOT NULL DEFAULT '',
        cc_exp_year varchar(4) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        expires_at datetime DEFAULT NULL,
        renewal_count int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY product_id (product_id),
        KEY status (status),
        KEY gateway (gateway)
    ) $charset_collate;";
}

/**
 * Get SQL for creating the transactions table
 *
 * @return string SQL for creating the transactions table
 */
function get_transactions_table_sql() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    return "CREATE TABLE " . get_transactions_table_name() . " (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        product_id bigint(20) NOT NULL,
        amount decimal(10,2) NOT NULL DEFAULT 0.00,
        total decimal(10,2) NOT NULL DEFAULT 0.00,
        tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        tax_rate decimal(10,2) NOT NULL DEFAULT 0.00,
        tax_desc varchar(255) NOT NULL DEFAULT '',
        trans_num varchar(100) NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'pending',
        txn_type varchar(50) NOT NULL DEFAULT 'payment',
        gateway varchar(50) NOT NULL,
        created_at datetime NOT NULL,
        expires_at datetime DEFAULT NULL,
        subscription_id bigint(20) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY product_id (product_id),
        KEY status (status),
        KEY gateway (gateway),
        KEY subscription_id (subscription_id)
    ) $charset_collate;";
}

/**
 * Get SQL for creating the products meta table
 *
 * @return string SQL for creating the products meta table
 */
function get_products_meta_table_sql() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    return "CREATE TABLE " . get_products_meta_table_name() . " (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        product_id bigint(20) NOT NULL,
        meta_key varchar(255) NOT NULL,
        meta_value longtext NOT NULL,
        PRIMARY KEY  (id),
        KEY product_id (product_id),
        KEY meta_key (meta_key(191))
    ) $charset_collate;";
}

/**
 * Get SQL for creating the transactions meta table
 *
 * @return string SQL for creating the transactions meta table
 */
function get_transactions_meta_table_sql() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    return "CREATE TABLE " . get_transactions_meta_table_name() . " (
        meta_id bigint(20) NOT NULL AUTO_INCREMENT,
        transaction_id bigint(20) NOT NULL,
        meta_key varchar(255) NOT NULL,
        meta_value longtext NOT NULL,
        PRIMARY KEY  (meta_id),
        KEY transaction_id (transaction_id),
        KEY meta_key (meta_key(191))
    ) $charset_collate;";
}