<?php

namespace Members\Subscriptions\admin;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions;

// Load WP_List_Table if not loaded
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Products List Table
 * 
 * Custom list table for displaying membership products in the admin area
 */
class Products_List_Table extends \WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => __('Product', 'members'),
            'plural'   => __('Products', 'members'),
            'ajax'     => false,
        ]);
    }
    
    /**
     * Get table columns
     *
     * @return array
     */
    public function get_columns() {
        return [
            'cb'               => '<input type="checkbox" />',
            'title'            => __('Title', 'members'),
            'price'            => __('Pricing', 'members'),
            'subscription'     => __('Subscription', 'members'),
            'roles'            => __('Member Roles', 'members'),
            'active_members'   => __('Active Members', 'members'),
            'total_revenue'    => __('Total Revenue', 'members'),
            'date'             => __('Created', 'members'),
            'actions'          => __('Actions', 'members'),
        ];
    }
    
    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns() {
        return [
            'title'           => ['title', false],
            'price'           => ['price', false],
            'active_members'  => ['active_members', false],
            'total_revenue'   => ['total_revenue', false],
            'date'            => ['date', true],
        ];
    }
    
    /**
     * Prepare items for the table
     */
    public function prepare_items() {
        global $wpdb;
        
        // Set column headers
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
        
        // Process bulk actions
        $this->process_bulk_action();
        
        // Set pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        // Query products
        $query_args = [
            'post_type'      => 'members_product',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
        ];
        
        // Add sorting
        if (isset($_REQUEST['orderby'])) {
            $orderby = sanitize_text_field($_REQUEST['orderby']);
            $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
            
            // Handle special ordering cases
            switch ($orderby) {
                case 'price':
                    $query_args['meta_key'] = '_price';
                    $query_args['orderby'] = 'meta_value_num';
                    $query_args['order'] = $order;
                    break;
                    
                case 'active_members':
                case 'total_revenue':
                    // These are calculated fields, not actual post fields
                    // Default to date ordering for these
                    $query_args['orderby'] = 'date';
                    $query_args['order'] = $order;
                    break;
                    
                default:
                    $query_args['orderby'] = $orderby;
                    $query_args['order'] = $order;
                    break;
            }
        } else {
            // Default sorting by date
            $query_args['orderby'] = 'date';
            $query_args['order'] = 'DESC';
        }
        
        // Handle search
        if (!empty($_REQUEST['s'])) {
            $query_args['s'] = sanitize_text_field($_REQUEST['s']);
        }
        
        // Get products
        $query = new \WP_Query($query_args);
        $this->items = $query->posts;
        
        // Set pagination arguments
        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => $query->max_num_pages,
        ]);
    }
    
    /**
     * Checkbox column
     * 
     * @param object $item
     * @return string
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="product[]" value="%s" />',
            $item->ID
        );
    }
    
    /**
     * Title column
     * 
     * @param object $item
     * @return string
     */
    public function column_title($item) {
        $edit_link = get_edit_post_link($item->ID);
        $view_link = get_permalink($item->ID);
        $subscriptions_link = admin_url('admin.php?page=members-subscriptions&product_id=' . $item->ID);
        
        $output = sprintf(
            '<strong><a class="row-title" href="%s">%s</a></strong>',
            esc_url($edit_link),
            esc_html($item->post_title)
        );
        
        // Add row actions
        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($edit_link),
                __('Edit', 'members')
            ),
            'view' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($view_link),
                __('View', 'members')
            ),
            'subscribers' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($subscriptions_link),
                __('Subscribers', 'members')
            ),
        ];
        
        $output .= $this->row_actions($actions);
        
        return $output;
    }
    
    /**
     * Price column
     * 
     * @param object $item
     * @return string
     */
    public function column_price($item) {
        $price = 0.00;
        
        // Try to get price from product meta
        if (function_exists('\\Members\\Subscriptions\\get_product_meta')) {
            $price = Subscriptions\get_product_meta($item->ID, '_price', 0.00);
        } else {
            $price = get_post_meta($item->ID, '_price', true);
            if (empty($price)) {
                $price = 0.00;
            }
        }
        
        // Get currency symbol and formatting
        $currency_symbol = '$'; // Default
        $currency_position = 'before'; // Default
        
        // Check if WordPress Currency settings are available
        if (function_exists('get_woocommerce_currency_symbol')) {
            $currency_symbol = get_woocommerce_currency_symbol();
        }
        
        // Format the price
        if ($currency_position === 'before') {
            $formatted_price = $currency_symbol . number_format_i18n((float)$price, 2);
        } else {
            $formatted_price = number_format_i18n((float)$price, 2) . $currency_symbol;
        }
        
        // Check if there's a trial price
        $has_trial = false;
        $trial_price = 0.00;
        
        if (function_exists('\\Members\\Subscriptions\\get_product_meta')) {
            $has_trial = (bool)Subscriptions\get_product_meta($item->ID, '_has_trial', false);
            $trial_price = (float)Subscriptions\get_product_meta($item->ID, '_trial_price', 0.00);
        } else {
            $has_trial = (bool)get_post_meta($item->ID, '_has_trial', true);
            $trial_price = (float)get_post_meta($item->ID, '_trial_price', true);
        }
        
        // Format the trial price if applicable
        if ($has_trial) {
            if ($currency_position === 'before') {
                $formatted_trial_price = $currency_symbol . number_format_i18n($trial_price, 2);
            } else {
                $formatted_trial_price = number_format_i18n($trial_price, 2) . $currency_symbol;
            }
            
            // Add trial info
            $trial_days = 0;
            if (function_exists('\\Members\\Subscriptions\\get_product_meta')) {
                $trial_days = (int)Subscriptions\get_product_meta($item->ID, '_trial_days', 0);
            } else {
                $trial_days = (int)get_post_meta($item->ID, '_trial_days', true);
            }
            
            if ($trial_days > 0) {
                $output = sprintf(
                    '%s<br><span class="trial-price">%s %s %s %s</span>',
                    $formatted_price,
                    __('Trial:', 'members'),
                    $formatted_trial_price,
                    __('for', 'members'),
                    sprintf(_n('%d day', '%d days', $trial_days, 'members'), $trial_days)
                );
                
                return $output;
            }
        }
        
        return $formatted_price;
    }
    
    /**
     * Subscription column
     * 
     * @param object $item
     * @return string
     */
    public function column_subscription($item) {
        // Try to get subscription details from product meta
        $is_recurring = false;
        $period = 1;
        $period_type = 'month';
        
        if (function_exists('\\Members\\Subscriptions\\get_product_meta')) {
            $is_recurring = (bool)Subscriptions\get_product_meta($item->ID, '_recurring', false);
            $period = (int)Subscriptions\get_product_meta($item->ID, '_period', 1);
            $period_type = Subscriptions\get_product_meta($item->ID, '_period_type', 'month');
        } else {
            $is_recurring = (bool)get_post_meta($item->ID, '_recurring', true);
            $period = (int)get_post_meta($item->ID, '_period', true);
            if (empty($period)) $period = 1;
            $period_type = get_post_meta($item->ID, '_period_type', true);
            if (empty($period_type)) $period_type = 'month';
        }
        
        if ($is_recurring) {
            // Format the period
            $period_labels = [
                'day'   => _n('day', 'days', $period, 'members'),
                'week'  => _n('week', 'weeks', $period, 'members'),
                'month' => _n('month', 'months', $period, 'members'),
                'year'  => _n('year', 'years', $period, 'members'),
            ];
            
            $period_label = isset($period_labels[$period_type]) 
                ? $period_labels[$period_type] 
                : $period_type;
            
            return sprintf(
                '<span class="recurring">%s</span>',
                sprintf(__('Recurring every %d %s', 'members'), $period, $period_label)
            );
        }
        
        // Check if one-time payment has limited access
        $has_access_period = false;
        $access_period = 0;
        $access_period_type = '';
        
        if (function_exists('\\Members\\Subscriptions\\get_product_meta')) {
            $has_access_period = (bool)Subscriptions\get_product_meta($item->ID, '_has_access_period', false);
            $access_period = (int)Subscriptions\get_product_meta($item->ID, '_access_period', 0);
            $access_period_type = Subscriptions\get_product_meta($item->ID, '_access_period_type', '');
        } else {
            $has_access_period = (bool)get_post_meta($item->ID, '_has_access_period', true);
            $access_period = (int)get_post_meta($item->ID, '_access_period', true);
            $access_period_type = get_post_meta($item->ID, '_access_period_type', true);
        }
        
        if ($has_access_period && $access_period > 0) {
            // Format the period
            $period_labels = [
                'day'   => _n('day', 'days', $access_period, 'members'),
                'week'  => _n('week', 'weeks', $access_period, 'members'),
                'month' => _n('month', 'months', $access_period, 'members'),
                'year'  => _n('year', 'years', $access_period, 'members'),
            ];
            
            $period_label = isset($period_labels[$access_period_type]) 
                ? $period_labels[$access_period_type] 
                : $access_period_type;
            
            return sprintf(
                '<span class="limited-access">%s</span>',
                sprintf(__('One-time payment (expires after %d %s)', 'members'), $access_period, $period_label)
            );
        }
        
        return '<span class="one-time">' . __('One-time payment', 'members') . '</span>';
    }
    
    /**
     * Roles column
     * 
     * @param object $item
     * @return string
     */
    public function column_roles($item) {
        $roles = [];
        
        // Try to get roles from product meta
        if (function_exists('\\Members\\Subscriptions\\get_product_meta')) {
            $roles = Subscriptions\get_product_meta($item->ID, '_membership_roles', []);
        } else {
            $roles = get_post_meta($item->ID, '_membership_roles', true);
        }
        
        if (!is_array($roles)) {
            if (is_string($roles) && !empty($roles)) {
                $roles = [$roles]; // Convert to array if it's a single role
            } else {
                $roles = [];
            }
        }
        
        if (empty($roles)) {
            return '<span class="na">—</span>';
        }
        
        // Get role names
        $wp_roles = wp_roles();
        $role_names = [];
        
        foreach ($roles as $role) {
            $role_name = isset($wp_roles->role_names[$role]) ? translate_user_role($wp_roles->role_names[$role]) : $role;
            $role_names[] = $role_name;
        }
        
        return implode(', ', $role_names);
    }
    
    /**
     * Active members column
     * 
     * @param object $item
     * @return string
     */
    public function column_active_members($item) {
        global $wpdb;
        
        // Make sure the subscriptions table exists
        $table_name = Subscriptions\get_subscriptions_table_name();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if (!$table_exists) {
            return '<span class="na">—</span>';
        }
        
        // Count active subscriptions for this product
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE product_id = %d AND status = 'active'",
            $item->ID
        ));
        
        if ($count === null) {
            return '<span class="na">—</span>';
        }
        
        return sprintf(
            '<a href="%s">%d</a>',
            esc_url(admin_url('admin.php?page=members-subscriptions&product_id=' . $item->ID . '&status=active')),
            $count
        );
    }
    
    /**
     * Total revenue column
     * 
     * @param object $item
     * @return string
     */
    public function column_total_revenue($item) {
        global $wpdb;
        
        // Make sure the transactions table exists
        $table_name = Subscriptions\get_transactions_table_name();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if (!$table_exists) {
            return '<span class="na">—</span>';
        }
        
        // Get total revenue from completed transactions for this product
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $table_name WHERE product_id = %d AND status = 'complete'",
            $item->ID
        ));
        
        if (empty($total)) {
            $total = 0;
        }
        
        // Get currency symbol and formatting
        $currency_symbol = '$'; // Default
        $currency_position = 'before'; // Default
        
        // Check if WordPress Currency settings are available
        if (function_exists('get_woocommerce_currency_symbol')) {
            $currency_symbol = get_woocommerce_currency_symbol();
        }
        
        // Format the total revenue
        if ($currency_position === 'before') {
            $formatted_total = $currency_symbol . number_format_i18n((float)$total, 2);
        } else {
            $formatted_total = number_format_i18n((float)$total, 2) . $currency_symbol;
        }
        
        // Count transactions
        $transaction_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE product_id = %d AND status = 'complete'",
            $item->ID
        ));
        
        return sprintf(
            '%s<br><span class="transaction-count">%s</span>',
            $formatted_total,
            sprintf(_n('%d transaction', '%d transactions', $transaction_count, 'members'), $transaction_count)
        );
    }
    
    /**
     * Date column
     * 
     * @param object $item
     * @return string
     */
    public function column_date($item) {
        $post_date = strtotime($item->post_date);
        
        $date = date_i18n(get_option('date_format'), $post_date);
        $time = date_i18n(get_option('time_format'), $post_date);
        
        return sprintf(
            '<time datetime="%s">%s</time><br><span class="time">%s</span>',
            esc_attr(date('c', $post_date)),
            esc_html($date),
            esc_html($time)
        );
    }
    
    /**
     * Actions column
     * 
     * @param object $item
     * @return string
     */
    public function column_actions($item) {
        $edit_url = get_edit_post_link($item->ID);
        $view_url = get_permalink($item->ID);
        $subscriptions_url = admin_url('admin.php?page=members-subscriptions&product_id=' . $item->ID);
        $transactions_url = admin_url('admin.php?page=members-transactions&product_id=' . $item->ID);
        
        $actions = '<div class="product-actions">';
        
        // Edit action - this is primary now that we only have one menu
        $actions .= sprintf(
            '<a href="%s" class="button button-small button-primary">%s</a>',
            esc_url($edit_url),
            __('Edit', 'members')
        );
        
        // View action
        $actions .= sprintf(
            '<a href="%s" class="button button-small">%s</a>',
            esc_url($view_url),
            __('View', 'members')
        );
        
        // View Subscriptions action
        $actions .= sprintf(
            '<a href="%s" class="button button-small">%s</a>',
            esc_url($subscriptions_url),
            __('Subscriptions', 'members')
        );
        
        // View Transactions action
        $actions .= sprintf(
            '<a href="%s" class="button button-small">%s</a>',
            esc_url($transactions_url),
            __('Transactions', 'members')
        );
        
        $actions .= '</div>';
        
        return $actions;
    }
    
    /**
     * Get bulk actions
     *
     * @return array
     */
    public function get_bulk_actions() {
        return [
            'activate'   => __('Activate', 'members'),
            'deactivate' => __('Deactivate', 'members'),
            'delete'     => __('Delete', 'members'),
        ];
    }
    
    /**
     * Process bulk actions
     */
    public function process_bulk_action() {
        // Security check
        if (isset($_REQUEST['_wpnonce']) && !empty($_REQUEST['_wpnonce'])) {
            $nonce = filter_input(INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING);
            $action = $this->current_action();
            
            if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
                wp_die(__('Security check failed.', 'members'));
            }
            
            $product_ids = isset($_REQUEST['product']) ? $_REQUEST['product'] : [];
            
            if (!is_array($product_ids)) {
                $product_ids = [$product_ids];
            }
            
            $product_ids = array_map('absint', $product_ids);
            
            // Process actions
            switch ($action) {
                case 'activate':
                    foreach ($product_ids as $product_id) {
                        wp_update_post([
                            'ID' => $product_id,
                            'post_status' => 'publish',
                        ]);
                    }
                    break;
                    
                case 'deactivate':
                    foreach ($product_ids as $product_id) {
                        wp_update_post([
                            'ID' => $product_id,
                            'post_status' => 'draft',
                        ]);
                    }
                    break;
                    
                case 'delete':
                    foreach ($product_ids as $product_id) {
                        wp_trash_post($product_id);
                    }
                    break;
            }
        }
    }
    
    /**
     * No items found message
     */
    public function no_items() {
        _e('No membership products found.', 'members');
    }
}