<?php

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Admin notifications handler
 */
class Admin_Notifications {

    /**
     * Option name for storing dismissed notifications
     */
    const DISMISSED_OPTION = 'members_subscriptions_dismissed_notifications';

    /**
     * Initialize the notifications
     */
    public static function init() {
        add_action('admin_init', [__CLASS__, 'setup_notifications']);
        add_action('admin_notices', [__CLASS__, 'display_notifications']);
        add_action('wp_ajax_members_subscriptions_dismiss_notification', [__CLASS__, 'ajax_dismiss_notification']);
    }

    /**
     * Setup notifications
     */
    public static function setup_notifications() {
        // Check if we need to show the database update notification
        self::maybe_add_db_update_notification();
    }

    /**
     * Maybe add database update notification
     */
    private static function maybe_add_db_update_notification() {
        // Get current DB version
        $current_db_version = get_option('members_subscriptions_db_version', '0.0.0');
        
        // If DB version is less than 1.0.2, show notification to run DB update
        if (version_compare($current_db_version, '1.0.2', '<')) {
            // Check if products_meta table exists as an additional check
            global $wpdb;
            $table_name = $wpdb->prefix . 'members_products_meta';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            
            if (!$table_exists) {
                self::add_notification(
                    'database_update',
                    __('Database Update Required: Members Subscriptions needs to update its database tables. This is required to use the product creation features.', 'members'),
                    'warning',
                    true,
                    'https://members-plugin.com/docs/subscriptions/database-updates/',
                    admin_url('admin.php?page=members-subscriptions&action=update_database')
                );
            }
        }
    }

    /**
     * Add a notification
     *
     * @param string $id         Unique notification ID
     * @param string $message    Notification message
     * @param string $type       Notification type (info, warning, error, success)
     * @param bool   $dismissible Whether the notification is dismissible
     * @param string $help_url   URL to help article (optional)
     * @param string $action_url URL for primary action (optional)
     * @param string $action_text Text for primary action (optional)
     */
    public static function add_notification($id, $message, $type = 'info', $dismissible = true, $help_url = '', $action_url = '', $action_text = '') {
        // Get existing notifications
        $notifications = get_option('members_subscriptions_notifications', []);
        
        // Add new notification
        $notifications[$id] = [
            'message' => $message,
            'type' => $type,
            'dismissible' => $dismissible,
            'help_url' => $help_url,
            'action_url' => $action_url,
            'action_text' => $action_text ?: __('Learn More', 'members'),
            'timestamp' => time(),
        ];
        
        // Save notifications
        update_option('members_subscriptions_notifications', $notifications);
    }

    /**
     * Remove a notification
     *
     * @param string $id Notification ID
     */
    public static function remove_notification($id) {
        // Get existing notifications
        $notifications = get_option('members_subscriptions_notifications', []);
        
        // Remove notification
        if (isset($notifications[$id])) {
            unset($notifications[$id]);
            update_option('members_subscriptions_notifications', $notifications);
        }
    }

    /**
     * Display admin notifications
     */
    public static function display_notifications() {
        // Only show on Members plugin screens
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'members') === false) {
            return;
        }
        
        // Get notifications
        $notifications = get_option('members_subscriptions_notifications', []);
        
        // Get dismissed notifications
        $dismissed = get_option(self::DISMISSED_OPTION, []);
        
        // Display each notification
        foreach ($notifications as $id => $notification) {
            // Skip if dismissed
            if (isset($dismissed[$id])) {
                continue;
            }
            
            // Display notification
            self::display_notification($id, $notification);
        }
    }

    /**
     * Display a single notification
     *
     * @param string $id           Notification ID
     * @param array  $notification Notification data
     */
    private static function display_notification($id, $notification) {
        $type = isset($notification['type']) ? $notification['type'] : 'info';
        $dismissible = isset($notification['dismissible']) ? $notification['dismissible'] : true;
        $dismiss_class = $dismissible ? 'is-dismissible' : '';
        $dismiss_data = $dismissible ? ' data-notification-id="' . esc_attr($id) . '"' : '';
        
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> <?php echo $dismiss_class; ?> members-subscriptions-notice"<?php echo $dismiss_data; ?>>
            <p><?php echo wp_kses_post($notification['message']); ?></p>
            
            <?php if (!empty($notification['action_url']) || !empty($notification['help_url'])) : ?>
                <p>
                    <?php if (!empty($notification['action_url'])) : ?>
                        <a href="<?php echo esc_url($notification['action_url']); ?>" class="button button-primary"><?php echo !empty($notification['action_text']) ? esc_html($notification['action_text']) : esc_html__('Learn More', 'members'); ?></a>
                    <?php endif; ?>
                    
                    <?php if (!empty($notification['help_url'])) : ?>
                        <a href="<?php echo esc_url($notification['help_url']); ?>" class="button button-secondary" target="_blank"><?php esc_html_e('Documentation', 'members'); ?></a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
        
        if ($dismissible) {
            // Add JS for dismissing notifications
            static $js_added = false;
            
            if (!$js_added) {
                ?>
                <script>
                    jQuery(document).ready(function($) {
                        $(document).on('click', '.members-subscriptions-notice.is-dismissible .notice-dismiss', function() {
                            var $notice = $(this).closest('.members-subscriptions-notice');
                            var notificationId = $notice.data('notification-id');
                            
                            if (notificationId) {
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'members_subscriptions_dismiss_notification',
                                        notification_id: notificationId,
                                        _ajax_nonce: '<?php echo wp_create_nonce('members_subscriptions_dismiss_notification'); ?>'
                                    }
                                });
                            }
                        });
                    });
                </script>
                <?php
                
                $js_added = true;
            }
        }
    }

    /**
     * AJAX handler for dismissing notifications
     */
    public static function ajax_dismiss_notification() {
        // Check nonce
        check_ajax_referer('members_subscriptions_dismiss_notification');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_die(-1);
        }
        
        // Get notification ID
        $notification_id = isset($_POST['notification_id']) ? sanitize_key($_POST['notification_id']) : '';
        
        if (!$notification_id) {
            wp_die(-1);
        }
        
        // Get dismissed notifications
        $dismissed = get_option(self::DISMISSED_OPTION, []);
        
        // Add to dismissed
        $dismissed[$notification_id] = time();
        
        // Save dismissed
        update_option(self::DISMISSED_OPTION, $dismissed);
        
        wp_die(1);
    }
}

// Initialize notifications on a later hook to ensure everything is loaded
add_action('admin_init', [__NAMESPACE__ . '\Admin_Notifications', 'init'], 5);