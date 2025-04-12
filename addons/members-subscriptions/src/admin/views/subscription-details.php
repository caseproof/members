<?php
/**
 * Subscription Details Admin View
 */

namespace Members\Subscriptions\admin;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions;

// Get subscription
$subscription = Subscriptions\get_subscription($subscription_id);

if (!$subscription) {
    ?>
    <div class="notice notice-error">
        <p><?php _e('Subscription not found.', 'members'); ?></p>
    </div>
    <?php
    return;
}

// Get user and product
$user = get_userdata($subscription->user_id);
$product = get_post($subscription->product_id);

// Get related transactions
$transactions = Subscriptions\get_transactions([
    'subscription_id' => $subscription->id,
    'orderby' => 'created_at',
    'order' => 'DESC',
]);

// Calculate lifetime value
$lifetime_value = 0;
foreach ($transactions as $transaction) {
    if ($transaction->status === 'complete' && $transaction->txn_type !== 'refund') {
        $lifetime_value += (float)$transaction->total;
    } elseif ($transaction->txn_type === 'refund') {
        $lifetime_value -= (float)$transaction->total;
    }
}

// Calculate days remaining
$days_remaining = 0;
$is_expired = false;
if (!empty($subscription->expires_at) && $subscription->expires_at !== '0000-00-00 00:00:00') {
    $expires_timestamp = strtotime($subscription->expires_at);
    $now_timestamp = current_time('timestamp');
    
    if ($expires_timestamp > $now_timestamp) {
        $days_remaining = ceil(($expires_timestamp - $now_timestamp) / DAY_IN_SECONDS);
    } else {
        $is_expired = true;
    }
}

// Get subscription status
$status = Subscriptions\get_formatted_subscription_status($subscription->status);

// Get formatted created date
$created_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($subscription->created_at));

