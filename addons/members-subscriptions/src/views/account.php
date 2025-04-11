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
    echo '<div class="members-login-required">';
    echo '<p>' . __('Please log in to view your account.', 'members') . '</p>';
    echo '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="button">' . __('Log In', 'members') . '</a>';
    echo '</div>';
    return;
}

// Get user data
$user = get_userdata($user_id);

// Get subscriptions
$subscriptions = get_user_subscriptions($user_id);

// Get transactions
$transactions = get_transactions([
    'user_id' => $user_id,
    'orderby' => 'created_at',
    'order' => 'DESC',
]);

// Account tabs
$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'subscriptions';

?>
<div class="members-subscription-container">
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
            <li class="members-account-tab <?php echo $active_tab === 'account' ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(add_query_arg('tab', 'account')); ?>">
                    <?php _e('Account Details', 'members'); ?>
                </a>
            </li>
        </ul>
        
        <div class="members-account-content">
            <div id="subscriptions" class="members-account-tab-content <?php echo $active_tab === 'subscriptions' ? 'active' : ''; ?>">
                <h3><?php _e('Your Memberships', 'members'); ?></h3>
                
                <?php if (empty($subscriptions)): ?>
                    <div class="members-message members-message-info">
                        <p><?php _e('You do not have any active memberships.', 'members'); ?></p>
                    </div>
                <?php else: ?>
                    <table class="members-subscriptions-table">
                        <thead>
                            <tr>
                                <th><?php _e('Membership', 'members'); ?></th>
                                <th><?php _e('Status', 'members'); ?></th>
                                <th><?php _e('Started', 'members'); ?></th>
                                <th><?php _e('Renews', 'members'); ?></th>
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
                                <td class="members-subscription-actions">
                                    <?php if ($subscription->status === 'active'): ?>
                                        <a href="<?php echo esc_url(add_query_arg(['action' => 'cancel', 'subscription_id' => $subscription->id])); ?>" class="members-subscription-action cancel" onclick="return confirm('<?php esc_attr_e('Are you sure you want to cancel this membership?', 'members'); ?>');">
                                            <?php _e('Cancel', 'members'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
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
                                $amount = number_format_i18n($transaction->total, 2);
                            ?>
                            <tr>
                                <td><?php echo esc_html($created); ?></td>
                                <td><?php echo esc_html($product->post_title); ?></td>
                                <td><?php echo esc_html($amount); ?></td>
                                <td>
                                    <span class="members-transaction-status members-transaction-status-<?php echo esc_attr($transaction->status); ?>">
                                        <?php echo esc_html($transaction->status); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($transaction->trans_num); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
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

<?php
// Process subscription cancellation
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['subscription_id'])) {
    $subscription_id = absint($_GET['subscription_id']);
    
    // Verify subscription belongs to this user
    $subscription = get_subscription($subscription_id);
    
    if ($subscription && $subscription->user_id === $user_id) {
        if (cancel_subscription($subscription_id)) {
            echo '<script>
                window.location.href = "' . esc_url(remove_query_arg(['action', 'subscription_id'])) . '";
                alert("' . esc_js(__('Your membership has been cancelled.', 'members')) . '");
            </script>';
        } else {
            echo '<script>
                alert("' . esc_js(__('There was an error cancelling your membership. Please try again.', 'members')) . '");
            </script>';
        }
    }
}
?>