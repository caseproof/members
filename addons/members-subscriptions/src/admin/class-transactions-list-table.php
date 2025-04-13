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
 * Transactions List Table
 * 
 * Displays transactions in the admin area
 */
class Transactions_List_Table extends \WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => __('Transaction', 'members'),
            'plural'   => __('Transactions', 'members'),
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
            'id'               => __('ID', 'members'),
            'user'             => __('User', 'members'),
            'product'          => __('Product', 'members'),
            'subscription_id'  => __('Subscription', 'members'),
            'amount'           => __('Amount', 'members'),
            'status'           => __('Status', 'members'),
            'type'             => __('Type', 'members'),
            'gateway'          => __('Gateway', 'members'),
            'notes'            => __('Notes', 'members'),
            'date'             => __('Date', 'members'),
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
            'id'              => ['id', false],
            'user'            => ['user_id', false],
            'product'         => ['product_id', false],
            'subscription_id' => ['subscription_id', false],
            'amount'          => ['amount', false],
            'status'          => ['status', false],
            'type'            => ['txn_type', false],
            'gateway'         => ['gateway', false],
            'date'            => ['created_at', true],
        ];
    }
    
    /**
     * Default column handler
     *
     * @param object $item
     * @param string $column_name
     * @return string
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return '#' . $item->id;
            
            case 'user':
                $user = get_userdata($item->user_id);
                if ($user) {
                    return sprintf(
                        '<a href="%s">%s</a>',
                        esc_url(get_edit_user_link($user->ID)),
                        esc_html($user->display_name)
                    );
                }
                return __('Unknown User', 'members');
            
            case 'product':
                $product = get_post($item->product_id);
                if ($product) {
                    return sprintf(
                        '<a href="%s">%s</a>',
                        esc_url(get_edit_post_link($product->ID)),
                        esc_html($product->post_title)
                    );
                }
                return __('Unknown Product', 'members');
            
            case 'subscription_id':
                // If no subscription ID, show 'N/A'
                if (empty($item->subscription_id)) {
                    return '<span class="na">—</span>';
                }
                
                // Get subscription data if available
                if (function_exists('\\Members\\Subscriptions\\get_subscription')) {
                    $subscription = Subscriptions\get_subscription($item->subscription_id);
                    
                    if ($subscription) {
                        return sprintf(
                            '<a href="%s">%s</a>',
                            esc_url(admin_url('admin.php?page=members-subscriptions&action=view&subscription=' . $subscription->id)),
                            '#' . $subscription->id
                        );
                    }
                }
                
                // Fallback if subscription not found or function not available
                return '#' . $item->subscription_id;
            
            case 'amount':
                // Get currency symbol and formatting
                $currency_symbol = '$'; // Default
                $currency_position = 'before'; // Default
                
                // Check if WordPress Currency settings are available
                if (function_exists('get_woocommerce_currency_symbol')) {
                    $currency_symbol = get_woocommerce_currency_symbol();
                }
                
                $amount = floatval($item->amount);
                
                // Format the amount based on currency position
                if ($currency_position === 'before') {
                    $formatted = $currency_symbol . number_format_i18n($amount, 2);
                } else {
                    $formatted = number_format_i18n($amount, 2) . $currency_symbol;
                }
                
                // Add special styling based on transaction type
                if (isset($item->txn_type) && $item->txn_type === 'refund') {
                    return '<span class="amount-refund">-' . $formatted . '</span>';
                }
                
                return $formatted;
            
            case 'status':
                $status_labels = [
                    'complete' => __('Complete', 'members'),
                    'pending'  => __('Pending', 'members'),
                    'failed'   => __('Failed', 'members'),
                    'refunded' => __('Refunded', 'members'),
                ];
                
                $status = isset($status_labels[$item->status]) ? $status_labels[$item->status] : ucfirst($item->status);
                
                return sprintf(
                    '<span class="transaction-status transaction-status-%s">%s</span>',
                    esc_attr($item->status),
                    esc_html($status)
                );
            
            case 'type':
                $type_labels = [
                    'payment' => __('Payment', 'members'),
                    'refund'  => __('Refund', 'members'),
                    'renewal' => __('Renewal', 'members'),
                ];
                
                $type = isset($item->txn_type) ? $item->txn_type : 'payment';
                $type_label = isset($type_labels[$type]) ? $type_labels[$type] : ucfirst($type);
                
                return sprintf(
                    '<span class="transaction-type transaction-type-%s">%s</span>',
                    esc_attr($type),
                    esc_html($type_label)
                );
            
            case 'gateway':
                $gateway_labels = [
                    'manual' => __('Manual', 'members'),
                    'stripe' => __('Stripe', 'members'),
                    'paypal' => __('PayPal', 'members'),
                ];
                
                return isset($gateway_labels[$item->gateway]) ? $gateway_labels[$item->gateway] : ucfirst($item->gateway);
            
            case 'notes':
                // Get transaction notes if available
                if (empty($item->notes)) {
                    // Check transaction meta table for notes
                    global $wpdb;
                    $meta_table = $wpdb->prefix . 'members_transactions_meta';
                    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$meta_table'");
                    
                    if ($table_exists) {
                        $notes = $wpdb->get_var($wpdb->prepare(
                            "SELECT meta_value FROM $meta_table WHERE transaction_id = %d AND meta_key = 'notes'",
                            $item->id
                        ));
                        
                        if (!empty($notes)) {
                            return sprintf(
                                '<div class="transaction-notes-summary">%s</div>',
                                wp_trim_words($notes, 10, '...')
                            );
                        }
                    }
                    
                    return '<span class="na">—</span>';
                }
                
                return sprintf(
                    '<div class="transaction-notes-summary">%s</div>',
                    wp_trim_words($item->notes, 10, '...')
                );
            
            case 'date':
                $date = strtotime($item->created_at);
                $display_date = date_i18n(get_option('date_format'), $date);
                $display_time = date_i18n(get_option('time_format'), $date);
                
                return sprintf(
                    '<time datetime="%s" title="%s">%s</time><br><span class="time">%s</span>',
                    esc_attr(date('c', $date)),
                    esc_attr(date('c', $date)),
                    esc_html($display_date),
                    esc_html($display_time)
                );
            
            case 'actions':
                return $this->get_row_actions($item);
            
            default:
                return print_r($item, true);
        }
    }
    
    /**
     * Get row actions
     *
     * @param object $item
     * @return string
     */
    private function get_row_actions($item) {
        $actions = '<div class="transaction-actions">';
        
        // Different actions based on transaction status
        if ($item->status === 'pending' && $item->gateway === 'manual') {
            // Manual pending transactions can be approved or rejected
            $approve_url = wp_nonce_url(
                admin_url('admin-post.php?action=members_manual_approve_transaction&transaction_id=' . $item->id),
                'approve_transaction'
            );
            
            $reject_url = wp_nonce_url(
                admin_url('admin-post.php?action=members_manual_reject_transaction&transaction_id=' . $item->id),
                'reject_transaction'
            );
            
            $actions .= sprintf(
                '<a href="%s" class="approve" title="%s">%s</a>',
                esc_url($approve_url),
                esc_attr__('Approve this transaction', 'members'),
                __('Approve', 'members')
            );
            
            $actions .= sprintf(
                '<a href="%s" class="reject" title="%s">%s</a>',
                esc_url($reject_url),
                esc_attr__('Reject this transaction', 'members'),
                __('Reject', 'members')
            );
        } elseif ($item->status === 'complete' && $item->txn_type === 'payment') {
            // Completed payments can be refunded
            $refund_url = wp_nonce_url(
                admin_url('admin-post.php?action=members_refund_transaction&transaction_id=' . $item->id),
                'refund_transaction'
            );
            
            $actions .= sprintf(
                '<a href="%s" class="refund" title="%s">%s</a>',
                esc_url($refund_url),
                esc_attr__('Refund this transaction', 'members'),
                __('Refund', 'members')
            );
        }
        
        // All transactions can be viewed in detail
        $view_url = admin_url('admin.php?page=members-transactions&action=view&transaction_id=' . $item->id);
        
        $actions .= sprintf(
            '<a href="%s" class="view" title="%s">%s</a>',
            esc_url($view_url),
            esc_attr__('View transaction details', 'members'),
            __('View', 'members')
        );
        
        $actions .= '</div>';
        
        return $actions;
    }
    
    /**
     * Checkbox column
     *
     * @param object $item
     * @return string
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="transaction_id[]" value="%d" />',
            $item->id
        );
    }
    
    /**
     * Get bulk actions
     *
     * @return array
     */
    public function get_bulk_actions() {
        $actions = [
            'approve' => __('Approve', 'members'),
            'reject'  => __('Reject', 'members'),
        ];
        
        return $actions;
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
            
            $transaction_ids = isset($_REQUEST['transaction_id']) ? $_REQUEST['transaction_id'] : [];
            
            if (!is_array($transaction_ids)) {
                $transaction_ids = [$transaction_ids];
            }
            
            $transaction_ids = array_map('absint', $transaction_ids);
            
            // Process actions
            switch ($action) {
                case 'approve':
                    // Process approval
                    foreach ($transaction_ids as $transaction_id) {
                        // Get transaction
                        $transaction = Subscriptions\get_transaction($transaction_id);
                        
                        if (!$transaction || $transaction->status !== 'pending') {
                            continue;
                        }
                        
                        // Get gateway
                        $gateway_manager = Subscriptions\gateways\Gateway_Manager::get_instance();
                        $gateway = $gateway_manager->get_gateway($transaction->gateway);
                        
                        if (!$gateway) {
                            continue;
                        }
                        
                        // Approve transaction - use gateway method if available
                        if (method_exists($gateway, 'admin_approve_transaction')) {
                            $gateway->admin_approve_transaction($transaction_id);
                        } else {
                            // Default approval method
                            // Update transaction status
                            Subscriptions\update_transaction($transaction_id, ['status' => 'complete']);
                            
                            // If there's a subscription ID, activate that subscription
                            if (!empty($transaction->subscription_id)) {
                                $subscription = Subscriptions\get_subscription($transaction->subscription_id);
                                
                                if ($subscription && $subscription->status === 'pending') {
                                    Subscriptions\activate_subscription($transaction->subscription_id);
                                }
                            }
                        }
                    }
                    break;
                
                case 'reject':
                    // Process rejection
                    foreach ($transaction_ids as $transaction_id) {
                        // Get transaction
                        $transaction = Subscriptions\get_transaction($transaction_id);
                        
                        if (!$transaction || $transaction->status !== 'pending') {
                            continue;
                        }
                        
                        // Get gateway
                        $gateway_manager = Subscriptions\gateways\Gateway_Manager::get_instance();
                        $gateway = $gateway_manager->get_gateway($transaction->gateway);
                        
                        if (!$gateway) {
                            continue;
                        }
                        
                        // Reject transaction - use gateway method if available
                        if (method_exists($gateway, 'admin_reject_transaction')) {
                            $gateway->admin_reject_transaction($transaction_id);
                        } else {
                            // Default rejection method
                            // Update transaction status
                            Subscriptions\update_transaction($transaction_id, ['status' => 'failed']);
                            
                            // If there's a subscription ID, cancel that subscription
                            if (!empty($transaction->subscription_id)) {
                                $subscription = Subscriptions\get_subscription($transaction->subscription_id);
                                
                                if ($subscription && ($subscription->status === 'pending' || $subscription->status === 'active')) {
                                    Subscriptions\update_subscription($transaction->subscription_id, ['status' => 'failed']);
                                }
                            }
                        }
                    }
                    break;
            }
        }
    }
    
    /**
     * Get views (status filters)
     *
     * @return array
     */
    public function get_views() {
        global $wpdb;
        
        $views = [];
        $current = isset($_REQUEST['status']) ? sanitize_key($_REQUEST['status']) : 'all';
        $base_url = admin_url('admin.php?page=members-transactions');
        
        // All transactions count
        $all_count = $this->get_total_items();
        
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url($base_url),
            $current === 'all' || empty($current) ? 'current' : '',
            __('All', 'members'),
            $all_count
        );
        
        // Get counts for other statuses
        $table_name = Subscriptions\get_transactions_table_name();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if ($table_exists) {
            // Status counts
            $statuses = ['pending', 'complete', 'failed', 'refunded'];
            
            foreach ($statuses as $status) {
                $count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_name WHERE status = %s",
                        $status
                    )
                );
                
                $status_url = add_query_arg('status', $status, $base_url);
                
                $views[$status] = sprintf(
                    '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                    esc_url($status_url),
                    $current === $status ? 'current' : '',
                    ucfirst($status),
                    $count
                );
            }
        }
        
        return $views;
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
        
        // Get table
        $table_name = Subscriptions\get_transactions_table_name();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        // Set pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Build query
        if ($table_exists) {
            // Start with base query
            $query = "SELECT * FROM $table_name WHERE 1=1";
            $count_query = "SELECT COUNT(*) FROM $table_name WHERE 1=1";
            
            // Add filters
            $query_args = [];
            
            // Status filter
            if (isset($_REQUEST['status']) && !empty($_REQUEST['status'])) {
                $status = sanitize_key($_REQUEST['status']);
                $query .= " AND status = %s";
                $count_query .= " AND status = %s";
                $query_args[] = $status;
            }
            
            // Gateway filter
            if (isset($_REQUEST['gateway']) && !empty($_REQUEST['gateway'])) {
                $gateway = sanitize_key($_REQUEST['gateway']);
                $query .= " AND gateway = %s";
                $count_query .= " AND gateway = %s";
                $query_args[] = $gateway;
            }
            
            // Date range filter
            if (isset($_REQUEST['date_from']) && !empty($_REQUEST['date_from'])) {
                $date_from = sanitize_text_field($_REQUEST['date_from']) . ' 00:00:00';
                $query .= " AND created_at >= %s";
                $count_query .= " AND created_at >= %s";
                $query_args[] = $date_from;
            }
            
            if (isset($_REQUEST['date_to']) && !empty($_REQUEST['date_to'])) {
                $date_to = sanitize_text_field($_REQUEST['date_to']) . ' 23:59:59';
                $query .= " AND created_at <= %s";
                $count_query .= " AND created_at <= %s";
                $query_args[] = $date_to;
            }
            
            // Search filter
            if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
                $search = sanitize_text_field($_REQUEST['s']);
                $query .= " AND (trans_num LIKE %s OR id LIKE %s)";
                $count_query .= " AND (trans_num LIKE %s OR id LIKE %s)";
                $query_args[] = '%' . $wpdb->esc_like($search) . '%';
                $query_args[] = '%' . $wpdb->esc_like($search) . '%';
            }
            
            // Order by
            $orderby = 'created_at';
            $order = 'DESC';
            
            if (isset($_REQUEST['orderby']) && !empty($_REQUEST['orderby'])) {
                $allowed_keys = ['id', 'user_id', 'product_id', 'amount', 'status', 'txn_type', 'gateway', 'created_at'];
                $orderby = in_array($_REQUEST['orderby'], $allowed_keys) ? $_REQUEST['orderby'] : 'created_at';
            }
            
            if (isset($_REQUEST['order']) && !empty($_REQUEST['order'])) {
                $order = strtoupper($_REQUEST['order']) === 'ASC' ? 'ASC' : 'DESC';
            }
            
            $query .= " ORDER BY $orderby $order";
            
            // Add pagination
            $query .= " LIMIT %d, %d";
            $query_args[] = $offset;
            $query_args[] = $per_page;
            
            // Prepare query if we have args
            if (!empty($query_args)) {
                $count_args = array_slice($query_args, 0, -2); // Remove pagination args
                $prepared_query = $wpdb->prepare($query, $query_args);
                $prepared_count_query = !empty($count_args) ? $wpdb->prepare($count_query, $count_args) : $count_query;
            } else {
                $prepared_query = $query;
                $prepared_count_query = $count_query;
            }
            
            // Get total items
            $total_items = $wpdb->get_var($prepared_count_query);
            
            // Get transactions
            $this->items = $wpdb->get_results($prepared_query);
        } else {
            // Table doesn't exist
            $this->items = [];
            $total_items = 0;
        }
        
        // Set pagination arguments
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }
    
    /**
     * Get total items count
     *
     * @return int
     */
    public function get_total_items() {
        global $wpdb;
        
        $table_name = Subscriptions\get_transactions_table_name();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if ($table_exists) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        }
        
        return 0;
    }
    
    /**
     * Display message when no items are found
     */
    public function no_items() {
        global $wpdb;
        
        $table_name = Subscriptions\get_transactions_table_name();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if (!$table_exists) {
            _e('The transactions table does not exist yet. You need to run the database migration first.', 'members');
        } else {
            _e('No transactions found.', 'members');
        }
    }
}