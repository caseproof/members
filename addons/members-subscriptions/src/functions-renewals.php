<?php

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Set up renewal cron jobs
 */
function schedule_renewal_events() {
    // Daily renewal check
    if (!wp_next_scheduled('members_subscriptions_daily_renewals')) {
        wp_schedule_event(time(), 'daily', 'members_subscriptions_daily_renewals');
    }
    
    // Upcoming renewal notifications
    if (!wp_next_scheduled('members_subscriptions_renewal_reminders')) {
        wp_schedule_event(time(), 'daily', 'members_subscriptions_renewal_reminders');
    }
}
add_action('init', 'Members\Subscriptions\schedule_renewal_events');

/**
 * Clean up renewal cron jobs on deactivation
 */
function clear_renewal_events() {
    wp_clear_scheduled_hook('members_subscriptions_daily_renewals');
    wp_clear_scheduled_hook('members_subscriptions_renewal_reminders');
}
add_action('members_subscriptions_deactivate', 'Members\Subscriptions\clear_renewal_events');

/**
 * Process daily renewals
 */
function process_daily_renewals() {
    global $wpdb;
    
    // Current time for comparison
    $now = current_time('mysql');
    
    // Get subscriptions that need renewal
    $table = get_subscriptions_table_name();
    $subscriptions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table 
             WHERE status = 'active' 
             AND (gateway = 'manual' OR gateway = '') 
             AND next_payment_at <= %s
             AND next_payment_at != '0000-00-00 00:00:00'
             AND next_payment_at IS NOT NULL",
            $now
        )
    );
    
    if (empty($subscriptions)) {
        return;
    }
    
    foreach ($subscriptions as $subscription) {
        // Process manual renewal
        process_manual_renewal($subscription);
    }
}
add_action('members_subscriptions_daily_renewals', 'Members\Subscriptions\process_daily_renewals');

/**
 * Process manual renewal for a subscription
 * 
 * @param object $subscription The subscription object
 * @return int|false The transaction ID if successful, false otherwise
 */
function process_manual_renewal($subscription) {
    // Get product details
    $product = get_post($subscription->product_id);
    if (!$product || $product->post_type !== 'members_product') {
        return false;
    }
    
    // Calculate next renewal date
    $next_payment_date = calculate_subscription_expiration(
        $subscription->period, 
        $subscription->period_type, 
        $subscription->next_payment_at ?: current_time('mysql')
    );
    
    // Create transaction for the renewal
    $transaction_data = [
        'user_id' => $subscription->user_id,
        'product_id' => $subscription->product_id,
        'amount' => $subscription->price,
        'total' => $subscription->total,
        'tax_amount' => $subscription->tax_amount,
        'tax_rate' => $subscription->tax_rate,
        'tax_desc' => $subscription->tax_desc,
        'trans_num' => 'manual-renewal-' . $subscription->id . '-' . time(),
        'status' => 'complete', // For manual renewals, set as complete
        'txn_type' => 'renewal',
        'gateway' => $subscription->gateway,
        'subscription_id' => $subscription->id,
    ];
    
    $transaction_id = create_transaction($transaction_data);
    
    if (!$transaction_id) {
        return false;
    }
    
    // Store renewal information in transaction meta
    update_transaction_meta($transaction_id, '_is_renewal', '1');
    update_transaction_meta($transaction_id, '_renewal_num', $subscription->renewal_count + 1);
    update_transaction_meta($transaction_id, '_period_start', $subscription->next_payment_at);
    update_transaction_meta($transaction_id, '_period_end', $next_payment_date);
    
    // Update subscription
    update_subscription($subscription->id, [
        'last_payment_at' => current_time('mysql'),
        'next_payment_at' => $next_payment_date,
        'expires_at' => $next_payment_date,
        'renewal_count' => $subscription->renewal_count + 1,
    ]);
    
    // Send renewal notification
    if (function_exists('Members\Subscriptions\send_renewal_receipt_email')) {
        $transaction = get_transaction($transaction_id);
        send_renewal_receipt_email($transaction);
    }
    
    // Trigger action for manual renewal
    do_action('members_subscription_manual_renewal', $subscription, $transaction_id);
    
    return $transaction_id;
}

