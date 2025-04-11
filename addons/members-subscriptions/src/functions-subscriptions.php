<?php

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Get subscription
 *
 * @param int $subscription_id Subscription ID
 * @return object|null
 */
function get_subscription($subscription_id) {
    return \Members\Subscriptions\get_subscription($subscription_id);
}

/**
 * Get user subscriptions
 *
 * @param int $user_id User ID
 * @param array $args Additional arguments
 * @return array
 */
function get_user_subscriptions($user_id, $args = []) {
    $args['user_id'] = $user_id;
    return \Members\Subscriptions\get_subscriptions($args);
}

/**
 * Get active user subscriptions
 *
 * @param int $user_id User ID
 * @return array
 */
function get_active_user_subscriptions($user_id) {
    return \Members\Subscriptions\get_subscriptions([
        'user_id' => $user_id,
        'status' => 'active',
    ]);
}

/**
 * Check if user has an active subscription
 *
 * @param int $user_id User ID
 * @param int $product_id Optional product ID to check for specific product
 * @return bool
 */
function user_has_active_subscription($user_id, $product_id = 0) {
    $args = [
        'user_id' => $user_id,
        'status' => 'active',
    ];
    
    if (!empty($product_id)) {
        $args['product_id'] = $product_id;
    }
    
    return \Members\Subscriptions\count_subscriptions($args) > 0;
}

/**
 * Cancel subscription
 *
 * @param int $subscription_id Subscription ID
 * @return bool
 */
function cancel_subscription($subscription_id) {
    $subscription = \Members\Subscriptions\get_subscription($subscription_id);
    
    if (!$subscription) {
        return false;
    }
    
    // Get gateway
    $gateway_manager = \Members\Subscriptions\gateways\Gateway_Manager::get_instance();
    $gateway = $gateway_manager->get_gateway($subscription->gateway);
    
    // If gateway supports cancellation, try to cancel at gateway
    $cancelled_at_gateway = false;
    if ($gateway && method_exists($gateway, 'cancel_subscription')) {
        $cancelled_at_gateway = $gateway->cancel_subscription($subscription);
    }
    
    // Even if gateway cancellation fails, update local record
    return \Members\Subscriptions\update_subscription($subscription_id, [
        'status' => 'cancelled',
    ]);
}

/**
 * Process subscription renewal
 *
 * @param int $subscription_id Subscription ID
 * @return bool
 */
function process_subscription_renewal($subscription_id) {
    $subscription = \Members\Subscriptions\get_subscription($subscription_id);
    
    if (!$subscription || $subscription->status !== 'active') {
        return false;
    }
    
    // Get gateway
    $gateway_manager = \Members\Subscriptions\gateways\Gateway_Manager::get_instance();
    $gateway = $gateway_manager->get_gateway($subscription->gateway);
    
    // If gateway supports manual renewal, process it
    if ($gateway && method_exists($gateway, 'process_renewal')) {
        return $gateway->process_renewal($subscription);
    }
    
    return false;
}

/**
 * Get subscription transactions
 *
 * @param int $subscription_id Subscription ID
 * @return array
 */
function get_subscription_transactions($subscription_id) {
    return \Members\Subscriptions\get_transactions([
        'subscription_id' => $subscription_id,
    ]);
}

/**
 * Check if user has access to a specific product
 *
 * @param int $user_id User ID
 * @param int $product_id Product ID
 * @return bool
 */
