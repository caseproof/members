<?php
/**
 * Account Page Template
 *
 * This template displays the member's account page with their subscriptions and transactions.
 */

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

// Check if user is logged in
$user_id = get_current_user_id();

if (!$user_id) {
    // Display login required message with styled box
    echo '<div class="members-login-required" style="padding: 20px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; margin: 20px 0; text-align: center;">';
    echo '<p style="margin-bottom: 15px;">' . __('Please log in to view your account.', 'members') . '</p>';
    echo '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="button" style="display: inline-block; background-color: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">' . __('Log In', 'members') . '</a>';
    echo '</div>';
    return;
}

// Get user data
$user = get_userdata($user_id);

// Get subscriptions with error handling
$subscriptions = [];
try {
    $subscriptions = get_user_subscriptions($user_id);
} catch (\Exception $e) {
    // Silent fail, show empty subscriptions
}

// Get transactions with error handling
$transactions = [];
try {
    $transactions = get_transactions([
        'user_id' => $user_id,
        'orderby' => 'created_at',
        'order' => 'DESC',
    ]);
} catch (\Exception $e) {
    // Silent fail, show empty transactions
}

// Account tabs
$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'subscriptions';

// Process subscription cancellation
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['subscription_id'])) {
    $subscription_id = absint($_GET['subscription_id']);
    $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
    
    // Verify nonce
    if (wp_verify_nonce($nonce, 'cancel_subscription_' . $subscription_id)) {
        // Verify subscription belongs to this user
        $subscription = get_subscription($subscription_id);
        
        if ($subscription && $subscription->user_id === $user_id) {
            if (cancel_subscription($subscription_id)) {
                $redirect_url = remove_query_arg(['action', 'subscription_id', '_wpnonce']);
                $redirect_url = add_query_arg('message', 'subscription_cancelled', $redirect_url);
                wp_redirect($redirect_url);
                exit;
            } else {
                $redirect_url = remove_query_arg(['action', 'subscription_id', '_wpnonce']);
                $redirect_url = add_query_arg('error', 'cancel_failed', $redirect_url);
                wp_redirect($redirect_url);
                exit;
            }
        }
    }
}

// Get payment methods if available
$payment_methods = [];
// This would be populated by gateways that support stored payment methods

// Process Stripe-specific payment method management
// This would be used with hooks for Stripe integration

