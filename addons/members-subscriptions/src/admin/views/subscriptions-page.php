<?php
/**
 * Subscriptions Admin Page
 */

namespace Members\Subscriptions\admin;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions;

// Get subscription stats
global $wpdb;
$subscriptions_table_name = Subscriptions\get_subscriptions_table_name();
$transactions_table_name = Subscriptions\get_transactions_table_name();

// Count active subscriptions
$active_count = $wpdb->get_var(
    "SELECT COUNT(*) FROM $subscriptions_table_name WHERE status = 'active'"
);

// Count pending subscriptions
$pending_count = $wpdb->get_var(
    "SELECT COUNT(*) FROM $subscriptions_table_name WHERE status = 'pending'"
);

// Count cancelled subscriptions
$cancelled_count = $wpdb->get_var(
    "SELECT COUNT(*) FROM $subscriptions_table_name WHERE status = 'cancelled'"
);

// Count expired subscriptions
$expired_count = $wpdb->get_var(
    "SELECT COUNT(*) FROM $subscriptions_table_name WHERE status = 'expired'"
);

// Count total subscriptions
$total_count = $wpdb->get_var(
    "SELECT COUNT(*) FROM $subscriptions_table_name"
);

// Calculate Monthly Recurring Revenue (MRR)
$mrr = $wpdb->get_var(
    "SELECT SUM(price) FROM $subscriptions_table_name 
    WHERE status = 'active' 
    AND period_type = 'month' 
    AND period = 1"
);

// Calculate Annual Recurring Revenue (ARR)
$arr = $wpdb->get_var(
    "SELECT SUM(price) FROM $subscriptions_table_name 
    WHERE status = 'active' 
    AND period_type = 'year' 
    AND period = 1"
);

// Convert annual to monthly for MRR calculation
$arr_as_mrr = $arr / 12;

// Total MRR
$total_mrr = $mrr + $arr_as_mrr;

// Get subscriptions expiring in next 30 days
$expiring_soon_count = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM $subscriptions_table_name 
        WHERE status = 'active' 
        AND expires_at IS NOT NULL 
        AND expires_at != '0000-00-00 00:00:00' 
        AND expires_at < %s 
        AND expires_at > %s",
        date('Y-m-d H:i:s', strtotime('+30 days')),
        date('Y-m-d H:i:s')
    )
);

// Calculate revenue in last 30 days
$last_30_days_revenue = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT SUM(total) FROM $transactions_table_name 
        WHERE status = 'complete' 
        AND txn_type != 'refund' 
        AND created_at > %s",
        date('Y-m-d H:i:s', strtotime('-30 days'))
    )
);

// Calculate refunds in last 30 days
$last_30_days_refunds = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT SUM(total) FROM $transactions_table_name 
        WHERE txn_type = 'refund' 
        AND created_at > %s",
        date('Y-m-d H:i:s', strtotime('-30 days'))
    )
);

// Process bulk actions and list table
$subscriptions_table->process_bulk_action();
$subscriptions_table->prepare_items();

// Check if we need to show a message
$message = '';
$message_type = 'success';
if (isset($_REQUEST['message'])) {
    $message_code = sanitize_key($_REQUEST['message']);
    switch ($message_code) {
        case 'activated':
            $message = __('Subscription activated successfully.', 'members');
            break;
        case 'cancelled':
            $message = __('Subscription cancelled successfully.', 'members');
            break;
        case 'reactivated':
            $message = __('Subscription reactivated successfully.', 'members');
            break;
        case 'renewed':
            $message = __('Subscription renewed successfully.', 'members');
            break;
        case 'updated':
            $message = __('Subscription updated successfully.', 'members');
            break;
        case 'deleted':
            $message = __('Subscription deleted successfully.', 'members');
            break;
        case 'bulk_activated':
            $message = __('Selected subscriptions activated successfully.', 'members');
            break;
        case 'bulk_cancelled':
            $message = __('Selected subscriptions cancelled successfully.', 'members');
            break;
        case 'bulk_deleted':
            $message = __('Selected subscriptions deleted successfully.', 'members');
            break;
        case 'error':
            $message = __('An error occurred. Please try again.', 'members');
            $message_type = 'error';
            break;
    }
}