function user_has_access($user_id, $product_id) {
    // If user has an active subscription for this product, they have access
    if (user_has_active_subscription($user_id, $product_id)) {
        return true;
    }
    
    // Check if user has a valid transaction for this product
    $transactions = \Members\Subscriptions\get_transactions([
        'user_id' => $user_id,
        'product_id' => $product_id,
        'status' => 'complete',
    ]);
    
    if (empty($transactions)) {
        return false;
    }
    
    // Check if any transaction is not expired
    foreach ($transactions as $transaction) {
        // Lifetime access
        if (empty($transaction->expires_at) || $transaction->expires_at === '0000-00-00 00:00:00') {
            return true;
        }
        
        // Not expired yet
        if (strtotime($transaction->expires_at) > time()) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get subscription statuses
 *
 * @return array
 */
function get_subscription_statuses() {
    return [
        'active' => __('Active', 'members'),
        'pending' => __('Pending', 'members'),
        'cancelled' => __('Cancelled', 'members'),
        'expired' => __('Expired', 'members'),
        'suspended' => __('Suspended', 'members'),
    ];
}

/**
 * Get formatted subscription status
 *
 * @param string $status Status key
 * @return string
 */
function get_formatted_subscription_status($status) {
    $statuses = get_subscription_statuses();
    return isset($statuses[$status]) ? $statuses[$status] : $status;
}

/**
 * Get subscription period options
 *
 * @return array
 */
function get_subscription_period_options() {
    return [
        'day' => __('Day(s)', 'members'),
        'week' => __('Week(s)', 'members'),
        'month' => __('Month(s)', 'members'),
        'year' => __('Year(s)', 'members'),
    ];
}

/**
 * Format subscription period
 *
 * @param int $period Period count
 * @param string $period_type Period type
 * @return string
 */
function format_subscription_period($period, $period_type) {
    $period_options = get_subscription_period_options();
    $period_label = isset($period_options[$period_type]) ? $period_options[$period_type] : $period_type;
    
    if ($period == 1) {
        // Remove the (s) for singular
        $period_label = str_replace('(s)', '', $period_label);
    }
    
    return sprintf('%d %s', $period, $period_label);
}

/**
 * Calculate subscription expiration date
 *
 * @param int $period Period count
 * @param string $period_type Period type
 * @param string|int $from_date Starting date (timestamp or MySQL date)
 * @return string MySQL date
 */
function calculate_subscription_expiration($period, $period_type, $from_date = null) {
    if (empty($from_date)) {
        $from_date = current_time('timestamp');
    } elseif (!is_numeric($from_date)) {
        $from_date = strtotime($from_date);
    }
    
    switch ($period_type) {
        case 'day':
            $expires = strtotime("+{$period} days", $from_date);
            break;
        case 'week':
            $expires = strtotime("+{$period} weeks", $from_date);
            break;
        case 'month':
            $expires = strtotime("+{$period} months", $from_date);
            break;
        case 'year':
            $expires = strtotime("+{$period} years", $from_date);
            break;
        default:
            $expires = strtotime("+{$period} days", $from_date);
    }
    
    return date('Y-m-d H:i:s', $expires);
}

/**
 * Get product by subscription ID
 * 
 * @param int $subscription_id
 * @return \WP_Post|null
 */
function get_product_by_subscription($subscription_id) {
    $subscription = get_subscription($subscription_id);
    
    if (!$subscription || empty($subscription->product_id)) {
        return null;
    }
    
    return get_post($subscription->product_id);
}

/**
 * Apply membership roles based on product
 * 
 * @param int $user_id
 * @param int $product_id
 * @return bool
 */
function apply_membership_role($user_id, $product_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    // Get roles from product meta
    $roles = get_product_meta($product_id, '_membership_roles', []);
    
    // Check for legacy single role if no roles array found
    if (empty($roles)) {
        $legacy_role = get_post_meta($product_id, '_membership_role', true);
        if (!empty($legacy_role)) {
            $roles = [$legacy_role];
        }
    }
    
    if (empty($roles)) {
        return false;
    }
    
    // Get all valid roles
    $valid_roles = array_keys(get_editable_roles());
    
    // Apply all roles
    $success = true;
    foreach ($roles as $role) {
        // Validate the role before applying it
        if (!empty($role) && in_array($role, $valid_roles)) {
            $user->add_role($role);
        } else {
            // Log invalid role attempt
            if (!empty($role)) {
                error_log(sprintf('Attempted to add invalid role: %s to user %d', esc_html($role), $user_id));
            }
        }
    }
    
    return $success;
}

/**
 * Remove membership roles based on product
 * 
 * @param int $user_id
 * @param int $product_id
 * @return bool
 */
function remove_membership_role($user_id, $product_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    // Get roles from product meta
    $roles_to_remove = get_product_meta($product_id, '_membership_roles', []);
    
    // Check for legacy single role if no roles array found
    if (empty($roles_to_remove)) {
        $legacy_role = get_post_meta($product_id, '_membership_role', true);
        if (!empty($legacy_role)) {
            $roles_to_remove = [$legacy_role];
        }
    }
    
    if (empty($roles_to_remove)) {
        return false;
    }
    
    // Get all valid roles
    $valid_roles = array_keys(get_editable_roles());
    
    // Filter to only include valid roles
    $roles_to_remove = array_filter($roles_to_remove, function($role) use ($valid_roles) {
        if (empty($role)) {
            return false;
        }
        
        if (!in_array($role, $valid_roles)) {
            error_log(sprintf('Skipping invalid role for removal: %s', esc_html($role)));
            return false;
        }
        
        return true;
    });
    
    // Get all roles from other active subscriptions
    $other_active_roles = [];
    $active_subscriptions = get_active_user_subscriptions($user_id);
    
    foreach ($active_subscriptions as $subscription) {
        // Skip the current product
        if ($subscription->product_id == $product_id) {
            continue;
        }
        
        // Get roles from other active subscriptions
        $subscription_roles = get_product_meta($subscription->product_id, '_membership_roles', []);
        
        // Check for legacy single role
        if (empty($subscription_roles)) {
            $legacy_role = get_post_meta($subscription->product_id, '_membership_role', true);
            if (!empty($legacy_role)) {
                $subscription_roles = [$legacy_role];
            }
        }
        
        // Validate subscription roles
        $subscription_roles = array_filter($subscription_roles, function($role) use ($valid_roles) {
            return !empty($role) && in_array($role, $valid_roles);
        });
        
        // Add roles to the list of roles to keep
        if (!empty($subscription_roles)) {
            $other_active_roles = array_merge($other_active_roles, $subscription_roles);
        }
    }
    
    // Only remove roles that aren't provided by other active subscriptions
    foreach ($roles_to_remove as $role) {
        if (!in_array($role, $other_active_roles)) {
            $user->remove_role($role);
        }
    }
    
    // If user has no roles left, assign the default role
    if (empty($user->roles)) {
        $default_role = get_option('default_role', 'subscriber');
        
        // Validate default role
        if (in_array($default_role, $valid_roles)) {
            $user->set_role($default_role);
        } else {
            // Fallback to subscriber if default role is invalid
            $user->set_role('subscriber');
            error_log(sprintf('Default role %s is invalid, falling back to subscriber', esc_html($default_role)));
        }
    }
    
    return true;
}

/**
 * Handle subscription status change
 * Used as a hook callback for when subscription status changes
 * 
 * @param int $subscription_id
 * @param string $old_status
 * @param string $new_status
 */
function handle_subscription_status_change($subscription_id, $old_status, $new_status) {
    $subscription = get_subscription($subscription_id);
    
    if (!$subscription) {
        return;
    }
    
    // Apply role if subscription becomes active
    if ($new_status === 'active' && $old_status !== 'active') {
        apply_membership_role($subscription->user_id, $subscription->product_id);
    }
    
    // Remove role if subscription is no longer active
    if ($old_status === 'active' && $new_status !== 'active') {
        // Check if we should remove access immediately or wait until expiration
        $remove_access_immediately = apply_filters('members_subscriptions_remove_access_immediately', true, $subscription, $new_status);
        
        if ($remove_access_immediately) {
            remove_membership_role($subscription->user_id, $subscription->product_id);
        }
    }
    
    // Trigger action for status change
    do_action("members_subscription_{$new_status}", $subscription);
}

// Add hooks for subscription status changes
add_action('members_subscription_status_change', 'Members\Subscriptions\handle_subscription_status_change', 10, 3);