// Get formatted expiration date
if (empty($subscription->expires_at) || $subscription->expires_at === '0000-00-00 00:00:00') {
    $expiration_date = __('Never (lifetime)', 'members');
} else {
    $expiration_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($subscription->expires_at));
}
?>
<div class="wrap members-subscription-details">
    <h1 class="wp-heading-inline"><?php _e('Subscription Details', 'members'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=members-subscriptions')); ?>" class="page-title-action"><?php _e('Back to Subscriptions', 'members'); ?></a>
    <hr class="wp-header-end">
    
    <?php if ($subscription->status === 'active') : ?>
    <div class="members-status-badge members-status-active">
        <?php _e('Active Subscription', 'members'); ?>
    </div>
    <?php elseif ($subscription->status === 'pending') : ?>
    <div class="members-status-badge members-status-pending">
        <?php _e('Pending Subscription', 'members'); ?>
    </div>
    <?php elseif ($subscription->status === 'cancelled') : ?>
    <div class="members-status-badge members-status-cancelled">
        <?php _e('Cancelled Subscription', 'members'); ?>
    </div>
    <?php elseif ($subscription->status === 'expired') : ?>
    <div class="members-status-badge members-status-expired">
        <?php _e('Expired Subscription', 'members'); ?>
    </div>
    <?php endif; ?>
    
    <div class="members-subscription-overview">
        <div class="members-subscription-meta">
            <div class="members-card">
                <h2><?php _e('Subscription Overview', 'members'); ?></h2>
                
                <div class="members-subscription-info">
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('ID', 'members'); ?>:</span>
                        <span class="members-info-value">#<?php echo esc_html($subscription->id); ?></span>
                    </div>
                    
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('Status', 'members'); ?>:</span>
                        <span class="members-info-value members-sub-status members-sub-status-<?php echo esc_attr($subscription->status); ?>"><?php echo esc_html($status); ?></span>
                    </div>
                    
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('Created', 'members'); ?>:</span>
                        <span class="members-info-value"><?php echo esc_html($created_date); ?></span>
                    </div>
                    
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('Expiration', 'members'); ?>:</span>
                        <span class="members-info-value">
                            <?php echo esc_html($expiration_date); ?>
                            <?php if ($subscription->status === 'active' && !empty($days_remaining)) : ?>
                                <span class="members-days-remaining">(<?php echo sprintf(_n('%d day remaining', '%d days remaining', $days_remaining, 'members'), $days_remaining); ?>)</span>
                            <?php elseif ($is_expired) : ?>
                                <span class="members-days-expired">(<?php _e('Expired', 'members'); ?>)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('Payment Method', 'members'); ?>:</span>
                        <span class="members-info-value">
                            <?php 
                            $gateways = [
                                'stripe' => __('Stripe', 'members'),
                                'manual' => __('Manual', 'members'),
                            ];
                            echo isset($gateways[$subscription->gateway]) ? esc_html($gateways[$subscription->gateway]) : esc_html($subscription->gateway);
                            ?>
                        </span>
                    </div>
                    
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('Renewal Count', 'members'); ?>:</span>
                        <span class="members-info-value"><?php echo esc_html($subscription->renewal_count); ?></span>
                    </div>
                    
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('Lifetime Value', 'members'); ?>:</span>
                        <span class="members-info-value"><?php echo number_format_i18n($lifetime_value, 2); ?></span>
                    </div>
                    
                    <?php if (!empty($subscription->cc_last4)) : ?>
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('Card', 'members'); ?>:</span>
                        <span class="members-info-value">
                            <?php 
                            echo esc_html(sprintf(
                                __('Ending in %s (Expires: %s/%s)', 'members'),
                                $subscription->cc_last4,
                                $subscription->cc_exp_month,
                                $subscription->cc_exp_year
                            )); 
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="members-card">
                <h2><?php _e('Subscription Details', 'members'); ?></h2>
                
                <div class="members-subscription-details">
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('Amount', 'members'); ?>:</span>
                        <span class="members-info-value"><?php echo number_format_i18n((float)$subscription->price, 2); ?></span>
                    </div>
                    
                    <?php if (!empty($subscription->tax_amount)) : ?>
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('Tax', 'members'); ?>:</span>
                        <span class="members-info-value">
                            <?php 
                            echo number_format_i18n((float)$subscription->tax_amount, 2); 
                            if (!empty($subscription->tax_rate)) {
                                echo ' (' . number_format_i18n((float)$subscription->tax_rate, 2) . '%)';
                            }
                            if (!empty($subscription->tax_desc)) {
                                echo ' - ' . esc_html($subscription->tax_desc);
                            }
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('Total', 'members'); ?>:</span>
                        <span class="members-info-value"><?php echo number_format_i18n((float)$subscription->total, 2); ?></span>
                    </div>
                    
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('Billing Period', 'members'); ?>:</span>
                        <span class="members-info-value">
                            <?php 
                            if (!empty($subscription->period)) {
                                echo esc_html(Subscriptions\format_subscription_period($subscription->period, $subscription->period_type));
                            } else {
                                _e('One-time payment', 'members');
                            }
                            ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($subscription->trial) && $subscription->trial) : ?>
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('Trial', 'members'); ?>:</span>
                        <span class="members-info-value">
                            <?php 
                            echo sprintf(
                                __('%d days for %s', 'members'),
                                $subscription->trial_days,
                                number_format_i18n((float)$subscription->trial_amount, 2)
                            ); 
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="members-user-product-info">
            <div class="members-card">
                <h2><?php _e('User Information', 'members'); ?></h2>
                
                <?php if ($user) : ?>
                <div class="members-user-info">
                    <div class="members-user-avatar">
                        <?php echo get_avatar($user->ID, 80); ?>
                    </div>
                    
                    <div class="members-user-details">
                        <h3><?php echo esc_html($user->display_name); ?></h3>
                        <p class="members-user-email"><?php echo esc_html($user->user_email); ?></p>
                        
                        <div class="members-user-meta">
                            <div class="members-info-item">
                                <span class="members-info-label"><?php _e('Registered', 'members'); ?>:</span>
                                <span class="members-info-value"><?php echo date_i18n(get_option('date_format'), strtotime($user->user_registered)); ?></span>
                            </div>
                            
                            <div class="members-info-item">
                                <span class="members-info-label"><?php _e('User ID', 'members'); ?>:</span>
                                <span class="members-info-value"><?php echo esc_html($user->ID); ?></span>
                            </div>
                        </div>
                        
                        <div class="members-user-actions">
                            <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>" class="button"><?php _e('Edit User', 'members'); ?></a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=members-subscriptions&user_id=' . $user->ID)); ?>" class="button"><?php _e('View All Subscriptions', 'members'); ?></a>
                        </div>
                    </div>
                </div>
                <?php else : ?>
                <div class="members-user-deleted">
                    <p><?php _e('User has been deleted (ID: ', 'members'); ?><?php echo esc_html($subscription->user_id); ?>)</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="members-card">
                <h2><?php _e('Product Information', 'members'); ?></h2>
                
                <?php if ($product) : ?>
                <div class="members-product-info">
                    <?php if (has_post_thumbnail($product->ID)) : ?>
                    <div class="members-product-thumbnail">
                        <?php echo get_the_post_thumbnail($product->ID, 'thumbnail'); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="members-product-details">
                        <h3><?php echo esc_html($product->post_title); ?></h3>
                        
                        <?php
                        // Get associated roles
                        $product_roles = Subscriptions\get_product_meta($product->ID, '_membership_roles', []);
                        
                        if (!empty($product_roles)) :
                        ?>
                        <div class="members-product-roles">
                            <h4><?php _e('Associated Roles:', 'members'); ?></h4>
                            <ul>
                                <?php foreach ($product_roles as $role) : 
                                    $role_object = get_role($role);
                                    $role_name = $role;
                                    
                                    if (function_exists('\\Members\\get_role')) {
                                        $members_role = \Members\get_role($role);
                                        if ($members_role) {
                                            $role_name = $members_role->get_name();
                                        }
                                    }
                                ?>
                                <li><?php echo esc_html($role_name); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <div class="members-product-actions">
                            <a href="<?php echo esc_url(get_edit_post_link($product->ID)); ?>" class="button"><?php _e('Edit Product', 'members'); ?></a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=members-subscriptions&product_id=' . $product->ID)); ?>" class="button"><?php _e('View All Subscriptions', 'members'); ?></a>
                        </div>
                    </div>
                </div>
                <?php else : ?>
                <div class="members-product-deleted">
                    <p><?php _e('Product has been deleted (ID: ', 'members'); ?><?php echo esc_html($subscription->product_id); ?>)</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="members-subscription-actions-section">
        <div class="members-card">
            <h2><?php _e('Subscription Actions', 'members'); ?></h2>
            
            <div class="members-subscription-actions">
                <?php if ($subscription->status === 'pending') : ?>
                <a href="<?php echo esc_url(add_query_arg(['action' => 'activate', 'subscription' => $subscription->id, '_wpnonce' => wp_create_nonce('members_activate_subscription')], admin_url('admin.php?page=members-subscriptions'))); ?>" class="button button-primary"><?php _e('Activate Subscription', 'members'); ?></a>
                <?php endif; ?>
                
                <?php if ($subscription->status === 'active') : ?>
                <a href="<?php echo esc_url(add_query_arg(['action' => 'renew', 'subscription' => $subscription->id, '_wpnonce' => wp_create_nonce('members_renew_subscription')], admin_url('admin.php?page=members-subscriptions'))); ?>" class="button button-primary"><?php _e('Process Manual Renewal', 'members'); ?></a>
                
                <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'subscription' => $subscription->id], admin_url('admin.php?page=members-subscriptions'))); ?>" class="button"><?php _e('Edit Subscription', 'members'); ?></a>
                
                <a href="<?php echo esc_url(add_query_arg(['action' => 'cancel', 'subscription' => $subscription->id, '_wpnonce' => wp_create_nonce('members_cancel_subscription')], admin_url('admin.php?page=members-subscriptions'))); ?>" class="button" onclick="return confirm('<?php esc_attr_e('Are you sure you want to cancel this subscription? This action cannot be undone.', 'members'); ?>')"><?php _e('Cancel Subscription', 'members'); ?></a>
                <?php endif; ?>
                
                <?php if ($subscription->status === 'expired' || $subscription->status === 'cancelled') : ?>
                <a href="<?php echo esc_url(add_query_arg(['action' => 'reactivate', 'subscription' => $subscription->id, '_wpnonce' => wp_create_nonce('members_reactivate_subscription')], admin_url('admin.php?page=members-subscriptions'))); ?>" class="button button-primary"><?php _e('Reactivate Subscription', 'members'); ?></a>
                <?php endif; ?>
                
                <a href="<?php echo esc_url(add_query_arg(['action' => 'delete', 'subscription' => $subscription->id, '_wpnonce' => wp_create_nonce('members_delete_subscription')], admin_url('admin.php?page=members-subscriptions'))); ?>" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this subscription? This action cannot be undone.', 'members'); ?>')"><?php _e('Delete Subscription', 'members'); ?></a>
            </div>
        </div>
    </div>
    
    <div class="members-transactions-section">
        <div class="members-card">
            <h2><?php _e('Related Transactions', 'members'); ?></h2>
            
            <?php if (!empty($transactions)) : ?>
            <table class="wp-list-table widefat fixed striped members-transactions-table">
                <thead>
                    <tr>
                        <th scope="col"><?php _e('ID', 'members'); ?></th>
                        <th scope="col"><?php _e('Date', 'members'); ?></th>
                        <th scope="col"><?php _e('Transaction #', 'members'); ?></th>
                        <th scope="col"><?php _e('Type', 'members'); ?></th>
                        <th scope="col"><?php _e('Amount', 'members'); ?></th>
                        <th scope="col"><?php _e('Status', 'members'); ?></th>
                        <th scope="col"><?php _e('Actions', 'members'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction) : ?>
                    <tr>
                        <td><?php echo esc_html($transaction->id); ?></td>
                        <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction->created_at)); ?></td>
                        <td><?php echo esc_html($transaction->trans_num); ?></td>
                        <td>
                            <?php 
                            $types = [
                                'payment' => __('Payment', 'members'),
                                'refund'  => __('Refund', 'members'),
                                'renewal' => __('Renewal', 'members'),
                            ];
                            echo isset($types[$transaction->txn_type]) ? esc_html($types[$transaction->txn_type]) : esc_html($transaction->txn_type);
                            ?>
                        </td>
                        <td><?php echo number_format_i18n((float)$transaction->total, 2); ?></td>
                        <td>
                            <span class="members-transaction-status members-transaction-status-<?php echo esc_attr($transaction->status); ?>">
                                <?php 
                                $statuses = [
                                    'complete' => __('Complete', 'members'),
                                    'pending'  => __('Pending', 'members'),
                                    'refunded' => __('Refunded', 'members'),
                                    'failed'   => __('Failed', 'members'),
                                ];
                                echo isset($statuses[$transaction->status]) ? esc_html($statuses[$transaction->status]) : esc_html($transaction->status);
                                ?>
                            </span>
                        </td>
                        <td>
                            <div class="members-row-actions">
                                <a href="<?php echo esc_url(add_query_arg(['page' => 'members-transactions', 'action' => 'view', 'transaction' => $transaction->id], admin_url('admin.php'))); ?>" class="button button-small"><?php _e('View', 'members'); ?></a>
                                
                                <?php if ($transaction->status === 'complete' && $transaction->txn_type !== 'refund') : ?>
                                <a href="<?php echo esc_url(add_query_arg(['page' => 'members-transactions', 'action' => 'refund', 'transaction' => $transaction->id, '_wpnonce' => wp_create_nonce('members_refund_transaction')], admin_url('admin.php'))); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure you want to refund this transaction?', 'members'); ?>')"><?php _e('Refund', 'members'); ?></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p><?php _e('No transactions found for this subscription.', 'members'); ?></p>
            <?php endif; ?>
            
            <?php if ($subscription->status === 'active') : ?>
            <div class="members-add-transaction">
                <a href="<?php echo esc_url(add_query_arg(['page' => 'members-transactions', 'action' => 'add', 'subscription_id' => $subscription->id], admin_url('admin.php'))); ?>" class="button"><?php _e('Add Transaction', 'members'); ?></a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Subscription Details Styles */