// Safely get status value
$current_status = '';
if (isset($_REQUEST['status'])) {
    $current_status = sanitize_key($_REQUEST['status']);
}

// Get page value securely
$page_val = '';
if (isset($_REQUEST['page'])) {
    $page_val = sanitize_key($_REQUEST['page']);
}

// Check if viewing subscription detail
$view_subscription = false;
if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'view' && isset($_REQUEST['subscription'])) {
    $view_subscription = true;
    $subscription_id = intval($_REQUEST['subscription']);
}

// Handle subscription edit screen
$edit_subscription = false;
if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'edit' && isset($_REQUEST['subscription'])) {
    $edit_subscription = true;
    $subscription_id = intval($_REQUEST['subscription']);
}

// If viewing a subscription detail, include that view instead
if ($view_subscription) {
    include dirname(__FILE__) . '/subscription-details.php';
    return;
}

// If editing a subscription, include that view instead
if ($edit_subscription) {
    include dirname(__FILE__) . '/subscription-edit.php';
    return;
}

// Format numeric values
$total_mrr = number_format_i18n($total_mrr, 2);
$last_30_days_revenue = number_format_i18n($last_30_days_revenue ?: 0, 2);
$last_30_days_refunds = number_format_i18n($last_30_days_refunds ?: 0, 2);
?>