// CSS Styles for the account page
?>
<style type="text/css">
    .members-subscription-container {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        color: #333;
        line-height: 1.5;
    }
    .members-account-header {
        margin-bottom: 20px;
    }
    .members-account-tabs {
        display: flex;
        list-style: none;
        margin: 0 0 20px 0;
        padding: 0;
        border-bottom: 1px solid #dee2e6;
    }
    .members-account-tab {
        margin: 0;
        padding: 0;
    }
    .members-account-tab a {
        display: block;
        padding: 10px 15px;
        text-decoration: none;
        color: #555;
        border: 1px solid transparent;
        border-top-left-radius: 4px;
        border-top-right-radius: 4px;
        margin-bottom: -1px;
    }
    .members-account-tab.active a {
        color: #007bff;
        background-color: #fff;
        border-color: #dee2e6 #dee2e6 #fff;
        font-weight: bold;
    }
    .members-account-tab a:hover:not(.active) {
        background-color: #f8f9fa;
        border-color: #f8f9fa #f8f9fa #dee2e6;
    }
    .members-account-tab-content {
        display: none;
        padding: 20px 0;
    }
    .members-account-tab-content.active {
        display: block;
    }
    .members-message {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
    }
    .members-message-info {
        color: #0c5460;
        background-color: #d1ecf1;
        border-color: #bee5eb;
    }
    .members-message-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }
    .members-message-warning {
        color: #856404;
        background-color: #fff3cd;
        border-color: #ffeeba;
    }
    .members-message-error {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }
    .members-subscriptions-table,
    .members-transactions-table,
    .members-payment-methods-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    .members-subscriptions-table th,
    .members-transactions-table th,
    .members-payment-methods-table th {
        background-color: #f8f9fa;
        text-align: left;
        padding: 10px;
        border-bottom: 2px solid #dee2e6;
    }
    .members-subscriptions-table td,
    .members-transactions-table td,
    .members-payment-methods-table td {
        padding: 10px;
        border-bottom: 1px solid #dee2e6;
    }
    .members-subscription-status,
    .members-transaction-status {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 3px;
        font-size: 14px;
    }
    .members-subscription-status-active,
    .members-transaction-status-complete {
        background-color: #d4edda;
        color: #155724;
    }
    .members-subscription-status-pending,
    .members-transaction-status-pending {
        background-color: #fff3cd;
        color: #856404;
    }
    .members-subscription-status-cancelled,
    .members-subscription-status-expired,
    .members-subscription-status-suspended,
    .members-subscription-status-failed,
    .members-transaction-status-failed {
        background-color: #f8d7da;
        color: #721c24;
    }
    .members-transaction-status-refunded {
        background-color: #e2e3e5;
        color: #383d41;
    }
    .members-subscription-actions {
        white-space: nowrap;
    }
    .members-subscription-action {
        display: inline-block;
        padding: 5px 10px;
        text-decoration: none;
        border-radius: 3px;
        font-size: 14px;
        margin-right: 5px;
    }
    .members-subscription-action.cancel {
        background-color: #f8d7da;
        color: #721c24;
    }
    .members-subscription-action.update {
        background-color: #d1ecf1;
        color: #0c5460;
    }
    .members-account-info {
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 5px;
    }
    .members-form-row {
        margin-bottom: 15px;
    }
    .members-form-label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    .members-form-input {
        padding: 8px;
        background-color: #fff;
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }
    .members-form-input.disabled {
        background-color: #e9ecef;
    }
    .members-form-submit {
        display: inline-block;
        margin-right: 10px;
        background-color: #007bff;
        color: white;
        padding: 8px 16px;
        text-decoration: none;
        border-radius: 4px;
        border: none;
        cursor: pointer;
    }
    .members-payment-method {
        display: flex;
        align-items: center;
    }
    .members-payment-method-icon {
        margin-right: 10px;
    }
    .members-payment-method-details {
        flex-grow: 1;
    }
    .members-payment-method-actions {
        white-space: nowrap;
    }
    .members-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }
    .members-modal {
        background-color: #fff;
        border-radius: 5px;
        padding: 20px;
        max-width: 500px;
        width: 90%;
        position: relative;
    }
    .members-modal-close {
        position: absolute;
        top: 10px;
        right: 10px;
        cursor: pointer;
        font-size: 20px;
        color: #555;
    }
    .members-modal-title {
        margin-top: 0;
        margin-bottom: 15px;
    }
    .members-modal-content {
        margin-bottom: 15px;
    }
    .members-modal-actions {
        display: flex;
        justify-content: flex-end;
    }
    .members-modal-action {
        margin-left: 10px;
    }
    @media (max-width: 768px) {
        .members-account-tabs {
            flex-direction: column;
            border-bottom: none;
        }
        .members-account-tab {
            margin-bottom: 5px;
        }
        .members-account-tab a {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 0;
        }
        .members-account-tab.active a {
            border-color: #007bff;
        }
        .members-subscriptions-table,
        .members-transactions-table,
        .members-payment-methods-table {
            display: block;
            overflow-x: auto;
        }
    }
</style>

