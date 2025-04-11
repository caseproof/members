<?php

namespace Members\Subscriptions\admin;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Transactions List Table
 */
class Transactions_List_Table extends \WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'transaction',
            'plural'   => 'transactions',
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
            'trans_num'    => __('Transaction ID', 'members'),
            'gateway'      => __('Payment Method', 'members'),
            'amount'       => __('Amount', 'members'),
            'status'       => __('Status', 'members'),
            'created_at'   => __('Date', 'members'),
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
            'trans_num'  => ['trans_num', false],
            'gateway'    => ['gateway', false],
            'amount'     => ['amount', false],
            'status'     => ['status', false],
            'created_at' => ['created_at', false],
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
        $gateway = isset($_REQUEST['gateway']) ? sanitize_text_field($_REQUEST['gateway']) : '';
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $date_from = isset($_REQUEST['date_from']) ? sanitize_text_field($_REQUEST['date_from']) : '';
        $date_to = isset($_REQUEST['date_to']) ? sanitize_text_field($_REQUEST['date_to']) : '';
        
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
        
        // Add gateway filter
        if (!empty($gateway)) {
            $args['gateway'] = $gateway;
        }
        
        // Get transactions with filters
        global $wpdb;
        $table = Subscriptions\get_transactions_table_name();
        
        // Start building WHERE clause
        $where_clauses = [];
        $where_values = [];
        
        // Add status filter
        if (!empty($status)) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $status;
        }
        
        // Add gateway filter
        if (!empty($gateway)) {
            $where_clauses[] = 'gateway = %s';
            $where_values[] = $gateway;
        }
        
        // Add date range filter
        if (!empty($date_from)) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $date_from . ' 00:00:00';
        }
        
        if (!empty($date_to)) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $date_to . ' 23:59:59';
        }
        
        // Search functionality
        if (!empty($search)) {
            // Get user IDs matching search
            $user_query = $wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} 
                WHERE user_login LIKE %s 
                OR user_email LIKE %s 
                OR display_name LIKE %s",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
            
            $user_ids = $wpdb->get_col($user_query);
            
            // Get product IDs matching search
            $product_query = $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'members_product' 
                AND post_title LIKE %s",
                '%' . $wpdb->esc_like($search) . '%'
            );
            
            $product_ids = $wpdb->get_col($product_query);
            
            // Search by transaction number
            $trans_num_condition = $wpdb->prepare(
                "trans_num LIKE %s",
                '%' . $wpdb->esc_like($search) . '%'
            );
            
            // Build search conditions
            $search_conditions = [];
            
            if (!empty($user_ids)) {
                $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
                $search_conditions[] = "user_id IN ($placeholders)";
                $where_values = array_merge($where_values, $user_ids);
            }
            
            if (!empty($product_ids)) {
                $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
                $search_conditions[] = "product_id IN ($placeholders)";
                $where_values = array_merge($where_values, $product_ids);
            }
            
            $search_conditions[] = $trans_num_condition;
            
            // If any search condition found, add to where clause
            if (!empty($search_conditions)) {
                $where_clauses[] = '(' . implode(' OR ', $search_conditions) . ')';
            }
        }
        
        // Build the complete WHERE clause
        $where = '';
        if (!empty($where_clauses)) {
            $where = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Build ORDER BY clause
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'id DESC';
        
        // Build LIMIT clause
        $limit = $wpdb->prepare("LIMIT %d, %d", $args['offset'], $args['limit']);
        
        // Execute count query
        $count_query = "SELECT COUNT(*) FROM $table $where";
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        
        $total_items = $wpdb->get_var($count_query);
        
        // Execute main query
        $main_query = "SELECT * FROM $table $where ORDER BY $orderby $limit";
        if (!empty($where_values)) {
            $main_query = $wpdb->prepare($main_query, $where_values);
        }
        
        $transactions = $wpdb->get_results($main_query);
        
        // Set pagination args
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
        
        // Set items
        $this->items = $transactions;
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
            
            case 'trans_num':
                return $item->trans_num;
                
            case 'gateway':
                // Format gateway name
                $gateways = [
                    'stripe' => __('Stripe', 'members'),
                    'manual' => __('Manual', 'members'),
                ];
                
                return isset($gateways[$item->gateway]) ? $gateways[$item->gateway] : $item->gateway;
                
            case 'created_at':
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at));
                
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
        
        $output = sprintf(
            '<a href="%s">%s</a>',
            esc_url($edit_link),
            esc_html($product->post_title)
        );
        
        // Add subscription info if applicable
        if (!empty($item->subscription_id)) {
            $subscription = Subscriptions\get_subscription($item->subscription_id);
            
            if ($subscription) {
                $subscription_link = admin_url('admin.php?page=members-subscriptions&action=view&subscription=' . $subscription->id);
                
                $output .= sprintf(
                    '<br><span class="subscription-info"><small>%s: <a href="%s">#%d</a></small></span>',
                    __('Subscription', 'members'),
                    esc_url($subscription_link),
                    $subscription->id
                );
            }
        }
        
        return $output;
    }

    /**
     * Column amount
     *
     * @param object $item
     * @return string
     */
    public function column_amount($item) {
        // Show amount and total if tax included
        $amount = floatval($item->amount);
        $total = floatval($item->total);
        
        $output = number_format_i18n($total, 2);
        
        // Add tax details if there is tax
        if (!empty($item->tax_amount) && $item->tax_amount > 0) {
            $tax_amount = floatval($item->tax_amount);
            $tax_rate = floatval($item->tax_rate);
            
            $output .= sprintf(
                '<br><span class="tax-details"><small>%s %s%%: %s</small></span>',
                __('Tax', 'members'),
                number_format_i18n($tax_rate, 2),
                number_format_i18n($tax_amount, 2)
            );
        }
        
        return $output;
    }

    /**
     * Column status
     *
     * @param object $item
     * @return string
     */
    public function column_status($item) {
        $status_labels = [
            'pending'   => __('Pending', 'members'),
            'completed' => __('Completed', 'members'),
            'failed'    => __('Failed', 'members'),
            'refunded'  => __('Refunded', 'members'),
            'cancelled' => __('Cancelled', 'members'),
        ];
        
        // Map 'complete' to 'completed' for CSS class consistency
        $css_status = ($item->status === 'complete') ? 'completed' : $item->status;
        $status = isset($status_labels[$item->status]) ? $status_labels[$item->status] : $item->status;
        
        return sprintf(
            '<span class="members-txn-status members-txn-status-%s">%s</span>',
            esc_attr($css_status),
            esc_html($status)
        );
    }

    /**
     * Column actions
     *
     * @param object $item
     * @return string
     */
    public function column_actions($item) {
        $base_url = admin_url('admin.php?page=members-transactions');
        
        $actions_html = '<div class="members-sub-actions">';
        
        // View action
        $actions_html .= sprintf(
            '<a href="%s" class="members-sub-action view">%s</a>',
            esc_url(add_query_arg(['action' => 'view', 'transaction' => $item->id], $base_url)),
            __('View', 'members')
        );
        
        // Status-specific actions
        switch ($item->status) {
            case 'pending':
                $actions_html .= sprintf(
                    '<a href="%s" class="members-sub-action complete">%s</a>',
                    esc_url(add_query_arg(['action' => 'complete', 'transaction' => $item->id, '_wpnonce' => wp_create_nonce('members_complete_transaction')], $base_url)),
                    __('Mark Complete', 'members')
                );
                break;
                
            case 'complete':
                // Only show refund for gateways that support it
                if ($item->gateway === 'stripe') {
                    $actions_html .= sprintf(
                        '<a href="%s" class="members-sub-action refund" onclick="return confirm(\'%s\')">%s</a>',
                        esc_url(add_query_arg(['action' => 'refund', 'transaction' => $item->id, '_wpnonce' => wp_create_nonce('members_refund_transaction')], $base_url)),
                        esc_attr__('Are you sure you want to refund this transaction?', 'members'),
                        __('Refund', 'members')
                    );
                }
                break;
        }
        
        // Delete action
        $actions_html .= sprintf(
            '<a href="%s" class="members-sub-action delete" onclick="return confirm(\'%s\')">%s</a>',
            esc_url(add_query_arg(['action' => 'delete', 'transaction' => $item->id, '_wpnonce' => wp_create_nonce('members_delete_transaction')], $base_url)),
            esc_attr__('Are you sure you want to delete this transaction? This action cannot be undone.', 'members'),
            __('Delete', 'members')
        );
        
        $actions_html .= '</div>';
        
        return $actions_html;
    }

    /**
     * Column cb
     *
     * @param object $item
     * @return string
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="transaction[]" value="%s" />',
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
            'complete' => __('Mark Complete', 'members'),
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
        if (isset($_GET['transaction']) && isset($_GET['_wpnonce'])) {
            $transaction_id = intval($_GET['transaction']);
            
            switch ($action) {
                case 'complete':
                    // Verify nonce
                    if (wp_verify_nonce($_GET['_wpnonce'], 'members_complete_transaction')) {
                        $this->complete_transaction($transaction_id);
                    }
                    break;
                    
                case 'refund':
                    // Verify nonce
                    if (wp_verify_nonce($_GET['_wpnonce'], 'members_refund_transaction')) {
                        $this->refund_transaction($transaction_id);
                    }
                    break;
                    
                case 'delete':
                    // Verify nonce
                    if (wp_verify_nonce($_GET['_wpnonce'], 'members_delete_transaction')) {
                        $this->delete_transaction($transaction_id);
                    }
                    break;
            }
            
            // Redirect to remove action from URL
            wp_redirect(admin_url('admin.php?page=members-transactions'));
            exit;
        }
        
        // For bulk actions
        if (isset($_POST['transaction']) && is_array($_POST['transaction'])) {
            // Security check
            check_admin_referer('bulk-' . $this->_args['plural']);
            
            $transaction_ids = array_map('intval', $_POST['transaction']);
            
            switch ($action) {
                case 'complete':
                    foreach ($transaction_ids as $id) {
                        $this->complete_transaction($id);
                    }
                    break;
                    
                case 'delete':
                    foreach ($transaction_ids as $id) {
                        $this->delete_transaction($id);
                    }
                    break;
            }
            
            // Redirect to remove action from URL
            wp_redirect(admin_url('admin.php?page=members-transactions'));
            exit;
        }
    }

    /**
     * Mark transaction as complete
     *
     * @param int $transaction_id
     * @return void
     */
    private function complete_transaction($transaction_id) {
        global $wpdb;
        $table = Subscriptions\get_transactions_table_name();
        
        // Get transaction
        $transaction = Subscriptions\get_transaction($transaction_id);
        
        if ($transaction && $transaction->status === 'pending') {
            $old_status = $transaction->status;
            
            // Update status
            $result = $wpdb->update(
                $table,
                ['status' => 'complete'],
                ['id' => $transaction_id],
                ['%s'],
                ['%d']
            );
            
            if ($result) {
                // Log status change
                Subscriptions\log_event('transaction_completed', [
                    'transaction_id' => $transaction_id,
                    'old_status' => $old_status,
                    'new_status' => 'complete',
                    'user_id' => $transaction->user_id,
                ]);
                
                // If this is a subscription transaction, activate the subscription
                if (!empty($transaction->subscription_id)) {
                    $subscription = Subscriptions\get_subscription($transaction->subscription_id);
                    
                    if ($subscription && $subscription->status === 'pending') {
                        $wpdb->update(
                            Subscriptions\get_subscriptions_table_name(),
                            ['status' => 'active'],
                            ['id' => $subscription->id],
                            ['%s'],
                            ['%d']
                        );
                        
                        // Trigger status change action
                        do_action('members_subscription_status_change', $subscription->id, 'pending', 'active');
                    }
                }
                
                // Send email notification
                $this->send_transaction_complete_notification($transaction_id);
                
                // Redirect with success message
                wp_redirect(add_query_arg('message', 'completed', admin_url('admin.php?page=members-transactions')));
                exit;
            }
        }
        
        // Redirect with error message
        wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=members-transactions')));
        exit;
    }

    /**
     * Refund transaction
     *
     * @param int $transaction_id
     * @return void
     */
    private function refund_transaction($transaction_id) {
        // Get transaction
        $transaction = Subscriptions\get_transaction($transaction_id);
        
        if (!$transaction || $transaction->status !== 'complete') {
            wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=members-transactions')));
            exit;
        }
        
        // Get gateway
        $gateway_manager = Subscriptions\gateways\Gateway_Manager::get_instance();
        $gateway = $gateway_manager->get_gateway($transaction->gateway);
        
        // Process refund if gateway supports it
        if ($gateway && method_exists($gateway, 'process_refund')) {
            $result = $gateway->process_refund($transaction_id);
            
            if ($result) {
                // Redirect with success message
                wp_redirect(add_query_arg('message', 'refunded', admin_url('admin.php?page=members-transactions')));
                exit;
            }
        }
        
        // If gateway doesn't support refunds or refund failed, manually mark as refunded
        global $wpdb;
        $table = Subscriptions\get_transactions_table_name();
        
        $result = $wpdb->update(
            $table,
            ['status' => 'refunded'],
            ['id' => $transaction_id],
            ['%s'],
            ['%d']
        );
        
        if ($result) {
            // Log refund
            Subscriptions\log_event('transaction_refunded', [
                'transaction_id' => $transaction_id,
                'user_id' => $transaction->user_id,
                'amount' => $transaction->total,
            ]);
            
            // Send refund notification
            $this->send_transaction_refund_notification($transaction_id);
            
            // Redirect with success message
            wp_redirect(add_query_arg('message', 'refunded', admin_url('admin.php?page=members-transactions')));
            exit;
        }
        
        // Redirect with error message
        wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=members-transactions')));
        exit;
    }

    /**
     * Delete transaction
     *
     * @param int $transaction_id
     * @return void
     */
    private function delete_transaction($transaction_id) {
        global $wpdb;
        $table = Subscriptions\get_transactions_table_name();
        
        // Get transaction
        $transaction = Subscriptions\get_transaction($transaction_id);
        
        if ($transaction) {
            // Delete transaction
            $result = $wpdb->delete(
                $table,
                ['id' => $transaction_id],
                ['%d']
            );
            
            if ($result) {
                // Log deletion
                Subscriptions\log_event('transaction_deleted', [
                    'transaction_id' => $transaction_id,
                    'user_id' => $transaction->user_id,
                    'product_id' => $transaction->product_id,
                ]);
                
                // Redirect with success message
                wp_redirect(add_query_arg('message', 'deleted', admin_url('admin.php?page=members-transactions')));
                exit;
            }
        }
        
        // Redirect with error message
        wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=members-transactions')));
        exit;
    }

    /**
     * Send transaction complete notification
     *
     * @param int $transaction_id
     * @return void
     */
    private function send_transaction_complete_notification($transaction_id) {
        // This would be implemented in the email notifications system
        do_action('members_transaction_completed_notification', $transaction_id);
    }

    /**
     * Send transaction refund notification
     *
     * @param int $transaction_id
     * @return void
     */
    private function send_transaction_refund_notification($transaction_id) {
        // This would be implemented in the email notifications system
        do_action('members_transaction_refunded_notification', $transaction_id);
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
            </div>
            <?php
        }
    }

    /**
     * No items
     *
     * @return void
     */
    public function no_items() {
        _e('No transactions found.', 'members');
    }
}