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
 * @return bool True if all tables exist or were created successfully
 */
function verify_database_tables() {
    global $wpdb;
    
    // Tables to check
    $required_tables = [
        get_subscriptions_table_name(),
        get_transactions_table_name(),
        get_transactions_meta_table_name(),
        get_products_meta_table_name(),
    ];
    
    $missing_tables = [];
    
    // Check if each table exists
    foreach ($required_tables as $table) {
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        
        if (!$table_exists) {
            $missing_tables[] = $table;
        }
    }
    
    // If all tables exist, return true
    if (empty($missing_tables)) {
        return true;
    }
    
    error_log('Members Subscriptions: Missing database tables: ' . implode(', ', $missing_tables));
    
    try {
        // Try to include migration manager and migrations to create tables
        $migration_files = [
            dirname(__FILE__) . '/migrations/class-migration.php',
            dirname(__FILE__) . '/migrations/class-migration-manager.php',
            dirname(__FILE__) . '/migrations/class-migration-1-0-0.php',
            dirname(__FILE__) . '/migrations/class-migration-1-0-1.php',
            dirname(__FILE__) . '/migrations/class-migration-1-0-2.php',
        ];
        
        foreach ($migration_files as $file) {
            if (!file_exists($file)) {
                error_log('Members Subscriptions: Migration file not found: ' . $file);
                create_tables_directly();
                return check_tables_exist();
            }
            require_once $file;
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
        
        if (!$all_success) {
            error_log('Members Subscriptions: Migrations failed, attempting direct table creation');
            create_tables_directly();
        }
        
        return check_tables_exist();
    } catch (\Exception $e) {
        error_log('Members Subscriptions: Exception during table verification/creation: ' . $e->getMessage());
        // If migrations fail, try direct table creation
        create_tables_directly();
        return check_tables_exist();
    }
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
 * Adds an admin notice if database tables are missing
 */
function maybe_add_database_admin_notice() {
    global $wpdb;
    
    // Only run this check once per session
    if (get_transient('members_subscriptions_tables_checked')) {
        return;
    }
    
    // Set transient to prevent repeated checks
    set_transient('members_subscriptions_tables_checked', true, HOUR_IN_SECONDS);
    
    // Check if tables exist
    $missing_tables = [];
    $required_tables = [
        get_subscriptions_table_name(),
        get_transactions_table_name(),
        get_transactions_meta_table_name(),
        get_products_meta_table_name(),
    ];
    
    foreach ($required_tables as $table) {
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if (!$table_exists) {
            $missing_tables[] = $table;
        }
    }
    
    // If tables are missing, add an admin notice
    if (!empty($missing_tables)) {
        add_action('admin_notices', function() use ($missing_tables) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong>Members Subscriptions:</strong> Some required database tables are missing.</p>
                <p>Missing tables: <?php echo esc_html(implode(', ', $missing_tables)); ?></p>
                <p>This may cause subscription and transaction records to fail. Please try deactivating and reactivating the plugin.</p>
                <p>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=members_create_missing_tables'), 'members_create_tables')); ?>" class="button button-primary">
                        Attempt to Create Tables Now
                    </a>
                </p>
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
            wp_redirect(admin_url('index.php?members_tables_created=1'));
            exit;
        });
        
        // Add notice for successful table creation
        add_action('admin_notices', function() {
            if (isset($_GET['members_tables_created'])) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Members Subscriptions:</strong> Attempted to create missing database tables. Please check if they're now available.</p>
                </div>
                <?php
            }
        });
    }
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
 * @return mixed Result of callback or default value on failure
 */
function db_operation_with_verification($callback, $args = [], $default = false) {
    global $wpdb;
    
    // Check if callback is valid
    if (!is_callable($callback)) {
        error_log('Members Subscriptions: Invalid callback in db_operation_with_verification');
        return $default;
    }
    
    // First, verify that tables exist before attempting the operation
    if (!verify_database_tables()) {
        error_log('Members Subscriptions: Tables do not exist or could not be created in db_operation_with_verification');
        return $default;
    }
    
    try {
        // Try to run the callback
        $result = call_user_func_array($callback, $args);
        
        // If error occurs due to missing table
        if (!empty($wpdb->last_error)) {
            if (strpos($wpdb->last_error, "doesn't exist") !== false || 
                strpos($wpdb->last_error, "doesn't exist") !== false ||
                strpos($wpdb->last_error, "no such table") !== false) {
                    
                error_log('Members Subscriptions: Table missing error detected: ' . $wpdb->last_error);
                
                // Verify and create tables again
                $tables_created = verify_database_tables();
                
                if ($tables_created) {
                    error_log('Members Subscriptions: Tables created, retrying operation');
                    // Try again
                    $result = call_user_func_array($callback, $args);
                    
                    // If still failing, create tables using the direct method
                    if (!empty($wpdb->last_error)) {
                        error_log('Members Subscriptions: Operation still failing after table creation: ' . $wpdb->last_error);
                        create_tables_directly();
                        $result = call_user_func_array($callback, $args);
                    }
                } else {
                    error_log('Members Subscriptions: Failed to create tables, using direct method');
                    // Failed to create tables, try direct method
                    create_tables_directly();
                    $result = call_user_func_array($callback, $args);
                }
            } else {
                error_log('Members Subscriptions: Database error in operation: ' . $wpdb->last_error);
            }
        }
        
        return $result;
    } catch (\Exception $e) {
        // Log the error
        error_log('Members Subscriptions: Exception in db_operation_with_verification: ' . $e->getMessage());
        
        // Try to create tables before giving up
        create_tables_directly();
        
        try {
            // One more attempt
            return call_user_func_array($callback, $args);
        } catch (\Exception $e2) {
            error_log('Members Subscriptions: Final attempt failed: ' . $e2->getMessage());
            return $default;
        }
    }
}