<div class="wrap members-subscriptions-wrap">
    <h1 class="wp-heading-inline"><?php _e('Subscriptions', 'members'); ?></h1>
    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=members_product')); ?>" class="page-title-action"><?php _e('Add New Product', 'members'); ?></a>
    
    <?php if (!empty($message)) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <!-- Dashboard Cards -->
    <div class="members-dashboard">
        <div class="members-stats-cards">
            <div class="members-card">
                <div class="members-card-header">
                    <h2><?php _e('Active Subscriptions', 'members'); ?></h2>
                </div>
                <div class="members-card-body">
                    <div class="members-card-value"><?php echo esc_html($active_count); ?></div>
                    <div class="members-card-description"><?php _e('Currently active subscribers', 'members'); ?></div>
                </div>
                <div class="members-card-footer">
                    <a href="<?php echo esc_url(add_query_arg(['status' => 'active'], admin_url('admin.php?page=members-subscriptions'))); ?>"><?php _e('View active subscriptions', 'members'); ?></a>
                </div>
            </div>
            
            <div class="members-card">
                <div class="members-card-header">
                    <h2><?php _e('Monthly Recurring Revenue', 'members'); ?></h2>
                </div>
                <div class="members-card-body">
                    <div class="members-card-value"><?php echo esc_html($total_mrr); ?></div>
                    <div class="members-card-description"><?php _e('Current MRR from active subscriptions', 'members'); ?></div>
                </div>
                <div class="members-card-footer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=members-reports')); ?>"><?php _e('View revenue reports', 'members'); ?></a>
                </div>
            </div>
            
            <div class="members-card">
                <div class="members-card-header">
                    <h2><?php _e('Pending Subscriptions', 'members'); ?></h2>
                </div>
                <div class="members-card-body">
                    <div class="members-card-value"><?php echo esc_html($pending_count); ?></div>
                    <div class="members-card-description"><?php _e('Awaiting activation or payment', 'members'); ?></div>
                </div>
                <div class="members-card-footer">
                    <a href="<?php echo esc_url(add_query_arg(['status' => 'pending'], admin_url('admin.php?page=members-subscriptions'))); ?>"><?php _e('View pending subscriptions', 'members'); ?></a>
                </div>
            </div>
            
            <div class="members-card">
                <div class="members-card-header">
                    <h2><?php _e('Expiring Soon', 'members'); ?></h2>
                </div>
                <div class="members-card-body">
                    <div class="members-card-value"><?php echo esc_html($expiring_soon_count); ?></div>
                    <div class="members-card-description"><?php _e('Expiring in the next 30 days', 'members'); ?></div>
                </div>
                <div class="members-card-footer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=members-subscriptions&expiring=soon')); ?>"><?php _e('View soon to expire', 'members'); ?></a>
                </div>
            </div>
        </div>
        
        <div class="members-stats-secondary">
            <div class="members-card members-revenue-card">
                <div class="members-card-header">
                    <h2><?php _e('Revenue (Last 30 Days)', 'members'); ?></h2>
                </div>
                <div class="members-card-body members-revenue-details">
                    <div class="members-revenue-item">
                        <span class="members-revenue-label"><?php _e('Revenue:', 'members'); ?></span>
                        <span class="members-revenue-value"><?php echo esc_html($last_30_days_revenue); ?></span>
                    </div>
                    
                    <div class="members-revenue-item">
                        <span class="members-revenue-label"><?php _e('Refunds:', 'members'); ?></span>
                        <span class="members-revenue-value members-refund-value"><?php echo esc_html($last_30_days_refunds); ?></span>
                    </div>
                    
                    <div class="members-revenue-item members-total-revenue">
                        <span class="members-revenue-label"><?php _e('Net Revenue:', 'members'); ?></span>
                        <span class="members-revenue-value"><?php echo esc_html(number_format_i18n(($last_30_days_revenue ?: 0) - ($last_30_days_refunds ?: 0), 2)); ?></span>
                    </div>
                </div>
                <div class="members-card-footer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=members-transactions')); ?>"><?php _e('View all transactions', 'members'); ?></a>
                </div>
            </div>
            
            <div class="members-card members-status-card">
                <div class="members-card-header">
                    <h2><?php _e('Subscription Status', 'members'); ?></h2>
                </div>
                <div class="members-card-body members-status-breakdown">
                    <div class="members-status-item">
                        <span class="members-status-label"><?php _e('Active:', 'members'); ?></span>
                        <span class="members-status-value"><?php echo esc_html($active_count); ?></span>
                        <span class="members-status-percentage"><?php echo $total_count > 0 ? esc_html(round(($active_count / $total_count) * 100)) . '%' : '0%'; ?></span>
                        <div class="members-status-bar">
                            <div class="members-status-bar-fill members-status-active" style="width: <?php echo $total_count > 0 ? esc_attr(($active_count / $total_count) * 100) . '%' : '0%'; ?>"></div>
                        </div>
                    </div>
                    
                    <div class="members-status-item">
                        <span class="members-status-label"><?php _e('Pending:', 'members'); ?></span>
                        <span class="members-status-value"><?php echo esc_html($pending_count); ?></span>
                        <span class="members-status-percentage"><?php echo $total_count > 0 ? esc_html(round(($pending_count / $total_count) * 100)) . '%' : '0%'; ?></span>
                        <div class="members-status-bar">
                            <div class="members-status-bar-fill members-status-pending" style="width: <?php echo $total_count > 0 ? esc_attr(($pending_count / $total_count) * 100) . '%' : '0%'; ?>"></div>
                        </div>
                    </div>
                    
                    <div class="members-status-item">
                        <span class="members-status-label"><?php _e('Cancelled:', 'members'); ?></span>
                        <span class="members-status-value"><?php echo esc_html($cancelled_count); ?></span>
                        <span class="members-status-percentage"><?php echo $total_count > 0 ? esc_html(round(($cancelled_count / $total_count) * 100)) . '%' : '0%'; ?></span>
                        <div class="members-status-bar">
                            <div class="members-status-bar-fill members-status-cancelled" style="width: <?php echo $total_count > 0 ? esc_attr(($cancelled_count / $total_count) * 100) . '%' : '0%'; ?>"></div>
                        </div>
                    </div>
                    
                    <div class="members-status-item">
                        <span class="members-status-label"><?php _e('Expired:', 'members'); ?></span>
                        <span class="members-status-value"><?php echo esc_html($expired_count); ?></span>
                        <span class="members-status-percentage"><?php echo $total_count > 0 ? esc_html(round(($expired_count / $total_count) * 100)) . '%' : '0%'; ?></span>
                        <div class="members-status-bar">
                            <div class="members-status-bar-fill members-status-expired" style="width: <?php echo $total_count > 0 ? esc_attr(($expired_count / $total_count) * 100) . '%' : '0%'; ?>"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Advanced Filters -->
    <div class="members-advanced-filters members-card">
        <div class="members-filters-header">
            <h2><?php _e('Advanced Filters', 'members'); ?></h2>
            <button type="button" class="button toggle-filters" aria-expanded="false">
                <span class="dashicons dashicons-filter"></span>
                <?php _e('Show/Hide Filters', 'members'); ?>
            </button>
        </div>
        
        <div class="members-filters-content" style="display: none;">
            <form id="subscriptions-advanced-filter" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($page_val); ?>" />
                
                <div class="members-filter-row">
                    <div class="members-filter-column">
                        <label for="filter-by-status"><?php _e('Status:', 'members'); ?></label>
                        <select name="status" id="filter-by-status">
                            <option value=""><?php _e('All statuses', 'members'); ?></option>
                            <option value="active" <?php selected($current_status, 'active'); ?>><?php _e('Active', 'members'); ?></option>
                            <option value="pending" <?php selected($current_status, 'pending'); ?>><?php _e('Pending', 'members'); ?></option>
                            <option value="cancelled" <?php selected($current_status, 'cancelled'); ?>><?php _e('Cancelled', 'members'); ?></option>
                            <option value="expired" <?php selected($current_status, 'expired'); ?>><?php _e('Expired', 'members'); ?></option>
                            <option value="suspended" <?php selected($current_status, 'suspended'); ?>><?php _e('Suspended', 'members'); ?></option>
                            <option value="trialing" <?php selected($current_status, 'trialing'); ?>><?php _e('Trialing', 'members'); ?></option>
                        </select>
                    </div>
                    
                    <div class="members-filter-column">
                        <label for="filter-by-gateway"><?php _e('Gateway:', 'members'); ?></label>
                        <select name="gateway" id="filter-by-gateway">
                            <option value=""><?php _e('All gateways', 'members'); ?></option>
                            <option value="stripe" <?php selected(isset($_REQUEST['gateway']) ? $_REQUEST['gateway'] : '', 'stripe'); ?>><?php _e('Stripe', 'members'); ?></option>
                            <option value="manual" <?php selected(isset($_REQUEST['gateway']) ? $_REQUEST['gateway'] : '', 'manual'); ?>><?php _e('Manual', 'members'); ?></option>
                        </select>
                    </div>
                    
                    <div class="members-filter-column">
                        <label for="filter-by-date-from"><?php _e('Date From:', 'members'); ?></label>
                        <input type="date" name="date_from" id="filter-by-date-from" value="<?php echo isset($_REQUEST['date_from']) ? esc_attr($_REQUEST['date_from']) : ''; ?>" />
                    </div>
                    
                    <div class="members-filter-column">
                        <label for="filter-by-date-to"><?php _e('Date To:', 'members'); ?></label>
                        <input type="date" name="date_to" id="filter-by-date-to" value="<?php echo isset($_REQUEST['date_to']) ? esc_attr($_REQUEST['date_to']) : ''; ?>" />
                    </div>
                </div>
                
                <div class="members-filter-actions">
                    <button type="submit" class="button button-primary"><?php _e('Apply Filters', 'members'); ?></button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=members-subscriptions')); ?>" class="button"><?php _e('Reset Filters', 'members'); ?></a>
                    
                    <div class="members-export-actions">
                        <button type="submit" name="export" value="csv" class="button"><?php _e('Export CSV', 'members'); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Subscriptions List Table -->
    <form id="subscriptions-filter" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($page_val); ?>" />
        
        <?php 
        // Display search box
        $subscriptions_table->search_box(__('Search Subscriptions', 'members'), 'subscription-search');
        
        // Display the subscriptions
        $subscriptions_table->display(); 
        ?>
    </form>
