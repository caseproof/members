<?php

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Get subscription period options
 *
 * @return array
 */
function get_subscription_period_options() {
    return [
        'day'   => __('Day(s)', 'members'),
        'week'  => __('Week(s)', 'members'),
        'month' => __('Month(s)', 'members'),
        'year'  => __('Year(s)', 'members'),
    ];
}

/**
 * Format subscription period for display
 *
 * @param int    $period
 * @param string $period_type
 * @return string
 */
function format_subscription_period($period, $period_type) {
    $period_options = get_subscription_period_options();
    
    if (!isset($period_options[$period_type])) {
        $period_type = 'month'; // Default fallback
    }
    
    $period_label = $period_options[$period_type];
    
    if ($period == 1) {
        // For singular, remove the (s) from the label
        $period_label = str_replace('(s)', '', $period_label);
    }
    
    return sprintf(_n('%d %s', '%d %s', $period, 'members'), $period, $period_label);
}

/**
 * Get formatted subscription status
 *
 * @param string $status
 * @return string
 */
function get_formatted_subscription_status($status) {
    $statuses = [
        'active'    => __('Active', 'members'),
        'pending'   => __('Pending', 'members'),
        'cancelled' => __('Cancelled', 'members'),
        'expired'   => __('Expired', 'members'),
        'suspended' => __('Suspended', 'members'),
        'failed'    => __('Failed', 'members'),
    ];
    
    return isset($statuses[$status]) ? $statuses[$status] : ucfirst($status);
}

/**
 * Get user subscriptions
 *
 * @param int $user_id
 * @return array
 */
function get_user_subscriptions($user_id) {
    global $wpdb;
    
    // Try to get subscriptions from database table
    $table_name = get_subscriptions_table_name();
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if ($table_exists) {
        $subscriptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            )
        );
        
        if (!empty($subscriptions)) {
            return $subscriptions;
        }
    }
    
    // Fallback: Try to get from user meta
    $subscriptions = get_user_meta($user_id, '_members_subscriptions', true);
    
    if (is_array($subscriptions) && !empty($subscriptions)) {
        return array_map(function($subscription) {
            return (object) $subscription;
        }, $subscriptions);
    }
    
    // Super fallback: Check global option
    $all_subscriptions = get_option('members_subscription_users', []);
    $user_subscriptions = [];
    
    if (is_array($all_subscriptions) && isset($all_subscriptions[$user_id])) {
        $sub_data = $all_subscriptions[$user_id];
        
        // Convert to expected format
        $subscription = (object) [
            'id' => uniqid('sub-'),
            'user_id' => $user_id,
            'product_id' => $sub_data['product_id'],
            'gateway' => 'manual',
            'status' => $sub_data['active'] ? 'active' : 'expired',
            'subscr_id' => $sub_data['subscription_id'] ?? '',
            'period_type' => 'month',
            'period' => 1,
            'price' => 0,
            'total' => 0,
            'created_at' => $sub_data['created_at'] ?? current_time('mysql'),
            'expires_at' => null,
        ];
        
        $user_subscriptions[] = $subscription;
    }
    
    return $user_subscriptions;
}

/**
 * Check if a user has access to a product
 *
 * @param int $user_id
 * @param int $product_id
 * @return bool
 */
function user_has_access($user_id, $product_id) {
    if (!$user_id || !$product_id) {
        return false;
    }
    
    // Get membership roles for this product
    $product_roles = [];
    if (function_exists('\\Members\\Subscriptions\\get_product_meta')) {
        $product_roles = get_product_meta($product_id, '_membership_roles', []);
    } else {
        $product_roles = get_post_meta($product_id, '_membership_roles', true);
    }
    
    if (!is_array($product_roles)) {
        if (!empty($product_roles)) {
            $product_roles = [$product_roles]; // Convert string to array
        } else {
            $product_roles = [];
        }
    }
    
    // If no roles required, user doesn't have access
    if (empty($product_roles)) {
        return false;
    }
    
    // Check if user has any of the required roles
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    foreach ($product_roles as $role) {
        if (in_array($role, (array) $user->roles)) {
            return true;
        }
    }
    
    // Also check if user has an active subscription to this product
    $subscriptions = get_user_subscriptions($user_id);
    
    foreach ($subscriptions as $subscription) {
        if ($subscription->product_id == $product_id && $subscription->status === 'active') {
            return true;
        }
    }
    
    return false;
}

/**
 * Calculate subscription expiration date
 *
 * @param int    $period
 * @param string $period_type
 * @param string $from_date Optional. Date to calculate from. Defaults to current time.
 * @return string MySQL date
 */
