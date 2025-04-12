<?php

namespace Members\Subscriptions\admin;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Subscriptions List Table
 */
class Subscriptions_List_Table extends \WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'subscription',
            'plural'   => 'subscriptions',
            'ajax'     => false,
        ]);
    }

    /**
     * Get columns
     *
     * @return array
     */
    public function get_columns() {
        return [
            'cb'           => '<input type="checkbox" />',
            'id'           => __('ID', 'members'),
            'user'         => __('User', 'members'),
            'product'      => __('Membership', 'members'),
            'gateway'      => __('Payment Method', 'members'),
            'amount'       => __('Amount', 'members'),
            'status'       => __('Status', 'members'),
            'created_at'   => __('Start Date', 'members'),
            'expires_at'   => __('Expiry Date', 'members'),
            'renewal_count' => __('Renewals', 'members'),
            'actions'      => __('Actions', 'members'),
        ];
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns() {
        return [
            'id'         => ['id', true],
            'user'       => ['user_id', false],
            'product'    => ['product_id', false],
            'gateway'    => ['gateway', false],
            'amount'     => ['price', false],
            'status'     => ['status', false],
            'created_at' => ['created_at', false],
            'expires_at' => ['expires_at', false],
        ];
    }

    /**
     * Prepare items
     */
    public function prepare_items() {
        // Get columns
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        
        // Setup column headers
        $this->_column_headers = [$columns, $hidden, $sortable];
        
        // Process bulk actions
        $this->process_bulk_action();
        
        // Get pagination state
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Get filter parameters
        $status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        
        // Setup query args
        $args = [
            'orderby' => isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'id',
            'order'   => isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC',
            'limit'   => $per_page,
            'offset'  => $offset,
        ];
        
        // Add status filter
        if (!empty($status)) {
            $args['status'] = $status;
        }
        
        // Get subscriptions
        if (!empty($search)) {
            global $wpdb;
            $table = Subscriptions\get_subscriptions_table_name();
            
            $search_query = '%' . $wpdb->esc_like($search) . '%';
            
            // First query - get user IDs that match search
            $user_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT ID FROM {$wpdb->users}
                WHERE user_login LIKE %s
                OR user_email LIKE %s
                OR display_name LIKE %s",
                $search_query, $search_query, $search_query
            ));
            
            // Second query - get product IDs that match search
            $product_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT ID FROM {$wpdb->posts}
                WHERE post_type = 'members_product'
                AND post_title LIKE %s",
                $search_query
            ));
            
            // No matches for users or products
            if (empty($user_ids) && empty($product_ids)) {
                $total_items = 0;
                $subscriptions = [];
            } else {
                // Build WHERE clause for filtering
                $where = [];
                
                if (!empty($user_ids)) {
                    $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
                    $where[] = $wpdb->prepare("user_id IN ($placeholders)", $user_ids);
                }
                
                if (!empty($product_ids)) {
                    $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
                    $where[] = $wpdb->prepare("product_id IN ($placeholders)", $product_ids);
                }
                
                // Add status filter if set
                if (!empty($status)) {
                    $where[] = $wpdb->prepare("status = %s", $status);
                }
                
                $where_clause = 'WHERE ' . implode(' OR ', $where);
                
                // Count query
                $count_query = "SELECT COUNT(*) FROM $table $where_clause";
                $total_items = $wpdb->get_var($count_query);
                
                // Main query
                $order = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
                $limit = $wpdb->prepare("LIMIT %d, %d", $args['offset'], $args['limit']);
                
                $query = "SELECT * FROM $table $where_clause ORDER BY $order $limit";
                $subscriptions = $wpdb->get_results($query);
            }
        } else {
            // Regular query without search
            $total_items = Subscriptions\count_subscriptions($args);
            $subscriptions = Subscriptions\get_subscriptions($args);
        }
        
        // Set pagination args
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
        
        // Set items
        $this->items = $subscriptions;
    }

    /**
     * Column default
     *
     * @param object $item
     * @param string $column_name
     * @return string
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item->id;
                
            case 'gateway':
                // Format gateway name
                $gateways = [
                    'stripe' => __('Stripe', 'members'),
                    'manual' => __('Manual', 'members'),
                ];
                
                return isset($gateways[$item->gateway]) ? $gateways[$item->gateway] : $item->gateway;
                
            case 'created_at':
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at));
                
            case 'expires_at':
                if (empty($item->expires_at) || $item->expires_at === '0000-00-00 00:00:00') {
                    return __('Never (lifetime)', 'members');
                }
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->expires_at));
                
            case 'renewal_count':
                return $item->renewal_count;
                
            default:
                return print_r($item, true);
        }
    }

    /**
     * Column user
     *
     * @param object $item
     * @return string
     */
    public function column_user($item) {
        $user = get_userdata($item->user_id);
        
        if (!$user) {
            return sprintf('<em>%s</em>', __('User deleted', 'members'));
        }
        
        $edit_link = get_edit_user_link($user->ID);
        $avatar = get_avatar($user->ID, 32, '', '', ['class' => 'members-user-avatar']);
        
        $output = sprintf(
            '<a href="%s">%s %s</a><br>
            <span class="members-user-email">%s</span>',
            esc_url($edit_link),
            $avatar,
            esc_html($user->display_name),
            esc_html($user->user_email)
        );
        
        return $output;
    }

    /**
     * Column product
     *
     * @param object $item
     * @return string
     */
    public function column_product($item) {
        $product = get_post($item->product_id);
        
        if (!$product) {
            return sprintf('<em>%s</em>', __('Product deleted', 'members'));
        }
        
        $edit_link = get_edit_post_link($product->ID);
        
        return sprintf(
            '<a href="%s">%s</a>',
            esc_url($edit_link),
            esc_html($product->post_title)
        );
    }

    /**
     * Column amount
     *
     * @param object $item
     * @return string
     */
    public function column_amount($item) {
        $amount = floatval($item->price);
        $formatted = number_format_i18n($amount, 2);
        
        // Add subscription details
        $details = '';
        
        // If it's a recurring subscription, show period
        if (!empty($item->period)) {
            $period_type = $item->period_type;
            $period = $item->period;
            
            $details = sprintf(
                '<span class="members-recurring-details">%s</span>',
                Subscriptions\format_subscription_period($period, $period_type)
            );
        }
        
        return $formatted . '<br>' . $details;
    }

    /**
     * Column status
     *
     * @param object $item
     * @return string
     */
    public function column_status($item) {
        $status = Subscriptions\get_formatted_subscription_status($item->status);
        
        return sprintf(
            '<span class="members-sub-status members-sub-status-%s">%s</span>',
            esc_attr($item->status),
            esc_html($status)
        );
    }

    /**
     * Column cb
     *
     * @param object $item
     * @return string
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="subscription[]" value="%s" />',
            $item->id
        );
    }

    /**
     * Get bulk actions
     *
     * @return array
     */
    public function get_bulk_actions() {
        return [
            'activate' => __('Activate', 'members'),
            'cancel'   => __('Cancel', 'members'),
            'delete'   => __('Delete', 'members'),
        ];
    }

    /**
     * Process bulk action
     */
    public function process_bulk_action() {
        // Check if a bulk action is being triggered
        $action = $this->current_action();
        
        if (empty($action)) {
            return;
        }
        
        // For single actions
        if (isset($_GET['subscription']) && isset($_GET['_wpnonce'])) {
            $subscription_id = intval($_GET['subscription']);
            
            switch ($action) {
                case 'activate':
                    // Verify nonce
                    if (wp_verify_nonce($_GET['_wpnonce'], 'members_activate_subscription')) {
                        $this->activate_subscription($subscription_id);
                    }
                    break;
                    
                case 'cancel':
                    // Verify nonce
                    if (wp_verify_nonce($_GET['_wpnonce'], 'members_cancel_subscription')) {
                        $this->cancel_subscription($subscription_id);
                    }
                    break;
                    
                case 'delete':
                    // Verify nonce
                    if (wp_verify_nonce($_GET['_wpnonce'], 'members_delete_subscription')) {
                        $this->delete_subscription($subscription_id);
                    }
                    break;
            }
            
            // Redirect to remove action from URL
            wp_redirect(admin_url('admin.php?page=members-subscriptions'));
            exit;
        }
        
        // For bulk actions
        if (isset($_POST['subscription']) && is_array($_POST['subscription'])) {
            // Security check
            check_admin_referer('bulk-' . $this->_args['plural']);
            
            $subscription_ids = array_map('intval', $_POST['subscription']);
            
            switch ($action) {
                case 'activate':
                    foreach ($subscription_ids as $id) {
                        $this->activate_subscription($id);
                    }
                    break;
                    
                case 'cancel':
                    foreach ($subscription_ids as $id) {
                        $this->cancel_subscription($id);
                    }
                    break;
                    
                case 'delete':
                    foreach ($subscription_ids as $id) {
                        $this->delete_subscription($id);
                    }
                    break;
            }
            
            // Redirect to remove action from URL
            wp_redirect(admin_url('admin.php?page=members-subscriptions'));
            exit;
        }
    }

    /**
     * Activate subscription
     *
     * @param int $subscription_id
     * @return void
     */
    private function activate_subscription($subscription_id) {
        global $wpdb;
        $table = Subscriptions\get_subscriptions_table_name();
        
        // Get subscription
        $subscription = Subscriptions\get_subscription($subscription_id);
        
        if ($subscription) {
            $old_status = $subscription->status;
            
            // Update status
            $result = $wpdb->update(
                $table,
                ['status' => 'active'],
                ['id' => $subscription_id],
                ['%s'],
                ['%d']
            );
            
            if ($result) {
                // Log status change
                Subscriptions\log_event('subscription_activated', [
                    'subscription_id' => $subscription_id,
                    'old_status' => $old_status,
                    'new_status' => 'active',
                    'user_id' => $subscription->user_id,
                ]);
                
                // Trigger status change action
                do_action('members_subscription_status_change', $subscription_id, $old_status, 'active');
                
                // Update user role if needed
                Subscriptions\apply_membership_role($subscription->user_id, $subscription->product_id);
                
                // Redirect with success message
                wp_redirect(add_query_arg('message', 'activated', admin_url('admin.php?page=members-subscriptions')));
                exit;
            }
        }
        
        // Redirect with error message
        wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=members-subscriptions')));
        exit;
    }

    /**
     * Cancel subscription
     *
     * @param int $subscription_id
     * @return void
     */
    private function cancel_subscription($subscription_id) {
        // Use the existing cancel_subscription function
        $result = Subscriptions\cancel_subscription($subscription_id);
        
        if ($result) {
            // Redirect with success message
            wp_redirect(add_query_arg('message', 'cancelled', admin_url('admin.php?page=members-subscriptions')));
            exit;
        }
        
        // Redirect with error message
        wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=members-subscriptions')));
        exit;
    }

    /**
     * Delete subscription
     *
     * @param int $subscription_id
     * @return void
     */
    private function delete_subscription($subscription_id) {
        global $wpdb;
        $table = Subscriptions\get_subscriptions_table_name();
        
        // Get subscription
        $subscription = Subscriptions\get_subscription($subscription_id);
        
        if ($subscription) {
            // Delete subscription
            $result = $wpdb->delete(
                $table,
                ['id' => $subscription_id],
                ['%d']
            );
            
            if ($result) {
                // Log deletion
                Subscriptions\log_event('subscription_deleted', [
                    'subscription_id' => $subscription_id,
                    'user_id' => $subscription->user_id,
                    'product_id' => $subscription->product_id,
                ]);
                
                // Redirect with success message
                wp_redirect(add_query_arg('message', 'deleted', admin_url('admin.php?page=members-subscriptions')));
                exit;
            }
        }
        
        // Redirect with error message
        wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=members-subscriptions')));
        exit;
    }

    /**
     * Extra tablenav
     *
     * @param string $which
     * @return void
     */
    protected function extra_tablenav($which) {
        if ('top' !== $which) {
            return;
        }
        
        // Product filter
        $products = get_posts([
            'post_type' => 'members_product',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        
        if (!empty($products)) {
            $current_product = isset($_REQUEST['product_id']) ? intval($_REQUEST['product_id']) : 0;
            ?>
            <div class="alignleft actions">
                <label for="filter-by-product" class="screen-reader-text"><?php _e('Filter by product', 'members'); ?></label>
                <select name="product_id" id="filter-by-product">
                    <option value=""><?php _e('All products', 'members'); ?></option>
                    <?php foreach ($products as $product) : ?>
                        <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($current_product, $product->ID); ?>>
                            <?php echo esc_html($product->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <?php if (isset($_REQUEST['gateway'])) : ?>
                <input type="hidden" name="gateway" value="<?php echo esc_attr($_REQUEST['gateway']); ?>">
                <?php endif; ?>
                
                <?php if (isset($_REQUEST['date_from'])) : ?>
                <input type="hidden" name="date_from" value="<?php echo esc_attr($_REQUEST['date_from']); ?>">
                <?php endif; ?>
                
                <?php if (isset($_REQUEST['date_to'])) : ?>
                <input type="hidden" name="date_to" value="<?php echo esc_attr($_REQUEST['date_to']); ?>">
                <?php endif; ?>
                
                <?php if (isset($_REQUEST['expiring']) && $_REQUEST['expiring'] === 'soon') : ?>
                <input type="hidden" name="expiring" value="soon">
                <?php endif; ?>
                
                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'members'); ?>">
            </div>
            <?php
        }
        
        // Add export button
        ?>
        <div class="alignright">
            <button type="submit" name="export" value="csv" class="button"><?php _e('Export CSV', 'members'); ?></button>
        </div>
        <?php
    }
    
    /**
     * Column actions
     *
     * @param object $item
     * @return string
     */
    public function column_actions($item) {
        $base_url = admin_url('admin.php?page=members-subscriptions');
        
        $actions_html = '<div class="members-sub-actions">';
        
        // View action
        $actions_html .= sprintf(
            '<a href="%s" class="members-sub-action view">%s</a>',
            esc_url(add_query_arg(['action' => 'view', 'subscription' => $item->id], $base_url)),
            __('View', 'members')
        );
        
        // Edit action
        $actions_html .= sprintf(
            '<a href="%s" class="members-sub-action edit">%s</a>',
            esc_url(add_query_arg(['action' => 'edit', 'subscription' => $item->id], $base_url)),
            __('Edit', 'members')
        );
        
        // Status-specific actions
        switch ($item->status) {
            case 'pending':
                $actions_html .= sprintf(
                    '<a href="%s" class="members-sub-action activate">%s</a>',
                    esc_url(add_query_arg(['action' => 'activate', 'subscription' => $item->id, '_wpnonce' => wp_create_nonce('members_activate_subscription')], $base_url)),
                    __('Activate', 'members')
                );
                break;
                
            case 'active':
                // Get dates for renew button to be displayed
                $show_renew_button = false;
                if (!empty($item->expires_at) && $item->expires_at !== '0000-00-00 00:00:00') {
                    $expires_timestamp = strtotime($item->expires_at);
                    $now_timestamp = current_time('timestamp');
                    
                    // Show renew button if subscription expires within the next 30 days
                    if ($expires_timestamp - $now_timestamp < 30 * DAY_IN_SECONDS) {
                        $show_renew_button = true;
                    }
                }
                
                if ($show_renew_button) {
                    $actions_html .= sprintf(
                        '<a href="%s" class="members-sub-action renew">%s</a>',
                        esc_url(add_query_arg(['action' => 'renew', 'subscription' => $item->id, '_wpnonce' => wp_create_nonce('members_renew_subscription')], $base_url)),
                        __('Renew', 'members')
                    );
                }
                
                $actions_html .= sprintf(
                    '<a href="%s" class="members-sub-action cancel" onclick="return confirm(\'%s\')">%s</a>',
                    esc_url(add_query_arg(['action' => 'cancel', 'subscription' => $item->id, '_wpnonce' => wp_create_nonce('members_cancel_subscription')], $base_url)),
                    esc_attr__('Are you sure you want to cancel this subscription? This action cannot be undone.', 'members'),
                    __('Cancel', 'members')
                );
                break;
                
            case 'cancelled':
            case 'expired':
                $actions_html .= sprintf(
                    '<a href="%s" class="members-sub-action reactivate">%s</a>',
                    esc_url(add_query_arg(['action' => 'reactivate', 'subscription' => $item->id, '_wpnonce' => wp_create_nonce('members_reactivate_subscription')], $base_url)),
                    __('Reactivate', 'members')
                );
                break;
        }
        
        // Delete action
        $actions_html .= sprintf(
            '<a href="%s" class="members-sub-action delete" onclick="return confirm(\'%s\')">%s</a>',
            esc_url(add_query_arg(['action' => 'delete', 'subscription' => $item->id, '_wpnonce' => wp_create_nonce('members_delete_subscription')], $base_url)),
            esc_attr__('Are you sure you want to delete this subscription? This action cannot be undone.', 'members'),
            __('Delete', 'members')
        );
        
        $actions_html .= '</div>';
        
        return $actions_html;
    }

    /**
     * No items
     *
     * @return void
     */
    public function no_items() {
        _e('No subscriptions found.', 'members');
    }
}