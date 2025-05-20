<?php

namespace Members\Subscriptions\admin;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions;

// Load WP_List_Table if not loaded
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
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
            'singular' => __('Subscription', 'members'),
            'plural'   => __('Subscriptions', 'members'),
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
            'cb'             => '<input type="checkbox" />',
            'id'             => __('ID', 'members'),
            'user'           => __('User', 'members'),
            'product'        => __('Membership', 'members'),
            'gateway'        => __('Payment Method', 'members'),
            'amount'         => __('Amount', 'members'),
            'status'         => __('Status', 'members'),
            'health'         => __('Health', 'members'),
            'created_at'     => __('Start Date', 'members'),
            'next_renewal'   => __('Next Renewal', 'members'),
            'expires_at'     => __('Expiry Date', 'members'),
            'renewal_count'  => __('Renewals', 'members'),
            'actions'        => __('Actions', 'members'),
        ];
    }
    
    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns() {
        return [
            'id'           => ['id', true],
            'user'         => ['user_id', false],
            'product'      => ['product_id', false],
            'amount'       => ['price', false],
            'status'       => ['status', false],
            'created_at'   => ['created_at', true],
            'expires_at'   => ['expires_at', false],
            'renewal_count' => ['renewal_count', false],
        ];
    }
    
    /**
     * Get bulk actions
     *
     * @return array
     */
    public function get_bulk_actions() {
        return [
            'cancel'    => __('Cancel', 'members'),
            'suspend'   => __('Suspend', 'members'),
            'reactivate' => __('Reactivate', 'members'),
            'delete'    => __('Delete', 'members'),
        ];
    }
    
    /**
     * Prepare items
     */
    public function prepare_items() {
        global $wpdb;
        
        // Column headers
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
        
        // Process bulk actions
        $this->process_bulk_action();
        
        // Get table name
        $table_name = Subscriptions\get_subscriptions_table_name();
        
        // Ensure the table exists before continuing
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            $this->items = [];
            $this->set_pagination_args([
                'total_items' => 0,
                'per_page'    => 20,
                'total_pages' => 0,
            ]);
            return;
        }
        
        // Pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Filters
        $where = '1=1';
        $args = [];
        
        // User filter
        if (!empty($_REQUEST['user_id'])) {
            $where .= ' AND user_id = %d';
            $args[] = intval($_REQUEST['user_id']);
        }
        
        // Product filter
        if (!empty($_REQUEST['product_id'])) {
            $where .= ' AND product_id = %d';
            $args[] = intval($_REQUEST['product_id']);
        }
        
        // Status filter
        if (!empty($_REQUEST['status'])) {
            $where .= ' AND status = %s';
            $args[] = sanitize_text_field($_REQUEST['status']);
        }
        
        // Gateway filter
        if (!empty($_REQUEST['gateway'])) {
            $where .= ' AND gateway = %s';
            $args[] = sanitize_text_field($_REQUEST['gateway']);
        }
        
        // Date range filter for created_at
        if (!empty($_REQUEST['date_from'])) {
            $where .= ' AND created_at >= %s';
            $args[] = sanitize_text_field($_REQUEST['date_from']) . ' 00:00:00';
        }
        
        if (!empty($_REQUEST['date_to'])) {
            $where .= ' AND created_at <= %s';
            $args[] = sanitize_text_field($_REQUEST['date_to']) . ' 23:59:59';
        }
        
        // Search
        if (!empty($_REQUEST['s'])) {
            $search = '%' . $wpdb->esc_like($_REQUEST['s']) . '%';
            
            // Join with users table for searching by name or email
            $join = "LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID";
            $where .= " AND (
                s.id LIKE %s OR 
                u.user_email LIKE %s OR 
                u.display_name LIKE %s OR 
                s.subscr_id LIKE %s
            )";
            $args[] = $search;
            $args[] = $search;
            $args[] = $search;
            $args[] = $search;
        } else {
            $join = '';
        }
        
        // Ordering
        $orderby = !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'created_at';
        $order = !empty($_REQUEST['order']) ? $_REQUEST['order'] : 'desc';
        
        // Ensure orderby is valid
        $allowed_orderby = ['id', 'user_id', 'product_id', 'price', 'status', 'created_at', 'expires_at', 'renewal_count'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'created_at';
        }
        
        // Ensure order is valid
        if (!in_array(strtolower($order), ['asc', 'desc'])) {
            $order = 'desc';
        }
        
        // Build query
        if (empty($join)) {
            $query = "SELECT * FROM {$table_name} s WHERE {$where} ORDER BY {$orderby} {$order} LIMIT {$per_page} OFFSET {$offset}";
            $count_query = "SELECT COUNT(*) FROM {$table_name} s WHERE {$where}";
        } else {
            $query = "SELECT s.* FROM {$table_name} s {$join} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT {$per_page} OFFSET {$offset}";
            $count_query = "SELECT COUNT(*) FROM {$table_name} s {$join} WHERE {$where}";
        }
        
        // Get items
        if (!empty($args)) {
            $items = $wpdb->get_results($wpdb->prepare($query, $args));
            $total_items = $wpdb->get_var($wpdb->prepare($count_query, $args));
        } else {
            $items = $wpdb->get_results($query);
            $total_items = $wpdb->get_var($count_query);
        }
        
        // Set items
        $this->items = $items;
        
        // Set pagination args
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
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
                return sprintf(
                    '<a href="%s">%d</a>',
                    esc_url(add_query_arg(['subscription' => $item->id, 'action' => 'edit'], admin_url('admin.php?page=members-subscriptions'))),
                    $item->id
                );
                
            case 'created_at':
            case 'expires_at':
                $date = $item->$column_name;
                if (empty($date) || $date === '0000-00-00 00:00:00') {
                    return $column_name === 'expires_at' ? __('Never', 'members') : '—';
                }
                
                $time = strtotime($date);
                return date_i18n(get_option('date_format'), $time);
                
            case 'renewal_count':
                return $item->renewal_count ? $item->renewal_count : '0';
                
            default:
                return isset($item->$column_name) ? $item->$column_name : '—';
        }
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
     * Column user
     *
     * @param object $item
     * @return string
     */
    public function column_user($item) {
        $user = get_userdata($item->user_id);
        
        if (!$user) {
            return sprintf(
                '<span class="na">%s (#%d)</span>',
                __('User deleted', 'members'),
                $item->user_id
            );
        }
        
        $edit_link = get_edit_user_link($item->user_id);
        $output = sprintf(
            '<a href="%s">%s (#%d)</a>',
            esc_url($edit_link),
            esc_html($user->display_name),
            $item->user_id
        );
        
        // Add subscription count if available
        $subscriptions_count = $this->get_user_subscriptions_count($item->user_id);
        if ($subscriptions_count > 1) {
            $output .= sprintf(
                '<br><small class="meta">%s</small>',
                sprintf(_n('%d subscription', '%d subscriptions', $subscriptions_count, 'members'), $subscriptions_count)
            );
        }
        
        return $output;
    }
    
    /**
     * Get user subscriptions count
     *
     * @param int $user_id
     * @return int
     */
    private function get_user_subscriptions_count($user_id) {
        global $wpdb;
        
        $table_name = Subscriptions\get_subscriptions_table_name();
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
            $user_id
        ));
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
            return sprintf(
                '<span class="na">%s (#%d)</span>',
                __('Product deleted', 'members'),
                $item->product_id
            );
        }
        
        $edit_link = get_edit_post_link($item->product_id);
        $output = sprintf(
            '<a href="%s">%s (#%d)</a>',
            esc_url($edit_link),
            esc_html($product->post_title),
            $item->product_id
        );
        
        return $output;
    }
    
    /**
     * Column amount
     *
     * @param object $item
     * @return string
     */
    public function column_amount($item) {
        $amount = floatval($item->price);
        
        // Get currency symbol and formatting
        $currency_symbol = '$'; // Default
        $currency_position = 'before'; // Default
        
        // Check if WordPress Currency settings are available
        if (function_exists('get_woocommerce_currency_symbol')) {
            $currency_symbol = get_woocommerce_currency_symbol();
        }
        
        // Format the amount based on currency position
        if ($currency_position === 'before') {
            $formatted_amount = $currency_symbol . number_format_i18n($amount, 2);
        } else {
            $formatted_amount = number_format_i18n($amount, 2) . $currency_symbol;
        }
        
        return $formatted_amount;
    }
    
    /**
     * Column status
     *
     * @param object $item
     * @return string
     */
    public function column_status($item) {
        $status = $item->status;
        $status_labels = [
            'active'    => __('Active', 'members'),
            'cancelled' => __('Cancelled', 'members'),
            'expired'   => __('Expired', 'members'),
            'suspended' => __('Suspended', 'members'),
            'pending'   => __('Pending', 'members'),
            'trialing'  => __('Trialing', 'members'),
        ];
        
        $status_label = isset($status_labels[$status]) ? $status_labels[$status] : $status;
        
        return sprintf(
            '<span class="members-sub-status members-sub-status-%s">%s</span>',
            sanitize_html_class(strtolower($status)),
            esc_html($status_label)
        );
    }
    
    /**
     * Column health
     *
     * @param object $item
     * @return string
     */
    public function column_health($item) {
        // Only calculate health for active subscriptions
        if ($item->status !== 'active') {
            return '—';
        }
        
        // If no expiration date, it's in good health
        if (empty($item->expires_at) || $item->expires_at === '0000-00-00 00:00:00') {
            return sprintf(
                '<span class="subscription-health subscription-health-good">%s</span>',
                __('Good', 'members')
            );
        }
        
        // Calculate days until expiration
        $now = current_time('timestamp');
        $expires = strtotime($item->expires_at);
        
        if ($expires < $now) {
            // Already expired
            return sprintf(
                '<span class="subscription-health subscription-health-critical">%s</span><span class="subscription-health-desc">%s</span>',
                __('Expired', 'members'),
                __('Subscription has expired', 'members')
            );
        }
        
        $days_left = round(($expires - $now) / DAY_IN_SECONDS);
        
        if ($days_left <= 7) {
            return sprintf(
                '<span class="subscription-health subscription-health-critical">%s</span><span class="subscription-health-desc">%s</span>',
                __('Critical', 'members'),
                sprintf(_n('Expires in %d day', 'Expires in %d days', $days_left, 'members'), $days_left)
            );
        } else if ($days_left <= 30) {
            return sprintf(
                '<span class="subscription-health subscription-health-atrisk">%s</span><span class="subscription-health-desc">%s</span>',
                __('At Risk', 'members'),
                sprintf(_n('Expires in %d day', 'Expires in %d days', $days_left, 'members'), $days_left)
            );
        } else if ($days_left <= 90) {
            return sprintf(
                '<span class="subscription-health subscription-health-warning">%s</span><span class="subscription-health-desc">%s</span>',
                __('Warning', 'members'),
                sprintf(_n('Expires in %d day', 'Expires in %d days', $days_left, 'members'), $days_left)
            );
        } else {
            return sprintf(
                '<span class="subscription-health subscription-health-good">%s</span><span class="subscription-health-desc">%s</span>',
                __('Good', 'members'),
                sprintf(_n('Expires in %d day', 'Expires in %d days', $days_left, 'members'), $days_left)
            );
        }
    }
    
    /**
     * Column next_renewal
     *
     * @param object $item
     * @return string
     */
    public function column_next_renewal($item) {
        // Only active subscriptions can renew
        if ($item->status !== 'active') {
            return '—';
        }
        
        // If no expiration date, it's lifetime
        if (empty($item->expires_at) || $item->expires_at === '0000-00-00 00:00:00') {
            return __('Never (Lifetime)', 'members');
        }
        
        // Calculate next renewal date
        $next_renewal = $item->expires_at;
        if (empty($next_renewal)) {
            return '—';
        }
        
        $time = strtotime($next_renewal);
        $now = current_time('timestamp');
        $days_left = round(($time - $now) / DAY_IN_SECONDS);
        
        // Format the date
        $formatted_date = date_i18n(get_option('date_format'), $time);
        
        // Add countdown if within 90 days
        if ($days_left > 0 && $days_left <= 90) {
            $countdown_class = '';
            if ($days_left <= 7) {
                $countdown_class = 'days-7';
            } else if ($days_left <= 14) {
                $countdown_class = 'days-14';
            } else if ($days_left <= 30) {
                $countdown_class = 'days-30';
            } else if ($days_left <= 60) {
                $countdown_class = 'days-60';
            } else {
                $countdown_class = 'days-90';
            }
            
            return sprintf(
                '<div class="subscription-date"><span class="subscription-date-primary">%s</span><span class="subscription-date-secondary">%s <span class="subscription-countdown %s">%s</span></span></div>',
                $formatted_date,
                __('Renews in', 'members'),
                $countdown_class,
                sprintf(_n('%d day', '%d days', $days_left, 'members'), $days_left)
            );
        }
        
        return sprintf(
            '<div class="subscription-date"><span class="subscription-date-primary">%s</span></div>',
            $formatted_date
        );
    }
    
    /**
     * Column actions
     *
     * @param object $item
     * @return string
     */
    public function column_actions($item) {
        $base_url = admin_url('admin.php?page=members-subscriptions&subscription=' . $item->id);
        
        $actions = '<div class="subscription-actions">';
        
        // Edit action
        $actions .= sprintf(
            '<a href="%s" class="button button-small">%s</a>',
            esc_url(add_query_arg(['action' => 'edit'], $base_url)),
            __('Edit', 'members')
        );
        
        // Status dependent actions
        switch ($item->status) {
            case 'active':
                // Cancel action
                $actions .= sprintf(
                    '<a href="%s" class="button button-small" onclick="return confirm(\'%s\');">%s</a>',
                    esc_url(wp_nonce_url(add_query_arg(['action' => 'cancel'], $base_url), 'members_cancel_subscription')),
                    esc_js(__('Are you sure you want to cancel this subscription?', 'members')),
                    __('Cancel', 'members')
                );
                
                // Renew action
                $actions .= sprintf(
                    '<a href="%s" class="button button-small">%s</a>',
                    esc_url(wp_nonce_url(add_query_arg(['action' => 'renew'], $base_url), 'members_renew_subscription')),
                    __('Renew', 'members')
                );
                break;
                
            case 'cancelled':
            case 'expired':
                // Reactivate action
                $actions .= sprintf(
                    '<a href="%s" class="button button-small">%s</a>',
                    esc_url(wp_nonce_url(add_query_arg(['action' => 'reactivate'], $base_url), 'members_reactivate_subscription')),
                    __('Reactivate', 'members')
                );
                break;
                
            case 'suspended':
                // Resume action
                $actions .= sprintf(
                    '<a href="%s" class="button button-small">%s</a>',
                    esc_url(wp_nonce_url(add_query_arg(['action' => 'resume'], $base_url), 'members_resume_subscription')),
                    __('Resume', 'members')
                );
                break;
        }
        
        // Delete action
        $actions .= sprintf(
            '<a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'%s\');">%s</a>',
            esc_url(wp_nonce_url(add_query_arg(['action' => 'delete'], $base_url), 'members_delete_subscription')),
            esc_js(__('Are you sure you want to delete this subscription? This action cannot be undone.', 'members')),
            __('Delete', 'members')
        );
        
        $actions .= '</div>';
        
        return $actions;
    }
    
    /**
     * Get views
     *
     * @return array
     */
    public function get_views() {
        global $wpdb;
        
        $status_links = [];
        $current = !empty($_REQUEST['status']) ? $_REQUEST['status'] : 'all';
        
        // All link
        $count = $wpdb->get_var("SELECT COUNT(*) FROM " . Subscriptions\get_subscriptions_table_name());
        $status_links['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
            esc_url(admin_url('admin.php?page=members-subscriptions')),
            $current === 'all' ? 'current' : '',
            __('All', 'members'),
            number_format_i18n($count)
        );
        
        // Status links
        $statuses = ['active', 'cancelled', 'expired', 'suspended', 'pending', 'trialing'];
        
        foreach ($statuses as $status) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . Subscriptions\get_subscriptions_table_name() . " WHERE status = %s",
                $status
            ));
            
            if ($count === '0') {
                continue;
            }
            
            $status_labels = [
                'active'    => __('Active', 'members'),
                'cancelled' => __('Cancelled', 'members'),
                'expired'   => __('Expired', 'members'),
                'suspended' => __('Suspended', 'members'),
                'pending'   => __('Pending', 'members'),
                'trialing'  => __('Trialing', 'members'),
            ];
            
            $label = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status);
            
            $status_links[$status] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                esc_url(add_query_arg(['status' => $status], admin_url('admin.php?page=members-subscriptions'))),
                $current === $status ? 'current' : '',
                $label,
                number_format_i18n($count)
            );
        }
        
        return $status_links;
    }
    
    /**
     * Display extra tablenav
     *
     * @param string $which
     */
    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }
        
        $product_id = isset($_REQUEST['product_id']) ? intval($_REQUEST['product_id']) : '';
        $gateway = isset($_REQUEST['gateway']) ? sanitize_text_field($_REQUEST['gateway']) : '';
        $date_from = isset($_REQUEST['date_from']) ? sanitize_text_field($_REQUEST['date_from']) : '';
        $date_to = isset($_REQUEST['date_to']) ? sanitize_text_field($_REQUEST['date_to']) : '';
        
        ?>
        <div class="subscription-filters">
            <?php
            // Products dropdown
            $products = get_posts([
                'post_type'      => 'members_product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);
            
            if (!empty($products)) {
                ?>
                <select name="product_id" class="product-filter">
                    <option value=""><?php _e('All Products', 'members'); ?></option>
                    <?php foreach ($products as $product) : ?>
                        <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($product_id, $product->ID); ?>>
                            <?php echo esc_html($product->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
            }
            
            // Gateways dropdown
            global $wpdb;
            $gateways = $wpdb->get_col("SELECT DISTINCT gateway FROM " . Subscriptions\get_subscriptions_table_name() . " WHERE gateway != ''");
            
            if (!empty($gateways)) {
                ?>
                <select name="gateway" class="gateway-filter">
                    <option value=""><?php _e('All Gateways', 'members'); ?></option>
                    <?php foreach ($gateways as $gateway_option) : ?>
                        <option value="<?php echo esc_attr($gateway_option); ?>" <?php selected($gateway, $gateway_option); ?>>
                            <?php echo esc_html(ucfirst($gateway_option)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
            }
            ?>
        </div>
        
        <div class="date-filter">
            <input type="text" name="date_from" class="date-picker" placeholder="<?php esc_attr_e('From', 'members'); ?>" value="<?php echo esc_attr($date_from); ?>" />
            <input type="text" name="date_to" class="date-picker" placeholder="<?php esc_attr_e('To', 'members'); ?>" value="<?php echo esc_attr($date_to); ?>" />
        </div>
        
        <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'members'); ?>" />
        
        <?php if (!empty($_REQUEST['s']) || !empty($_REQUEST['product_id']) || !empty($_REQUEST['gateway']) || !empty($_REQUEST['date_from']) || !empty($_REQUEST['date_to']) || !empty($_REQUEST['status'])) : ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=members-subscriptions')); ?>" class="button"><?php _e('Reset', 'members'); ?></a>
        <?php endif; ?>
        
        <?php
    }
    
    /**
     * Process bulk action
     */
    public function process_bulk_action() {
        // Security check
        if (isset($_REQUEST['_wpnonce']) && !empty($_REQUEST['subscription'])) {
            $action = $this->current_action();
            $subscriptions = (array) $_REQUEST['subscription'];
            
            // Check if this is a valid action
            if (in_array($action, ['cancel', 'suspend', 'reactivate', 'delete']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural'])) {
                
                global $wpdb;
                $table_name = Subscriptions\get_subscriptions_table_name();
                $count = 0;
                
                foreach ($subscriptions as $subscription_id) {
                    $subscription_id = intval($subscription_id);
                    
                    // Get current subscription
                    $subscription = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $table_name WHERE id = %d",
                        $subscription_id
                    ));
                    
                    if (!$subscription) {
                        continue;
                    }
                    
                    switch ($action) {
                        case 'cancel':
                            // Only cancel active subscriptions
                            if ($subscription->status === 'active') {
                                $result = $wpdb->update(
                                    $table_name,
                                    ['status' => 'cancelled'],
                                    ['id' => $subscription_id],
                                    ['%s'],
                                    ['%d']
                                );
                                
                                if ($result) {
                                    $count++;
                                    
                                    // Log the cancellation
                                    if (function_exists('\\Members\\Subscriptions\\log_event')) {
                                        Subscriptions\log_event('subscription_cancelled', [
                                            'subscription_id' => $subscription_id,
                                            'user_id' => $subscription->user_id,
                                            'source' => 'admin-bulk',
                                        ]);
                                    }
                                }
                            }
                            break;
                            
                        case 'suspend':
                            // Only suspend active subscriptions
                            if ($subscription->status === 'active') {
                                $result = $wpdb->update(
                                    $table_name,
                                    ['status' => 'suspended'],
                                    ['id' => $subscription_id],
                                    ['%s'],
                                    ['%d']
                                );
                                
                                if ($result) {
                                    $count++;
                                    
                                    // Log the suspension
                                    if (function_exists('\\Members\\Subscriptions\\log_event')) {
                                        Subscriptions\log_event('subscription_suspended', [
                                            'subscription_id' => $subscription_id,
                                            'user_id' => $subscription->user_id,
                                            'source' => 'admin-bulk',
                                        ]);
                                    }
                                }
                            }
                            break;
                            
                        case 'reactivate':
                            // Only reactivate cancelled or expired subscriptions
                            if (in_array($subscription->status, ['cancelled', 'expired', 'suspended'])) {
                                $data = [
                                    'status' => 'active',
                                ];
                                
                                // If expired, set a new expiration date
                                if ($subscription->status === 'expired' && !empty($subscription->period) && !empty($subscription->period_type)) {
                                    $data['expires_at'] = Subscriptions\calculate_subscription_expiration(
                                        $subscription->period, 
                                        $subscription->period_type, 
                                        current_time('mysql')
                                    );
                                }
                                
                                $result = $wpdb->update(
                                    $table_name,
                                    $data,
                                    ['id' => $subscription_id],
                                    ['%s', '%s'],
                                    ['%d']
                                );
                                
                                if ($result) {
                                    $count++;
                                    
                                    // Log the reactivation
                                    if (function_exists('\\Members\\Subscriptions\\log_event')) {
                                        Subscriptions\log_event('subscription_reactivated', [
                                            'subscription_id' => $subscription_id,
                                            'user_id' => $subscription->user_id,
                                            'source' => 'admin-bulk',
                                        ]);
                                    }
                                }
                            }
                            break;
                            
                        case 'delete':
                            $result = $wpdb->delete(
                                $table_name,
                                ['id' => $subscription_id],
                                ['%d']
                            );
                            
                            if ($result) {
                                $count++;
                                
                                // Log the deletion
                                if (function_exists('\\Members\\Subscriptions\\log_event')) {
                                    Subscriptions\log_event('subscription_deleted', [
                                        'subscription_id' => $subscription_id,
                                        'user_id' => $subscription->user_id,
                                        'source' => 'admin-bulk',
                                    ]);
                                }
                            }
                            break;
                    }
                }
                
                // Redirect with status message
                $redirect_url = add_query_arg([
                    'message' => $action . '_' . $count,
                ], admin_url('admin.php?page=members-subscriptions'));
                
                wp_safe_redirect($redirect_url);
                exit;
            }
        }
    }
    
    /**
     * No items found message
     */
    public function no_items() {
        _e('No subscriptions found.', 'members');
    }
}