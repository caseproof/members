<?php
/**
 * Subscription Edit Form
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

// Check if form is submitted
if (isset($_POST['save_subscription']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'edit_subscription_' . $subscription_id)) {
    // Get form data
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : $subscription->status;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : $subscription->price;
    $period = isset($_POST['period']) ? intval($_POST['period']) : $subscription->period;
    $period_type = isset($_POST['period_type']) ? sanitize_text_field($_POST['period_type']) : $subscription->period_type;
    $expires_at = isset($_POST['expires_at']) ? sanitize_text_field($_POST['expires_at']) : $subscription->expires_at;
    
    // Prepare update data
    $data = [
        'status' => $status,
        'price' => $price,
        'period' => $period,
        'period_type' => $period_type,
    ];
    
    // Only update expires_at if it's not empty
    if (!empty($expires_at)) {
        $data['expires_at'] = $expires_at;
    }
    
    // Calculate total based on price
    if ($price != $subscription->price) {
        $tax_amount = 0;
        $tax_rate = 0;
        
        // If there is tax info, recalculate
        if (!empty($subscription->tax_rate)) {
            $tax_rate = floatval($subscription->tax_rate);
            $tax_amount = $price * ($tax_rate / 100);
            $data['tax_amount'] = $tax_amount;
        }
        
        $data['total'] = $price + $tax_amount;
    }
    
    // Update subscription
    $updated = Subscriptions\update_subscription($subscription_id, $data);
    
    if ($updated) {
        // Check if status has changed
        if ($status !== $subscription->status) {
            // Trigger status change action
            do_action('members_subscription_status_change', $subscription_id, $subscription->status, $status);
            
            // If status changed to active, update user roles
            if ($status === 'active') {
                Subscriptions\apply_membership_role($subscription->user_id, $subscription->product_id);
            }
            // If status changed to cancelled or expired, remove roles
            elseif (in_array($status, ['cancelled', 'expired'])) {
                $product_roles = Subscriptions\get_product_meta($subscription->product_id, '_membership_roles', []);
                
                if (!empty($product_roles)) {
                    $user = get_userdata($subscription->user_id);
                    
                    if ($user) {
                        foreach ($product_roles as $role) {
                            // Check if user has other active subscriptions with the same role
                            $keep_role = false;
                            $other_subscriptions = Subscriptions\get_user_subscriptions($subscription->user_id);
                            
                            foreach ($other_subscriptions as $other) {
                                if ($other->id == $subscription_id || $other->status !== 'active') {
                                    continue;
                                }
                                
                                $other_roles = Subscriptions\get_product_meta($other->product_id, '_membership_roles', []);
                                
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
            }
        }
        
        // Redirect back to the subscriptions list with success message
        wp_redirect(add_query_arg('message', 'updated', admin_url('admin.php?page=members-subscriptions')));
        exit;
    } else {
        // Show error message
        $update_error = __('There was an error updating the subscription. Please try again.', 'members');
    }
}

// Get user and product
$user = get_userdata($subscription->user_id);
$product = get_post($subscription->product_id);

// Format dates
$created_date = date('Y-m-d\TH:i', strtotime($subscription->created_at));
$expires_date = !empty($subscription->expires_at) && $subscription->expires_at !== '0000-00-00 00:00:00' ? date('Y-m-d\TH:i', strtotime($subscription->expires_at)) : '';

// Get subscription statuses
$statuses = Subscriptions\get_subscription_statuses();

// Get period options
$period_options = Subscriptions\get_subscription_period_options();
?>

<div class="wrap members-subscription-edit">
    <h1 class="wp-heading-inline"><?php _e('Edit Subscription', 'members'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=members-subscriptions')); ?>" class="page-title-action"><?php _e('Back to Subscriptions', 'members'); ?></a>
    <hr class="wp-header-end">
    
    <?php if (isset($update_error)) : ?>
    <div class="notice notice-error">
        <p><?php echo esc_html($update_error); ?></p>
    </div>
    <?php endif; ?>
    
    <form method="post" id="subscription-edit-form">
        <?php wp_nonce_field('edit_subscription_' . $subscription_id); ?>
        
        <div class="members-edit-container">
            <div class="members-edit-main">
                <div class="members-card">
                    <h2><?php _e('Subscription Details', 'members'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="status"><?php _e('Status', 'members'); ?></label>
                            </th>
                            <td>
                                <select name="status" id="status">
                                    <?php foreach ($statuses as $status_key => $status_label) : ?>
                                    <option value="<?php echo esc_attr($status_key); ?>" <?php selected($subscription->status, $status_key); ?>><?php echo esc_html($status_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Changing the status may affect user access and roles.', 'members'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="price"><?php _e('Price', 'members'); ?></label>
                            </th>
                            <td>
                                <input type="number" step="0.01" min="0" name="price" id="price" value="<?php echo esc_attr($subscription->price); ?>" class="regular-text" />
                                <p class="description"><?php _e('The recurring price for this subscription.', 'members'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="period"><?php _e('Billing Period', 'members'); ?></label>
                            </th>
                            <td>
                                <div class="members-billing-period">
                                    <input type="number" min="1" name="period" id="period" value="<?php echo esc_attr($subscription->period); ?>" class="small-text" />
                                    
                                    <select name="period_type" id="period_type">
                                        <?php foreach ($period_options as $period_key => $period_label) : ?>
                                        <option value="<?php echo esc_attr($period_key); ?>" <?php selected($subscription->period_type, $period_key); ?>><?php echo esc_html($period_label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <p class="description"><?php _e('The billing frequency for this subscription.', 'members'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="expires_at"><?php _e('Expiration Date', 'members'); ?></label>
                            </th>
                            <td>
                                <input type="datetime-local" name="expires_at" id="expires_at" value="<?php echo esc_attr($expires_date); ?>" class="regular-text" />
                                <p class="description"><?php _e('When this subscription expires. Leave blank for lifetime subscriptions.', 'members'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php if (!empty($subscription->tax_amount) || !empty($subscription->tax_rate)) : ?>
                <div class="members-card">
                    <h2><?php _e('Tax Information', 'members'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Tax Rate', 'members'); ?></th>
                            <td>
                                <p><?php echo esc_html($subscription->tax_rate); ?>%</p>
                                <?php if (!empty($subscription->tax_desc)) : ?>
                                <p class="description"><?php echo esc_html($subscription->tax_desc); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Tax Amount', 'members'); ?></th>
                            <td>
                                <p><?php echo number_format_i18n((float)$subscription->tax_amount, 2); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Total', 'members'); ?></th>
                            <td>
                                <p><strong><?php echo number_format_i18n((float)$subscription->total, 2); ?></strong></p>
                                <p class="description"><?php _e('Price + Tax', 'members'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>
                
                <?php if ($subscription->gateway === 'stripe' && !empty($subscription->subscr_id)) : ?>
                <div class="members-card">
                    <h2><?php _e('Stripe Information', 'members'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Stripe Subscription ID', 'members'); ?></th>
                            <td>
                                <p><?php echo esc_html($subscription->subscr_id); ?></p>
                                <p class="description"><?php _e('The Stripe subscription ID for this subscription.', 'members'); ?></p>
                            </td>
                        </tr>
                        
                        <?php if (!empty($subscription->cc_last4)) : ?>
                        <tr>
                            <th scope="row"><?php _e('Payment Method', 'members'); ?></th>
                            <td>
                                <p>
                                    <?php 
                                    echo esc_html(sprintf(
                                        __('Card ending in %s (Expires: %s/%s)', 'members'),
                                        $subscription->cc_last4,
                                        $subscription->cc_exp_month,
                                        $subscription->cc_exp_year
                                    )); 
                                    ?>
                                </p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="members-edit-sidebar">
                <div class="members-card">
                    <h2><?php _e('Subscription Information', 'members'); ?></h2>
                    
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('ID', 'members'); ?>:</span>
                        <span class="members-info-value">#<?php echo esc_html($subscription->id); ?></span>
                    </div>
                    
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('Created', 'members'); ?>:</span>
                        <span class="members-info-value"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($subscription->created_at)); ?></span>
                    </div>
                    
                    <div class="members-info-item">
                        <span class="members-info-label"><?php _e('Gateway', 'members'); ?>:</span>
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
                        <span class="members-info-label"><?php _e('Renewals', 'members'); ?>:</span>
                        <span class="members-info-value"><?php echo esc_html($subscription->renewal_count); ?></span>
                    </div>
                </div>
                
                <div class="members-card">
                    <h2><?php _e('User Information', 'members'); ?></h2>
                    
                    <?php if ($user) : ?>
                    <div class="members-user-mini">
                        <div class="members-user-avatar">
                            <?php echo get_avatar($user->ID, 40); ?>
                        </div>
                        
                        <div class="members-user-details">
                            <h3><?php echo esc_html($user->display_name); ?></h3>
                            <p class="members-user-email"><?php echo esc_html($user->user_email); ?></p>
                        </div>
                    </div>
                    
                    <div class="members-user-actions">
                        <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>" class="button"><?php _e('Edit User', 'members'); ?></a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=members-subscriptions&user_id=' . $user->ID)); ?>" class="button"><?php _e('View Subscriptions', 'members'); ?></a>
                    </div>
                    <?php else : ?>
                    <p><?php _e('User has been deleted.', 'members'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="members-card">
                    <h2><?php _e('Product Information', 'members'); ?></h2>
                    
                    <?php if ($product) : ?>
                    <div class="members-product-mini">
                        <h3><?php echo esc_html($product->post_title); ?></h3>
                        
                        <?php
                        // Get associated roles
                        $product_roles = Subscriptions\get_product_meta($product->ID, '_membership_roles', []);
                        
                        if (!empty($product_roles)) :
                        ?>
                        <div class="members-product-roles-mini">
                            <h4><?php _e('Roles:', 'members'); ?></h4>
                            <ul>
                                <?php foreach ($product_roles as $role) : 
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
                    </div>
                    
                    <div class="members-product-actions">
                        <a href="<?php echo esc_url(get_edit_post_link($product->ID)); ?>" class="button"><?php _e('Edit Product', 'members'); ?></a>
                        <a href="<?php echo esc_url(get_permalink($product->ID)); ?>" class="button" target="_blank"><?php _e('View Product', 'members'); ?></a>
                    </div>
                    <?php else : ?>
                    <p><?php _e('Product has been deleted.', 'members'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="members-submit-box members-card">
                    <div class="members-submit-box-actions">
                        <button type="submit" name="save_subscription" class="button button-primary button-large"><?php _e('Update Subscription', 'members'); ?></button>
                        
                        <a href="<?php echo esc_url(add_query_arg(['action' => 'view', 'subscription' => $subscription_id], admin_url('admin.php?page=members-subscriptions'))); ?>" class="button button-large"><?php _e('Cancel', 'members'); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
/* Edit Subscription Styles */
.members-edit-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 20px;
}

.members-edit-main {
    flex: 2;
    min-width: 500px;
}

.members-edit-sidebar {
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
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    font-size: 18px;
}

.members-info-item {
    margin-bottom: 10px;
}

.members-info-label {
    font-weight: bold;
    margin-right: 5px;
}

.members-billing-period {
    display: flex;
    align-items: center;
    gap: 10px;
}

.members-user-mini {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.members-user-avatar {
    margin-right: 10px;
}

.members-user-details h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
}

.members-user-email {
    margin: 0;
    font-size: 13px;
    color: #646970;
}

.members-user-actions,
.members-product-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.members-product-mini h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
}

.members-product-roles-mini h4 {
    margin: 0 0 5px 0;
    font-size: 13px;
    font-weight: 600;
}

.members-product-roles-mini ul {
    margin: 0 0 10px 0;
    padding-left: 20px;
    font-size: 13px;
}

.members-submit-box {
    position: sticky;
    top: 32px;
}

.members-submit-box-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.members-submit-box-actions .button {
    text-align: center;
    justify-content: center;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Update form before submission
    $('#subscription-edit-form').on('submit', function() {
        // Additional validation or confirmation if needed
        return true;
    });
});
</script>