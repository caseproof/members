<?php
/**
 * Subscriptions Admin Page
 */

namespace Members\Subscriptions\admin;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

// Process bulk actions and list table
$subscriptions_table->process_bulk_action();
$subscriptions_table->prepare_items();

// Check if we need to show a message
$message = '';
if (isset($_REQUEST['message'])) {
    switch ($_REQUEST['message']) {
        case 'activated':
            $message = __('Subscription activated successfully.', 'members');
            break;
        case 'cancelled':
            $message = __('Subscription cancelled successfully.', 'members');
            break;
        case 'updated':
            $message = __('Subscription updated successfully.', 'members');
            break;
        case 'error':
            $message = __('An error occurred. Please try again.', 'members');
            break;
    }
}
?>

<div class="wrap members-subscriptions-wrap">
    <h1 class="wp-heading-inline"><?php _e('Subscriptions', 'members'); ?></h1>
    
    <?php if (!empty($message)) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <form id="subscriptions-filter" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
        
        <?php 
        // Display search box
        $subscriptions_table->search_box(__('Search Subscriptions', 'members'), 'subscription-search');
        
        // Display the subscriptions
        $subscriptions_table->display(); 
        ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Add filter by status to the top of the table
    $('.tablenav.top .bulkactions').after(
        '<div class="subscription-filters">' +
        '<label for="filter-by-status" class="screen-reader-text"><?php _e('Filter by status', 'members'); ?></label>' +
        '<select name="status" id="filter-by-status" class="subscription-status-filter">' +
        '<option value=""><?php _e('All statuses', 'members'); ?></option>' +
        '<option value="active" <?php selected(isset($_REQUEST['status']) ? $_REQUEST['status'] : '', 'active'); ?>><?php _e('Active', 'members'); ?></option>' +
        '<option value="pending" <?php selected(isset($_REQUEST['status']) ? $_REQUEST['status'] : '', 'pending'); ?>><?php _e('Pending', 'members'); ?></option>' +
        '<option value="cancelled" <?php selected(isset($_REQUEST['status']) ? $_REQUEST['status'] : '', 'cancelled'); ?>><?php _e('Cancelled', 'members'); ?></option>' +
        '<option value="expired" <?php selected(isset($_REQUEST['status']) ? $_REQUEST['status'] : '', 'expired'); ?>><?php _e('Expired', 'members'); ?></option>' +
        '<option value="suspended" <?php selected(isset($_REQUEST['status']) ? $_REQUEST['status'] : '', 'suspended'); ?>><?php _e('Suspended', 'members'); ?></option>' +
        '<option value="trialing" <?php selected(isset($_REQUEST['status']) ? $_REQUEST['status'] : '', 'trialing'); ?>><?php _e('Trialing', 'members'); ?></option>' +
        '</select>' +
        '</div>'
    );
});
</script>