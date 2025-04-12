<?php
/**
 * Transactions Admin Page
 */

namespace Members\Subscriptions\admin;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions;

// Process bulk actions and list table
$transactions_table->process_bulk_action();
$transactions_table->prepare_items();

// Get transaction counts
global $wpdb;
$table_name = Subscriptions\get_transactions_table_name();
$table_exists = false;

$counts = [
    'all' => $transactions_table->get_total_items(),
    'pending' => 0,
    'complete' => 0,
    'failed' => 0,
    'refunded' => 0,
];

// Only count if the table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
if ($table_exists) {
    $counts['pending'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
    $counts['complete'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'complete'");
    $counts['failed'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'failed'");
    $counts['refunded'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'refunded'");
}

// Transaction statistics
$total_revenue = 0;
$total_refunds = 0;
$monthly_revenue = 0;
$today_revenue = 0;

if ($table_exists) {
    // Total completed revenue
    $total_revenue = $wpdb->get_var(
        "SELECT SUM(amount) FROM $table_name WHERE status = 'complete' AND txn_type = 'payment'"
    );
    $total_revenue = $total_revenue ? $total_revenue : 0;
    
    // Total refunds
    $total_refunds = $wpdb->get_var(
        "SELECT SUM(amount) FROM $table_name WHERE status = 'refunded' OR txn_type = 'refund'"
    );
    $total_refunds = $total_refunds ? $total_refunds : 0;
    
    // This month's revenue
    $first_day_of_month = date('Y-m-01 00:00:00');
    $monthly_revenue = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(amount) FROM $table_name WHERE status = 'complete' AND txn_type = 'payment' AND created_at >= %s",
            $first_day_of_month
        )
    );
    $monthly_revenue = $monthly_revenue ? $monthly_revenue : 0;
    
    // Today's revenue
    $today_start = date('Y-m-d 00:00:00');
    $today_revenue = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(amount) FROM $table_name WHERE status = 'complete' AND txn_type = 'payment' AND created_at >= %s",
            $today_start
        )
    );
    $today_revenue = $today_revenue ? $today_revenue : 0;
}

// Check if we need to show a message
if (isset($_REQUEST['message'])) {
    $message_type = sanitize_key($_REQUEST['message']);
    switch ($message_type) {
        case 'approved':
            $message = __('Transaction approved successfully.', 'members');
            $message_class = 'success';
            break;
        case 'rejected':
            $message = __('Transaction rejected.', 'members');
            $message_class = 'info';
            break;
        case 'already_approved':
            $message = __('Transaction was already approved.', 'members');
            $message_class = 'warning';
            break;
        case 'already_rejected':
            $message = __('Transaction was already rejected.', 'members');
            $message_class = 'warning';
            break;
        case 'refunded':
            $message = __('Transaction refunded successfully.', 'members');
            $message_class = 'success';
            break;
        case 'updated':
            $message = __('Transaction updated successfully.', 'members');
            $message_class = 'success';
            break;
        case 'error':
            $message = __('An error occurred. Please try again.', 'members');
            $message_class = 'error';
            break;
        default:
            $message = '';
            $message_class = '';
    }
}

// Safely get request values
$page_val = isset($_REQUEST['page']) ? sanitize_key($_REQUEST['page']) : '';
$current_status = isset($_REQUEST['status']) ? sanitize_key($_REQUEST['status']) : '';
$current_gateway = isset($_REQUEST['gateway']) ? sanitize_key($_REQUEST['gateway']) : '';
$date_from = isset($_REQUEST['date_from']) ? sanitize_text_field($_REQUEST['date_from']) : '';
$date_to = isset($_REQUEST['date_to']) ? sanitize_text_field($_REQUEST['date_to']) : '';
?>