</div>

<style>
/* Dashboard Styles */
.members-dashboard {
    margin-bottom: 20px;
}

.members-stats-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
}

.members-stats-secondary {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.members-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.members-stats-cards .members-card {
    flex: 1;
    min-width: 200px;
    display: flex;
    flex-direction: column;
}

.members-stats-secondary .members-card {
    flex: 1;
    min-width: 300px;
}

.members-card-header {
    padding: 15px 20px 0;
}

.members-card-header h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #23282d;
}

.members-card-body {
    padding: 15px 20px;
    flex-grow: 1;
}

.members-card-value {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 5px;
    color: #2271b1;
}

.members-card-description {
    color: #646970;
    font-size: 13px;
}

.members-card-footer {
    padding: 10px 20px;
    border-top: 1px solid #f0f0f0;
}

.members-card-footer a {
    font-size: 13px;
    text-decoration: none;
    display: flex;
    align-items: center;
}

.members-revenue-details {
    padding-top: 10px;
}

.members-revenue-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid #f0f0f0;
}

.members-total-revenue {
    font-weight: 600;
    margin-top: 8px;
    border-bottom: none;
}

.members-refund-value {
    color: #d63638;
}

.members-status-breakdown {
    padding-top: 10px;
}

.members-status-item {
    margin-bottom: 12px;
}

