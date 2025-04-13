<?php
/**
 * Products Admin Page
 */

namespace Members\Subscriptions\admin;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions;

// Initialize the products list table
$products_list_table = new Products_List_Table();

// Get product stats
global $wpdb;
$posts_table = $wpdb->posts;
$subscriptions_table = Subscriptions\get_subscriptions_table_name();
$transactions_table = Subscriptions\get_transactions_table_name();

// Count total products
$total_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $posts_table WHERE post_type = %s AND post_status = 'publish'",
    'members_product'
));

// Count total active subscriptions
$active_subs_count = $wpdb->get_var(
    "SELECT COUNT(*) FROM $subscriptions_table WHERE status = 'active'"
);

// Count total revenue
$total_revenue = $wpdb->get_var(
    "SELECT SUM(amount) FROM $transactions_table WHERE status = 'complete'"
);
$total_revenue = $total_revenue ? $total_revenue : 0;

// Get most popular product
$popular_product = $wpdb->get_row(
    "SELECT p.ID, p.post_title, COUNT(s.id) as subscription_count 
    FROM $posts_table p 
    JOIN $subscriptions_table s ON p.ID = s.product_id 
    WHERE p.post_type = 'members_product' 
    AND s.status = 'active' 
    GROUP BY p.ID 
    ORDER BY subscription_count DESC 
    LIMIT 1"
);

// Get newest product
$newest_product = $wpdb->get_row($wpdb->prepare(
    "SELECT ID, post_title, post_date 
    FROM $posts_table 
    WHERE post_type = %s 
    AND post_status = 'publish' 
    ORDER BY post_date DESC 
    LIMIT 1",
    'members_product'
));

// Get highest revenue product
$highest_revenue_product = $wpdb->get_row(
    "SELECT p.ID, p.post_title, SUM(t.amount) as total_revenue 
    FROM $posts_table p 
    JOIN $transactions_table t ON p.ID = t.product_id 
    WHERE p.post_type = 'members_product' 
    AND t.status = 'complete' 
    GROUP BY p.ID 
    ORDER BY total_revenue DESC 
    LIMIT 1"
);

// Process bulk actions and list table
$products_list_table->process_bulk_action();
$products_list_table->prepare_items();

// Check if we need to show a message
$message = '';
$message_type = 'success';
if (isset($_REQUEST['message'])) {
    $message_code = sanitize_key($_REQUEST['message']);
    switch ($message_code) {
        case 'activated':
            $message = __('Product activated successfully.', 'members');
            break;
        case 'deactivated':
            $message = __('Product deactivated successfully.', 'members');
            break;
        case 'deleted':
            $message = __('Product deleted successfully.', 'members');
            break;
        case 'error':
            $message = __('An error occurred. Please try again.', 'members');
            $message_type = 'error';
            break;
    }
}

?>