<style>
    .transaction-status {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 12px;
        line-height: 1;
        text-transform: uppercase;
        font-weight: bold;
    }
    .transaction-status-complete {
        background-color: #d4edda;
        color: #155724;
    }
    .transaction-status-pending {
        background-color: #fff3cd;
        color: #856404;
    }
    .transaction-status-failed {
        background-color: #f8d7da;
        color: #721c24;
    }
    .transaction-status-refunded {
        background-color: #e2e3e5;
        color: #383d41;
    }
    .transaction-type {
        display: inline-block;
        padding: 3px 6px;
        border-radius: 3px;
        font-size: 11px;
        text-transform: uppercase;
    }
    .transaction-type-payment {
        background-color: #e3f2fd;
        color: #0c63e4;
    }
    .transaction-type-refund {
        background-color: #fdf2e9;
        color: #fd7e14;
    }
    .transaction-type-renewal {
        background-color: #e9ecef;
        color: #495057;
    }
    .transaction-actions a {
        text-decoration: none;
        margin-right: 8px;
    }
    .transaction-actions a:last-child {
        margin-right: 0;
    }
    .transaction-actions .approve {
        color: #28a745;
    }
    .transaction-actions .reject {
        color: #dc3545;
    }
    .transaction-actions .view {
        color: #007bff;
    }
    .transaction-actions .refund {
        color: #fd7e14;
    }
    .status-filter {
        margin-bottom: 15px;
    }
    .status-filter ul {
        display: flex;
        list-style: none;
        padding: 0;
        margin: 0;
        border-bottom: 1px solid #dee2e6;
    }
    .status-filter li {
        margin: 0;
        padding: 0;
    }
    .status-filter a {
        display: block;
        padding: 8px 12px;
        text-decoration: none;
        font-weight: normal;
        color: #495057;
        border: 1px solid transparent;
        border-top-left-radius: 4px;
        border-top-right-radius: 4px;
        margin-bottom: -1px;
    }
    .status-filter a:hover {
        background-color: #f8f9fa;
    }
    .status-filter a.active {
        border-color: #dee2e6;
        border-bottom-color: #fff;
        background-color: #fff;
        font-weight: bold;
    }
    .status-count {
        display: inline-block;
        padding: 2px 5px;
        border-radius: 10px;
        background-color: #f8f9fa;
        color: #6c757d;
        font-size: 11px;
        margin-left: 5px;
    }
    .status-count.status-count-pending {
        background-color: #fff3cd;
        color: #856404;
    }
    .admin-notification {
        padding: 12px 15px;
        margin: 15px 0;
        border-radius: 4px;
        border-left: 4px solid #ccc;
    }
    .admin-notification.admin-notification-success {
        background-color: #d4edda;
        border-left-color: #28a745;
        color: #155724;
    }
    .admin-notification.admin-notification-error {
        background-color: #f8d7da;
        border-left-color: #dc3545;
        color: #721c24;
    }
    .admin-notification.admin-notification-warning {
        background-color: #fff3cd;
        border-left-color: #ffc107;
        color: #856404;
    }
    .admin-notification.admin-notification-info {
        background-color: #d1ecf1;
        border-left-color: #17a2b8;
        color: #0c5460;
    }
    .dashboard-stats {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -10px 20px;
    }
    .dashboard-stat-box {
        flex: 1 0 200px;
        margin: 0 10px 20px;
        padding: 20px;
        background-color: #fff;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .dashboard-stat-box h3 {
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 16px;
        color: #555;
    }
    .dashboard-stat-box .stat-value {
        font-size: 28px;
        font-weight: bold;
        margin-bottom: 5px;
        color: #333;
    }
    .dashboard-stat-box .stat-description {
        color: #777;
        font-size: 12px;
    }
    .transaction-filters {
        display: inline-block;
        margin-left: 10px;
    }
    .date-filter {
        display: inline-block;
        margin-left: 10px;
    }
    .date-filter input {
        width: 95px;
    }
</style>