function calculate_subscription_expiration($period, $period_type, $from_date = '') {
    if (empty($from_date)) {
        $from_date = current_time('mysql');
    }
    
    $timestamp = strtotime($from_date);
    
    switch ($period_type) {
        case 'day':
            $expiration = strtotime("+{$period} days", $timestamp);
            break;
        case 'week':
            $expiration = strtotime("+{$period} weeks", $timestamp);
            break;
        case 'month':
            $expiration = strtotime("+{$period} months", $timestamp);
            break;
        case 'year':
            $expiration = strtotime("+{$period} years", $timestamp);
            break;
        default:
            $expiration = strtotime("+{$period} months", $timestamp);
            break;
    }
    
    return date('Y-m-d H:i:s', $expiration);
}

/**
 * Process subscription renewal
 *
 * @param int $subscription_id
 * @return bool|array Success status or error array
 */
function process_subscription_renewal($subscription_id) {
    // Get subscription
    $subscription = get_subscription($subscription_id);
    
    if (!$subscription) {
        return ['success' => false, 'message' => __('Subscription not found.', 'members')];
    }
    
    // Check if subscription is active
    if ($subscription->status !== 'active') {
        return ['success' => false, 'message' => __('Cannot renew inactive subscription.', 'members')];
    }
    
    // Calculate new expiration date
    $expires_at = calculate_subscription_expiration(
        $subscription->period,
        $subscription->period_type,
        $subscription->expires_at ?: current_time('mysql')
    );
    
    // Create transaction for renewal payment
    $transaction_data = [
        'user_id'         => $subscription->user_id,
        'product_id'      => $subscription->product_id,
        'amount'          => $subscription->price,
        'total'           => $subscription->total,
        'tax_amount'      => $subscription->tax_amount ?? 0,
        'tax_rate'        => $subscription->tax_rate ?? 0,
        'tax_desc'        => $subscription->tax_desc ?? '',
        'trans_num'       => uniqid('renewal-'),
        'status'          => 'complete',
        'txn_type'        => 'renewal',
        'gateway'         => $subscription->gateway,
        'subscription_id' => $subscription_id,
    ];
    
    $transaction_id = create_transaction($transaction_data);
    
    if (!$transaction_id) {
        return ['success' => false, 'message' => __('Error recording renewal transaction.', 'members')];
    }
    
    // Update subscription data
    $update_data = [
        'expires_at'     => $expires_at,
        'renewal_count'  => (int)$subscription->renewal_count + 1,
    ];
    
    $updated = update_subscription($subscription_id, $update_data);
    
    if (!$updated) {
        return ['success' => false, 'message' => __('Error updating subscription expiration.', 'members')];
    }
    
    // Update subscription in user's roles if needed
    $product_roles = get_product_meta($subscription->product_id, '_membership_roles', []);
    
    if (!empty($product_roles)) {
        $user = get_userdata($subscription->user_id);
        
        if ($user) {
            foreach ($product_roles as $role) {
                if (!in_array($role, (array)$user->roles)) {
                    $user->add_role($role);
                }
            }
        }
    }
    
    // Trigger action for renewal
    do_action('members_subscription_renewed', $subscription_id, $transaction_id);
    
    return [
        'success'         => true,
        'subscription_id' => $subscription_id,
        'transaction_id'  => $transaction_id,
        'expires_at'      => $expires_at,
        'message'         => __('Subscription renewed successfully.', 'members'),
    ];
}

/**
 * Cancel a subscription
 *
 * @param int $subscription_id
 * @return bool Success status
 */
function cancel_subscription($subscription_id) {
    // Get subscription
    $subscription = get_subscription($subscription_id);
    
    if (!$subscription) {
        return false;
    }
    
    // Check if subscription is already cancelled
    if ($subscription->status === 'cancelled') {
        return true; // Already cancelled
    }
    
    // Update subscription status
    $updated = update_subscription($subscription_id, ['status' => 'cancelled']);
    
    if (!$updated) {
        return false;
    }
    
    // Remove membership roles if applicable
    $product_roles = get_product_meta($subscription->product_id, '_membership_roles', []);
    
    if (!empty($product_roles)) {
        $user = get_userdata($subscription->user_id);
        
        if ($user) {
            foreach ($product_roles as $role) {
                // Check if user has other active subscriptions with the same role
                $other_subscriptions = get_subscriptions([
                    'user_id' => $subscription->user_id,
                    'status'  => 'active',
                ]);
                
                $keep_role = false;
                foreach ($other_subscriptions as $other) {
                    if ($other->id == $subscription_id) {
                        continue; // Skip the subscription being cancelled
                    }
                    
                    $other_roles = get_product_meta($other->product_id, '_membership_roles', []);
                    
                    if (in_array($role, $other_roles)) {
                        $keep_role = true;
                        break;
                    }
                }
                
                if (!$keep_role) {
                    $user->remove_role($role);
                }
            }
        }
    }
    
    // Trigger action for cancellation
    do_action('members_subscription_cancelled', $subscription_id);
    
    return true;
}