<div class="wrap members-products-wrap">
    <h1 class="wp-heading-inline"><?php _e('Subscription Products', 'members'); ?></h1>
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
                    <h2><?php _e('Total Products', 'members'); ?></h2>
                </div>
                <div class="members-card-body">
                    <div class="members-card-value"><?php echo esc_html($total_count); ?></div>
                    <div class="members-card-description"><?php _e('Published membership products', 'members'); ?></div>
                </div>
                <div class="members-card-footer">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=members_product')); ?>"><?php _e('Manage products', 'members'); ?></a>
                </div>
            </div>
            
            <div class="members-card">
                <div class="members-card-header">
                    <h2><?php _e('Active Subscriptions', 'members'); ?></h2>
                </div>
                <div class="members-card-body">
                    <div class="members-card-value"><?php echo esc_html($active_subs_count); ?></div>
                    <div class="members-card-description"><?php _e('Currently active subscribers', 'members'); ?></div>
                </div>
                <div class="members-card-footer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=members-subscriptions&status=active')); ?>"><?php _e('View subscriptions', 'members'); ?></a>
                </div>
            </div>
            
            <div class="members-card">
                <div class="members-card-header">
                    <h2><?php _e('Total Revenue', 'members'); ?></h2>
                </div>
                <div class="members-card-body">
                    <div class="members-card-value">$<?php echo esc_html(number_format_i18n($total_revenue, 2)); ?></div>
                    <div class="members-card-description"><?php _e('Lifetime revenue from all products', 'members'); ?></div>
                </div>
                <div class="members-card-footer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=members-transactions')); ?>"><?php _e('View transactions', 'members'); ?></a>
                </div>
            </div>
        </div>
        
        <div class="members-stats-secondary">
            <?php if ($popular_product) : ?>
            <div class="members-card">
                <div class="members-card-header">
                    <h2><?php _e('Most Popular Product', 'members'); ?></h2>
                </div>
                <div class="members-card-body">
                    <div class="members-product-name">
                        <a href="<?php echo esc_url(get_edit_post_link($popular_product->ID)); ?>">
                            <?php echo esc_html($popular_product->post_title); ?>
                        </a>
                    </div>
                    <div class="members-product-stats">
                        <?php printf(
                            _n('%s active subscription', '%s active subscriptions', $popular_product->subscription_count, 'members'),
                            number_format_i18n($popular_product->subscription_count)
                        ); ?>
                    </div>
                </div>
                <div class="members-card-footer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=members-subscriptions&product_id=' . $popular_product->ID)); ?>"><?php _e('View subscribers', 'members'); ?></a>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($newest_product) : ?>
            <div class="members-card">
                <div class="members-card-header">
                    <h2><?php _e('Newest Product', 'members'); ?></h2>
                </div>
                <div class="members-card-body">
                    <div class="members-product-name">
                        <a href="<?php echo esc_url(get_edit_post_link($newest_product->ID)); ?>">
                            <?php echo esc_html($newest_product->post_title); ?>
                        </a>
                    </div>
                    <div class="members-product-stats">
                        <?php echo esc_html(sprintf(
                            __('Created on %s', 'members'),
                            date_i18n(get_option('date_format'), strtotime($newest_product->post_date))
                        )); ?>
                    </div>
                </div>
                <div class="members-card-footer">
                    <a href="<?php echo esc_url(get_permalink($newest_product->ID)); ?>"><?php _e('View product', 'members'); ?></a>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($highest_revenue_product) : ?>
            <div class="members-card">
                <div class="members-card-header">
                    <h2><?php _e('Highest Revenue Product', 'members'); ?></h2>
                </div>
                <div class="members-card-body">
                    <div class="members-product-name">
                        <a href="<?php echo esc_url(get_edit_post_link($highest_revenue_product->ID)); ?>">
                            <?php echo esc_html($highest_revenue_product->post_title); ?>
                        </a>
                    </div>
                    <div class="members-product-stats">
                        <?php printf(
                            __('Revenue: $%s', 'members'),
                            number_format_i18n($highest_revenue_product->total_revenue, 2)
                        ); ?>
                    </div>
                </div>
                <div class="members-card-footer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=members-transactions&product_id=' . $highest_revenue_product->ID)); ?>"><?php _e('View transactions', 'members'); ?></a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Products List Table -->
    <form id="products-filter" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr(isset($_REQUEST['page']) ? sanitize_key($_REQUEST['page']) : ''); ?>" />
        
        <?php 
        // Display search box
        $products_list_table->search_box(__('Search Products', 'members'), 'product-search');
        
        // Display the products
        $products_list_table->display(); 
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
    margin-bottom: 20px;
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
    min-width: 220px;
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

.members-product-name {
    font-size: 16px;
    font-weight: 500;
    margin-bottom: 5px;
}

.members-product-name a {
    text-decoration: none;
    color: #2271b1;
}

.members-product-stats {
    color: #646970;
    font-size: 13px;
}

/* Table Styles */
.column-price,
.column-subscription {
    width: 15%;
}

.column-roles,
.column-active_members {
    width: 12%;
}

.column-total_revenue {
    width: 10%;
}

.column-date {
    width: 10%;
}

.column-actions {
    width: 15%;
}

.product-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.product-actions .button-small {
    padding: 0 8px 1px;
    font-size: 12px;
}

/* Status indicators */
.subscription-health {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.subscription-health-good {
    background-color: #e7f7ea;
    color: #1d9d4f;
}

.subscription-health-warning {
    background-color: #fcf8e3;
    color: #8a6d3b;
}

.subscription-health-atrisk {
    background-color: #f8d7da;
    color: #721c24;
}

.subscription-health-critical {
    background-color: #f5c6cb;
    color: #721c24;
}

.subscription-health-desc {
    font-size: 12px;
    color: #646970;
}

/* Recurring and trial indicators */
.trial-price,
.recurring,
.one-time,
.limited-access {
    font-size: 12px;
    color: #646970;
}

/* NA placeholder */
.na {
    color: #999;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Add any JavaScript functionality needed for the products page
    
    // Example: Add click handler for bulk export
    $('button[name="export"]').on('click', function() {
        $('#products-filter').append('<input type="hidden" name="export" value="csv" />');
        return true;
    });
});
</script>