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
        return false;
    }
    
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
        return false;
    }
    
    return $wpdb->insert_id;
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
    
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM " . get_subscriptions_table_name() . " WHERE id = %d",
            $subscription_id
        )
    );
}

/**
 * Get subscriptions from the database
 *
 * @param array $args Query arguments
 * @return array Array of subscription objects
 */
function get_subscriptions($args = []) {
    global $wpdb;
    
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
        return false;
    }
    
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
        return false;
    }
    
    return $wpdb->insert_id;
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