/**
 * Activate a subscription
 *
 * @param int $subscription_id
 * @return bool Success status
 */
function activate_subscription($subscription_id) {
    // Get subscription
    $subscription = get_subscription($subscription_id);
    
    if (!$subscription) {
        return false;
    }
    
    // Update subscription status
    $updated = update_subscription($subscription_id, ['status' => 'active']);
    
    if (!$updated) {
        return false;
    }
    
    // Add membership roles
    $product_roles = get_product_meta($subscription->product_id, '_membership_roles', []);
    
    if (!empty($product_roles)) {
        $user = get_userdata($subscription->user_id);
        
        if ($user) {
            foreach ($product_roles as $role) {
                $user->add_role($role);
            }
        }
    }
    
    // Trigger action for activation
    do_action('members_subscription_activated', $subscription_id);
    
    return true;
}

/**
 * Check for expiring/expired subscriptions
 */
function check_subscription_status() {
    global $wpdb;
    
    // Get subscriptions table
    $table_name = get_subscriptions_table_name();
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if (!$table_exists) {
        return;
    }
    
    // Get current time
    $now = current_time('mysql');
    
    // Get active subscriptions that have expired
    $expired_subscriptions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE status = 'active' 
            AND expires_at IS NOT NULL 
            AND expires_at != '0000-00-00 00:00:00' 
            AND expires_at < %s",
            $now
        )
    );
    
    foreach ($expired_subscriptions as $subscription) {
        // Handle based on gateway type
        if ($subscription->gateway === 'manual') {
            // For manual gateway, simply expire the subscription
            update_subscription($subscription->id, ['status' => 'expired']);
            
            // Remove membership roles
            $product_roles = get_product_meta($subscription->product_id, '_membership_roles', []);
            
            if (!empty($product_roles)) {
                $user = get_userdata($subscription->user_id);
                
                if ($user) {
                    foreach ($product_roles as $role) {
                        // Check if user has other active subscriptions with the same role
                        $keep_role = false;
                        $other_subscriptions = get_user_subscriptions($subscription->user_id);
                        
                        foreach ($other_subscriptions as $other) {
                            if ($other->id == $subscription->id || $other->status !== 'active') {
                                continue;
                            }
                            
                            $other_roles = get_product_meta($other->product_id, '_membership_roles', []);
                            
                            if (in_array($role, $other_roles)) {
                                $keep_role = true;
                                break;
                            }
                        }
                        
                        if (!$keep_role) {
                            $user->remove_role($role);
                        }
                    }
                }
            }
            
            // Trigger action
            do_action('members_subscription_expired', $subscription->id);
        } else {
            // For other gateways, attempt renewal through the gateway
            // This is handled by the respective gateway's webhook usually
            
            // Just in case, trigger an action that the gateway can hook into
            do_action('members_subscription_renewal_needed', $subscription->id);
        }
    }
    
    // Get subscriptions that are expiring soon (in the next 3 days)
    $soon_to_expire = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE status = 'active' 
            AND expires_at IS NOT NULL 
            AND expires_at != '0000-00-00 00:00:00' 
            AND expires_at < %s 
            AND expires_at > %s",
            date('Y-m-d H:i:s', strtotime('+3 days')),
            $now
        )
    );
    
    foreach ($soon_to_expire as $subscription) {
        // Trigger action for soon to expire subscriptions
        // Email notifications can hook into this
        do_action('members_subscription_expiring_soon', $subscription->id);
    }
}

// Register cron event to check subscriptions daily
add_action('init', function() {
    if (!wp_next_scheduled('members_check_subscriptions')) {
        wp_schedule_event(time(), 'daily', 'members_check_subscriptions');
    }
});

// Hook into the cron event
add_action('members_check_subscriptions', 'Members\\Subscriptions\\check_subscription_status');

/**
 * Get all subscription statuses
 *
 * @return array
 */
function get_subscription_statuses() {
    return [
        'active'    => __('Active', 'members'),
        'pending'   => __('Pending', 'members'),
        'cancelled' => __('Cancelled', 'members'),
        'expired'   => __('Expired', 'members'),
        'suspended' => __('Suspended', 'members'),
        'failed'    => __('Failed', 'members'),
    ];
}

/**
 * Get all transaction statuses
 *
 * @return array
 */
function get_transaction_statuses() {
    return [
        'complete'  => __('Complete', 'members'),
        'pending'   => __('Pending', 'members'),
        'refunded'  => __('Refunded', 'members'),
        'failed'    => __('Failed', 'members'),
    ];
}

/**
 * Get all transaction types
 *
 * @return array
 */
function get_transaction_types() {
    return [
        'payment'   => __('Payment', 'members'),
        'refund'    => __('Refund', 'members'),
        'renewal'   => __('Renewal', 'members'),
    ];
}