/**
 * Process renewal reminders
 */
function process_renewal_reminders() {
    global $wpdb;
    
    // Get reminder periods (in days)
    $reminder_days = apply_filters('members_subscriptions_renewal_reminder_days', [1, 3, 7, 14]);
    
    foreach ($reminder_days as $days) {
        // Calculate the date to check for renewals
        $reminder_date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        
        // Get subscriptions with renewals coming up
        $table = get_subscriptions_table_name();
        $subscriptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table 
                 WHERE status = 'active' 
                 AND DATE(next_payment_at) = DATE(%s)
                 AND next_payment_at != '0000-00-00 00:00:00'
                 AND next_payment_at IS NOT NULL",
                $reminder_date
            )
        );
        
        if (empty($subscriptions)) {
            continue;
        }
        
        foreach ($subscriptions as $subscription) {
            // Skip if we've already sent this reminder
            $reminder_key = "renewal_reminder_{$days}_days";
            $already_sent = get_subscription_meta($subscription->id, $reminder_key, false);
            
            if ($already_sent) {
                continue;
            }
            
            // Send reminder email
            if (function_exists('Members\Subscriptions\send_renewal_reminder_email')) {
                send_renewal_reminder_email($subscription, $days);
            }
            
            // Mark reminder as sent
            update_subscription_meta($subscription->id, $reminder_key, current_time('mysql'));
            
            // Trigger action for renewal reminder
            do_action('members_subscription_renewal_reminder', $subscription, $days);
        }
    }
}
add_action('members_subscriptions_renewal_reminders', 'Members\Subscriptions\process_renewal_reminders');

/**
 * Get subscription meta data
 *
 * @param int    $subscription_id Subscription ID
 * @param string $key            Meta key
 * @param mixed  $default        Default value
 * @return mixed Meta value
 */
function get_subscription_meta($subscription_id, $key, $default = '') {
    global $wpdb;
    
    $table = $wpdb->prefix . 'members_subscriptions_meta';
    
    $value = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT meta_value FROM $table WHERE subscription_id = %d AND meta_key = %s",
            $subscription_id,
            $key
        )
    );
    
    if ($value === null) {
        return $default;
    }
    
    return maybe_unserialize($value);
}

/**
 * Update subscription meta data
 *
 * @param int    $subscription_id Subscription ID
 * @param string $key            Meta key
 * @param mixed  $value          Meta value
 * @return bool Whether the update was successful
 */
function update_subscription_meta($subscription_id, $key, $value) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'members_subscriptions_meta';
    
    // Check if meta exists
    $meta_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT meta_id FROM $table WHERE subscription_id = %d AND meta_key = %s",
            $subscription_id,
            $key
        )
    );
    
    if ($meta_id) {
        // Update existing meta
        return $wpdb->update(
            $table,
            ['meta_value' => maybe_serialize($value)],
            ['meta_id' => $meta_id],
            ['%s'],
            ['%d']
        ) !== false;
    } else {
        // Insert new meta
        return $wpdb->insert(
            $table,
            [
                'subscription_id' => $subscription_id,
                'meta_key'       => $key,
                'meta_value'     => maybe_serialize($value),
            ],
            ['%d', '%s', '%s']
        ) !== false;
    }
}

/**
 * Delete subscription meta data
 *
 * @param int    $subscription_id Subscription ID
 * @param string $key            Meta key
 * @return bool Whether the deletion was successful
 */
function delete_subscription_meta($subscription_id, $key) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'members_subscriptions_meta';
    
    return $wpdb->delete(
        $table,
        [
            'subscription_id' => $subscription_id,
            'meta_key'       => $key,
        ],
        ['%d', '%s']
    ) !== false;
}