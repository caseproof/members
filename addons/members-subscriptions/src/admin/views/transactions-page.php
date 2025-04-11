<?php
/**
 * Transactions Admin Page
 */

namespace Members\Subscriptions\admin;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

// Process bulk actions and list table
$transactions_table->process_bulk_action();
$transactions_table->prepare_items();

// Check if we need to show a message
$message = '';
if (isset($_REQUEST['message'])) {
    $message_type = sanitize_key($_REQUEST['message']);
    switch ($message_type) {
        case 'completed':
            $message = __('Transaction marked as complete.', 'members');
            break;
        case 'refunded':
            $message = __('Transaction refunded successfully.', 'members');
            break;
        case 'updated':
            $message = __('Transaction updated successfully.', 'members');
            break;
        case 'error':
            $message = __('An error occurred. Please try again.', 'members');
            break;
    }
}

// Safely get request values
$page_val = isset($_REQUEST['page']) ? sanitize_key($_REQUEST['page']) : '';
$current_status = isset($_REQUEST['status']) ? sanitize_key($_REQUEST['status']) : '';
$current_gateway = isset($_REQUEST['gateway']) ? sanitize_key($_REQUEST['gateway']) : '';
$date_from = isset($_REQUEST['date_from']) ? sanitize_text_field($_REQUEST['date_from']) : '';
$date_to = isset($_REQUEST['date_to']) ? sanitize_text_field($_REQUEST['date_to']) : '';
?>

<div class="wrap members-subscriptions-wrap">
    <h1 class="wp-heading-inline"><?php _e('Transactions', 'members'); ?></h1>
    
    <?php if (!empty($message)) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <form id="transactions-filter" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($page_val); ?>" />
        
        <?php
        // Display search box
        $transactions_table->search_box(__('Search Transactions', 'members'), 'transaction-search');
        
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
    
    // Add filter by status and gateway to the top of the table
    $('.tablenav.top .bulkactions').after(
        '<div class="transaction-filters">' +
        '<label for="filter-by-status" class="screen-reader-text"><?php _e('Filter by status', 'members'); ?></label>' +
        '<select name="status" id="filter-by-status" class="transaction-status-filter">' +
        '<option value=""><?php _e('All statuses', 'members'); ?></option>' +
        '<option value="completed" <?php selected($current_status, 'completed'); ?>><?php _e('Completed', 'members'); ?></option>' +
        '<option value="pending" <?php selected($current_status, 'pending'); ?>><?php _e('Pending', 'members'); ?></option>' +
        '<option value="refunded" <?php selected($current_status, 'refunded'); ?>><?php _e('Refunded', 'members'); ?></option>' +
        '<option value="failed" <?php selected($current_status, 'failed'); ?>><?php _e('Failed', 'members'); ?></option>' +
        '</select>' +
        '</div>'
    );
    
    // Add gateway filter
    $('.transaction-filters').after(
        '<div class="transaction-filters">' +
        '<label for="filter-by-gateway" class="screen-reader-text"><?php _e('Filter by gateway', 'members'); ?></label>' +
        '<select name="gateway" id="filter-by-gateway" class="gateway-filter">' +
        '<option value=""><?php _e('All gateways', 'members'); ?></option>' +
        '<option value="stripe" <?php selected($current_gateway, 'stripe'); ?>><?php _e('Stripe', 'members'); ?></option>' +
        '<option value="manual" <?php selected($current_gateway, 'manual'); ?>><?php _e('Manual', 'members'); ?></option>' +
        '</select>' +
        '</div>'
    );
});
</script>