.members-status-label {
    display: inline-block;
    width: 70px;
    font-weight: 500;
}

.members-status-value {
    display: inline-block;
    width: 50px;
    text-align: right;
    font-weight: 600;
}

.members-status-percentage {
    margin-left: 10px;
    font-size: 13px;
    color: #646970;
}

.members-status-bar {
    height: 6px;
    background-color: #f0f0f0;
    border-radius: 3px;
    margin-top: 5px;
    overflow: hidden;
}

.members-status-bar-fill {
    height: 100%;
    border-radius: 3px;
}

.members-status-active {
    background-color: #1d9d4f;
}

.members-status-pending {
    background-color: #d69e2e;
}

.members-status-cancelled {
    background-color: #646970;
}

.members-status-expired {
    background-color: #d63638;
}

/* Advanced Filters */
.members-advanced-filters {
    margin-bottom: 20px;
}

.members-filters-header {
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #f0f0f0;
}

.members-filters-header h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.toggle-filters {
    display: flex;
    align-items: center;
}

.toggle-filters .dashicons {
    margin-right: 5px;
}

.members-filters-content {
    padding: 20px;
}

.members-filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
}

.members-filter-column {
    flex: 1;
    min-width: 200px;
}

.members-filter-column label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.members-filter-column select,
.members-filter-column input {
    width: 100%;
}

.members-filter-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 20px;
}

.members-export-actions {
    text-align: right;
}

/* Subscription Filters - Legacy */
.subscription-filters {
    display: inline-block;
    margin-left: 10px;
}

.subscription-status-filter {
    max-width: 200px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle advanced filters
    $('.toggle-filters').on('click', function() {
        $('.members-filters-content').slideToggle();
        
        var expanded = $(this).attr('aria-expanded') === 'true';
        $(this).attr('aria-expanded', !expanded);
    });
    
    // Bulk export button handler
    $('button[name="export"]').on('click', function() {
        // Add export parameter to the form
        $('#subscriptions-advanced-filter').append('<input type="hidden" name="export" value="csv" />');
        return true;
    });
    
    // Date range picker functionality if needed
    if ($.fn.datepicker) {
        $('#filter-by-date-from, #filter-by-date-to').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    }
});
</script>