<div class="members-subscription-container">
    <!-- Notification Messages -->
    <?php if (isset($_GET['message'])): ?>
        <?php if ($_GET['message'] === 'subscription_cancelled'): ?>
            <div class="members-message members-message-success">
                <p><?php _e('Your membership has been cancelled successfully.', 'members'); ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <?php if ($_GET['error'] === 'cancel_failed'): ?>
            <div class="members-message members-message-error">
                <p><?php _e('There was an error cancelling your membership. Please try again or contact support.', 'members'); ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="members-account">
        <div class="members-account-header">
            <h2><?php echo esc_html(sprintf(__('Hello, %s', 'members'), $user->display_name)); ?></h2>
        </div>
        
        <ul class="members-account-tabs">
            <li class="members-account-tab <?php echo $active_tab === 'subscriptions' ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(add_query_arg('tab', 'subscriptions')); ?>">
                    <?php _e('Memberships', 'members'); ?>
                </a>
            </li>
            <li class="members-account-tab <?php echo $active_tab === 'transactions' ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(add_query_arg('tab', 'transactions')); ?>">
                    <?php _e('Payment History', 'members'); ?>
                </a>
            </li>
            <li class="members-account-tab <?php echo $active_tab === 'payment-methods' ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(add_query_arg('tab', 'payment-methods')); ?>">
                    <?php _e('Payment Methods', 'members'); ?>
                </a>
            </li>
            <li class="members-account-tab <?php echo $active_tab === 'account' ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(add_query_arg('tab', 'account')); ?>">
                    <?php _e('Account Details', 'members'); ?>
                </a>
            </li>
        </ul>
        
        <div class="members-account-content">
            <!-- Subscriptions Tab -->
            <div id="subscriptions" class="members-account-tab-content <?php echo $active_tab === 'subscriptions' ? 'active' : ''; ?>">
                <h3><?php _e('Your Memberships', 'members'); ?></h3>
                
                <?php if (empty($subscriptions)): ?>
                    <div class="members-message members-message-info">
                        <p><?php _e('You do not have any active memberships.', 'members'); ?></p>
                        <p><?php _e('Browse our available membership options below:', 'members'); ?></p>
                    </div>
                    
                    <?php
                    // Display available products
                    $products = get_posts([
                        'post_type' => 'members_product',
                        'post_status' => 'publish',
                        'posts_per_page' => 5,
                    ]);
                    
                    if (!empty($products)) {
                        echo '<div class="members-available-products">';
                        foreach ($products as $product) {
                            echo '<div class="members-product-item">';
                            echo '<h4>' . esc_html($product->post_title) . '</h4>';
                            if (!empty($product->post_excerpt)) {
                                echo '<p>' . wp_kses_post($product->post_excerpt) . '</p>';
                            }
                            echo '<a href="' . esc_url(get_permalink($product->ID)) . '" class="members-form-submit">' . __('View Details', 'members') . '</a>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    ?>
                <?php else: ?>
                    <table class="members-subscriptions-table">
                        <thead>
                            <tr>
                                <th><?php _e('Membership', 'members'); ?></th>
                                <th><?php _e('Status', 'members'); ?></th>
                                <th><?php _e('Started', 'members'); ?></th>
                                <th><?php _e('Renews', 'members'); ?></th>
                                <th><?php _e('Price', 'members'); ?></th>
                                <th><?php _e('Actions', 'members'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscriptions as $subscription): 
                                $product = get_post($subscription->product_id);
                                if (!$product) continue;
                                
                                $status = get_formatted_subscription_status($subscription->status);
                                $created = date_i18n(get_option('date_format'), strtotime($subscription->created_at));
                                
                                // Calculate renewal date for active subscriptions
                                $renews = '';
                                if ($subscription->status === 'active') {
                                    if (empty($subscription->expires_at) || $subscription->expires_at === '0000-00-00 00:00:00') {
                                        $renews = __('Never (lifetime)', 'members');
                                    } else {
                                        $renews = date_i18n(get_option('date_format'), strtotime($subscription->expires_at));
                                    }
                                } else {
                                    $renews = '—';
                                }
                                
                                // Format price
                                $price = '';
                                if (!empty($subscription->price)) {
                                    $price = '$' . number_format((float)$subscription->price, 2);
                                    
                                    if (!empty($subscription->period) && !empty($subscription->period_type)) {
                                        $period_label = '';
                                        switch ($subscription->period_type) {
                                            case 'day':
                                                $period_label = _n('day', 'days', $subscription->period, 'members');
                                                break;
                                            case 'week':
                                                $period_label = _n('week', 'weeks', $subscription->period, 'members');
                                                break;
                                            case 'month':
                                                $period_label = _n('month', 'months', $subscription->period, 'members');
                                                break;
                                            case 'year':
                                                $period_label = _n('year', 'years', $subscription->period, 'members');
                                                break;
                                        }
                                        
                                        $price .= ' / ' . $subscription->period . ' ' . $period_label;
                                    }
                                } else {
                                    $price = __('Free', 'members');
                                }
                            ?>
                            <tr>
                                <td><?php echo esc_html($product->post_title); ?></td>
                                <td>
                                    <span class="members-subscription-status members-subscription-status-<?php echo esc_attr($subscription->status); ?>">
                                        <?php echo esc_html($status); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($created); ?></td>
                                <td><?php echo esc_html($renews); ?></td>
                                <td><?php echo esc_html($price); ?></td>
                                <td class="members-subscription-actions">
                                    <?php if ($subscription->status === 'active'): ?>
                                        <?php
                                        // Create a nonce for the cancel action
                                        $cancel_nonce = wp_create_nonce('cancel_subscription_' . $subscription->id);
                                        $cancel_url = add_query_arg([
                                            'action' => 'cancel',
                                            'subscription_id' => $subscription->id,
                                            '_wpnonce' => $cancel_nonce,
                                        ]);
                                        ?>
                                        <a href="#" class="members-subscription-action cancel" 
                                           data-subscription-id="<?php echo esc_attr($subscription->id); ?>"
                                           data-subscription-name="<?php echo esc_attr($product->post_title); ?>"
                                           data-cancel-url="<?php echo esc_url($cancel_url); ?>"
                                           onclick="showCancelModal(this); return false;">
                                            <?php _e('Cancel', 'members'); ?>
                                        </a>
                                        
                                        <?php if (!empty($payment_methods)): ?>
                                        <a href="#" class="members-subscription-action update"
                                           data-subscription-id="<?php echo esc_attr($subscription->id); ?>"
                                           onclick="showUpdateModal(this); return false;">
                                            <?php _e('Update Payment', 'members'); ?>
                                        </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Transactions Tab -->
            <div id="transactions" class="members-account-tab-content <?php echo $active_tab === 'transactions' ? 'active' : ''; ?>">
                <h3><?php _e('Payment History', 'members'); ?></h3>
                
                <?php if (empty($transactions)): ?>
                    <div class="members-message members-message-info">
                        <p><?php _e('You do not have any transaction records.', 'members'); ?></p>
                    </div>
                <?php else: ?>
                    <table class="members-transactions-table">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'members'); ?></th>
                                <th><?php _e('Membership', 'members'); ?></th>
                                <th><?php _e('Type', 'members'); ?></th>
                                <th><?php _e('Amount', 'members'); ?></th>
                                <th><?php _e('Status', 'members'); ?></th>
                                <th><?php _e('Transaction ID', 'members'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): 
                                $product = get_post($transaction->product_id);
                                if (!$product) continue;
                                
                                $created = date_i18n(get_option('date_format'), strtotime($transaction->created_at));
                                $amount = number_format((float)$transaction->total, 2);
                                
                                // Format transaction type
                                $type = isset($transaction->txn_type) ? $transaction->txn_type : 'payment';
                                $type_labels = [
                                    'payment' => __('Payment', 'members'),
                                    'refund' => __('Refund', 'members'),
                                    'renewal' => __('Renewal', 'members'),
                                ];
                                $type_formatted = isset($type_labels[$type]) ? $type_labels[$type] : ucfirst($type);
                            ?>
                            <tr>
                                <td><?php echo esc_html($created); ?></td>
                                <td><?php echo esc_html($product->post_title); ?></td>
                                <td><?php echo esc_html($type_formatted); ?></td>
                                <td>$<?php echo esc_html($amount); ?></td>
                                <td>
                                    <span class="members-transaction-status members-transaction-status-<?php echo esc_attr($transaction->status); ?>">
                                        <?php echo esc_html(ucfirst($transaction->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($transaction->trans_num); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Payment Methods Tab -->
            <div id="payment-methods" class="members-account-tab-content <?php echo $active_tab === 'payment-methods' ? 'active' : ''; ?>">
                <h3><?php _e('Payment Methods', 'members'); ?></h3>
                
                <?php if (empty($payment_methods)): ?>
                    <div class="members-message members-message-info">
                        <p><?php _e('You do not have any saved payment methods.', 'members'); ?></p>
                        <p><?php _e('Payment methods will be added automatically when you make a purchase using a payment gateway that supports storing payment information, such as Stripe.', 'members'); ?></p>
                    </div>
                <?php else: ?>
                    <table class="members-payment-methods-table">
                        <thead>
                            <tr>
                                <th><?php _e('Method', 'members'); ?></th>
                                <th><?php _e('Expires', 'members'); ?></th>
                                <th><?php _e('Default', 'members'); ?></th>
                                <th><?php _e('Actions', 'members'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payment_methods as $method): ?>
                            <tr>
                                <td class="members-payment-method">
                                    <div class="members-payment-method-icon">
                                        <!-- Card icon would go here -->
                                    </div>
                                    <div class="members-payment-method-details">
                                        <?php echo esc_html($method['type']); ?> ending in <?php echo esc_html($method['last4']); ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html($method['exp_month'] . '/' . $method['exp_year']); ?></td>
                                <td><?php echo $method['is_default'] ? '✓' : ''; ?></td>
                                <td class="members-payment-method-actions">
                                    <a href="#" class="members-subscription-action update" data-method-id="<?php echo esc_attr($method['id']); ?>" onclick="makeDefaultPaymentMethod(this); return false;">
                                        <?php _e('Make Default', 'members'); ?>
                                    </a>
                                    <a href="#" class="members-subscription-action cancel" data-method-id="<?php echo esc_attr($method['id']); ?>" onclick="deletePaymentMethod(this); return false;">
                                        <?php _e('Delete', 'members'); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <!-- Add payment method form would go here if supported -->
            </div>
            
            <!-- Account Tab -->
            <div id="account" class="members-account-tab-content <?php echo $active_tab === 'account' ? 'active' : ''; ?>">
                <h3><?php _e('Account Details', 'members'); ?></h3>
                
                <div class="members-account-info">
                    <div class="members-form-row">
                        <label class="members-form-label"><?php _e('Username', 'members'); ?></label>
                        <div class="members-form-input disabled"><?php echo esc_html($user->user_login); ?></div>
                    </div>
                    
                    <div class="members-form-row">
                        <label class="members-form-label"><?php _e('Email', 'members'); ?></label>
                        <div class="members-form-input disabled"><?php echo esc_html($user->user_email); ?></div>
                    </div>
                    
                    <div class="members-form-row">
                        <label class="members-form-label"><?php _e('Display Name', 'members'); ?></label>
                        <div class="members-form-input disabled"><?php echo esc_html($user->display_name); ?></div>
                    </div>
                    
                    <div class="members-form-row">
                        <a href="<?php echo esc_url(admin_url('profile.php')); ?>" class="members-form-submit"><?php _e('Edit Profile', 'members'); ?></a>
                        <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>" class="members-form-submit"><?php _e('Log Out', 'members'); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for subscription cancellation confirmation -->
<div id="cancel-subscription-modal" class="members-modal-overlay">
    <div class="members-modal">
        <span class="members-modal-close" onclick="closeModal()">&times;</span>
        <h3 class="members-modal-title"><?php _e('Confirm Cancellation', 'members'); ?></h3>
        <div class="members-modal-content">
            <p><?php _e('Are you sure you want to cancel your membership?', 'members'); ?> <span id="subscription-name"></span></p>
            <p><?php _e('This action cannot be undone. You will lose access when your current billing period ends.', 'members'); ?></p>
        </div>
        <div class="members-modal-actions">
            <button class="members-form-submit members-modal-action" onclick="closeModal()"><?php _e('Keep Membership', 'members'); ?></button>
            <a href="#" id="confirm-cancel-link" class="members-subscription-action cancel members-modal-action"><?php _e('Cancel Membership', 'members'); ?></a>
        </div>
    </div>
</div>

<!-- Modal for payment method update -->
<div id="update-payment-modal" class="members-modal-overlay">
    <div class="members-modal">
        <span class="members-modal-close" onclick="closeModal()">&times;</span>
        <h3 class="members-modal-title"><?php _e('Update Payment Method', 'members'); ?></h3>
        <div class="members-modal-content">
            <!-- Content would be populated by JavaScript -->
            <p><?php _e('Select a payment method for this subscription:', 'members'); ?></p>
            
            <div id="payment-method-selector">
                <!-- Payment methods would be populated here -->
                <p><?php _e('Loading payment methods...', 'members'); ?></p>
            </div>
        </div>
        <div class="members-modal-actions">
            <button class="members-form-submit members-modal-action" onclick="closeModal()"><?php _e('Cancel', 'members'); ?></button>
            <button class="members-form-submit members-modal-action" onclick="updatePaymentMethod()"><?php _e('Update', 'members'); ?></button>
        </div>
    </div>
</div>

<script type="text/javascript">
    // Modal functionality
    function showCancelModal(element) {
        var subscriptionId = element.getAttribute('data-subscription-id');
        var subscriptionName = element.getAttribute('data-subscription-name');
        var cancelUrl = element.getAttribute('data-cancel-url');
        
        document.getElementById('subscription-name').textContent = subscriptionName;
        document.getElementById('confirm-cancel-link').href = cancelUrl;
        
        document.getElementById('cancel-subscription-modal').style.display = 'flex';
    }
    
    function showUpdateModal(element) {
        var subscriptionId = element.getAttribute('data-subscription-id');
        
        // Here you would populate the payment methods
        // For now, just show the modal
        document.getElementById('update-payment-modal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('cancel-subscription-modal').style.display = 'none';
        document.getElementById('update-payment-modal').style.display = 'none';
    }
    
    function updatePaymentMethod() {
        // Implementation would vary based on payment gateway
        closeModal();
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        var cancelModal = document.getElementById('cancel-subscription-modal');
        var updateModal = document.getElementById('update-payment-modal');
        
        if (event.target === cancelModal) {
            cancelModal.style.display = 'none';
        } else if (event.target === updateModal) {
            updateModal.style.display = 'none';
        }
    }
</script>