<div class="wrap members-subscriptions-wrap">
    <h1 class="wp-heading-inline"><?php _e('Transactions', 'members'); ?></h1>
    
    <?php if (!empty($message)) : ?>
        <div class="admin-notification admin-notification-<?php echo esc_attr($message_class); ?>">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Dashboard Stats -->
    <div class="dashboard-stats">
        <div class="dashboard-stat-box">
            <h3><?php _e('Total Revenue', 'members'); ?></h3>
            <div class="stat-value">$<?php echo number_format((float)$total_revenue, 2); ?></div>
            <div class="stat-description"><?php _e('Lifetime earnings', 'members'); ?></div>
        </div>
        
        <div class="dashboard-stat-box">
            <h3><?php _e('Monthly Revenue', 'members'); ?></h3>
            <div class="stat-value">$<?php echo number_format((float)$monthly_revenue, 2); ?></div>
            <div class="stat-description"><?php _e('This month', 'members'); ?></div>
        </div>
        
        <div class="dashboard-stat-box">
            <h3><?php _e('Today', 'members'); ?></h3>
            <div class="stat-value">$<?php echo number_format((float)$today_revenue, 2); ?></div>
            <div class="stat-description"><?php _e('Revenue today', 'members'); ?></div>
        </div>
        
        <div class="dashboard-stat-box">
            <h3><?php _e('Pending Transactions', 'members'); ?></h3>
            <div class="stat-value"><?php echo intval($counts['pending']); ?></div>
            <div class="stat-description"><?php _e('Awaiting approval', 'members'); ?></div>
        </div>
    </div>
    
    <!-- Status Filter Tabs -->
    <div class="status-filter">
        <ul>
            <li>
                <a href="<?php echo esc_url(admin_url('admin.php?page=members-transactions')); ?>" class="<?php echo empty($current_status) ? 'active' : ''; ?>">
                    <?php _e('All', 'members'); ?> <span class="status-count"><?php echo intval($counts['all']); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo esc_url(admin_url('admin.php?page=members-transactions&status=pending')); ?>" class="<?php echo $current_status === 'pending' ? 'active' : ''; ?>">
                    <?php _e('Pending', 'members'); ?> <span class="status-count status-count-pending"><?php echo intval($counts['pending']); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo esc_url(admin_url('admin.php?page=members-transactions&status=complete')); ?>" class="<?php echo $current_status === 'complete' ? 'active' : ''; ?>">
                    <?php _e('Completed', 'members'); ?> <span class="status-count"><?php echo intval($counts['complete']); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo esc_url(admin_url('admin.php?page=members-transactions&status=failed')); ?>" class="<?php echo $current_status === 'failed' ? 'active' : ''; ?>">
                    <?php _e('Failed', 'members'); ?> <span class="status-count"><?php echo intval($counts['failed']); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo esc_url(admin_url('admin.php?page=members-transactions&status=refunded')); ?>" class="<?php echo $current_status === 'refunded' ? 'active' : ''; ?>">
                    <?php _e('Refunded', 'members'); ?> <span class="status-count"><?php echo intval($counts['refunded']); ?></span>
                </a>
            </li>
        </ul>
    </div>
    
    <hr class="wp-header-end">
    
    <!-- Transactions Table -->
    <form id="transactions-filter" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($page_val); ?>" />
        
        <?php
        // Display the transactions
        $transactions_table->display();
        ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Add date filter
    $('.tablenav.top .bulkactions').after(
        '<div class="date-filter">' +
        '<label for="filter-by-date-from" class="screen-reader-text"><?php _e('From date', 'members'); ?></label>' +
        '<input type="text" name="date_from" id="filter-by-date-from" class="members-datepicker" value="<?php echo esc_attr($date_from); ?>" placeholder="<?php _e('From', 'members'); ?>" />' +
        
        '<label for="filter-by-date-to" class="screen-reader-text"><?php _e('To date', 'members'); ?></label>' +
        '<input type="text" name="date_to" id="filter-by-date-to" class="members-datepicker" value="<?php echo esc_attr($date_to); ?>" placeholder="<?php _e('To', 'members'); ?>" />' +
        '</div>'
    );
    
    // Add filter by gateway to the top of the table
    $('.tablenav.top .bulkactions').after(
        '<div class="transaction-filters">' +
        '<label for="filter-by-gateway" class="screen-reader-text"><?php _e('Filter by gateway', 'members'); ?></label>' +
        '<select name="gateway" id="filter-by-gateway">' +
        '<option value=""><?php _e('All gateways', 'members'); ?></option>' +
        '<option value="manual" <?php selected($current_gateway, 'manual'); ?>><?php _e('Manual', 'members'); ?></option>' +
        '<option value="stripe" <?php selected($current_gateway, 'stripe'); ?>><?php _e('Stripe', 'members'); ?></option>' +
        '</select>' +
        '</div>'
    );
    
    // Initialize datepickers if jQuery UI is available
    if ($.fn.datepicker) {
        $('.members-datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true
        });
    }
    
    // Add confirmation for approval/rejection actions
    $(document).on('click', '.transaction-actions .approve', function(e) {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to approve this transaction?', 'members')); ?>')) {
            e.preventDefault();
        }
    });
    
    $(document).on('click', '.transaction-actions .reject', function(e) {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to reject this transaction? This will also fail any associated subscription.', 'members')); ?>')) {
            e.preventDefault();
        }
    });
    
    // Auto-submit on gateway change
    $('#filter-by-gateway').change(function() {
        $('#transactions-filter').submit();
    });
});
</script>