.members-subscription-details h1.wp-heading-inline {
    margin-bottom: 20px;
}

.members-status-badge {
    display: inline-block;
    padding: 8px 15px;
    border-radius: 4px;
    font-weight: bold;
    margin-bottom: 20px;
}

.members-status-active {
    background-color: #e7f9ed;
    color: #1d9d4f;
}

.members-status-pending {
    background-color: #fff8e6;
    color: #d69e2e;
}

.members-status-cancelled {
    background-color: #f2f3f5;
    color: #555d66;
}

.members-status-expired {
    background-color: #f8eae9;
    color: #d63638;
}

.members-subscription-overview {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 30px;
}

.members-subscription-meta {
    flex: 1;
    min-width: 300px;
}

.members-user-product-info {
    flex: 1;
    min-width: 300px;
}

.members-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    padding: 20px;
}

.members-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    font-size: 18px;
}

.members-info-item {
    margin-bottom: 8px;
    line-height: 1.5;
}

.members-info-label {
    font-weight: bold;
    margin-right: 5px;
}

.members-sub-status {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.members-sub-status-active {
    background-color: #e7f9ed;
    color: #1d9d4f;
}

.members-sub-status-pending {
    background-color: #fff8e6;
    color: #d69e2e;
}

.members-sub-status-cancelled {
    background-color: #f2f3f5;
    color: #555d66;
}

.members-sub-status-expired {
    background-color: #f8eae9;
    color: #d63638;
}

.members-days-remaining {
    color: #1d9d4f;
    font-size: 0.85em;
    margin-left: 5px;
}

.members-days-expired {
    color: #d63638;
    font-size: 0.85em;
    margin-left: 5px;
}

.members-user-info {
    display: flex;
    align-items: flex-start;
}

.members-user-avatar {
    margin-right: 15px;
}

.members-user-details {
    flex: 1;
}

.members-user-details h3 {
    margin: 0 0 5px 0;
}

.members-user-email {
    margin: 0 0 15px 0;
    color: #666;
}

.members-user-actions {
    margin-top: 15px;
}

.members-product-info {
    display: flex;
    align-items: flex-start;
}

.members-product-thumbnail {
    margin-right: 15px;
    width: 80px;
    height: 80px;
}

.members-product-details {
    flex: 1;
}

.members-product-details h3 {
    margin: 0 0 10px 0;
}

.members-product-roles {
    margin: 15px 0;
}

.members-product-roles h4 {
    margin: 0 0 5px 0;
    font-size: 14px;
}

.members-product-roles ul {
    margin: 0;
    padding-left: 20px;
}

.members-product-actions {
    margin-top: 15px;
}

.members-subscription-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.members-transactions-table {
    width: 100%;
    border-spacing: 0;
}

.members-transactions-table th {
    text-align: left;
    padding: 8px;
}

.members-transactions-table td {
    padding: 12px 8px;
}

.members-transaction-status {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.members-transaction-status-complete {
    background-color: #e7f9ed;
    color: #1d9d4f;
}

.members-transaction-status-pending {
    background-color: #fff8e6;
    color: #d69e2e;
}

.members-transaction-status-refunded {
    background-color: #f2f3f5;
    color: #555d66;
}

.members-transaction-status-failed {
    background-color: #f8eae9;
    color: #d63638;
}

.members-row-actions {
    display: flex;
    gap: 5px;
}

.members-add-transaction {
    margin-top: 15px;
    text-align: right;
}
</style>