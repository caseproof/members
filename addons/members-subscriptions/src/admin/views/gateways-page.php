<?php
/**
 * Payment Gateways Admin Page
 */

namespace Members\Subscriptions\admin;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions\gateways\Gateway_Manager;

// Get gateway manager
$gateway_manager = Gateway_Manager::get_instance();
$gateways = $gateway_manager->get_gateways();

// Check if we're viewing a specific gateway's settings
$current_gateway = isset($_GET['gateway']) ? sanitize_key($_GET['gateway']) : '';
$gateway = $current_gateway ? $gateway_manager->get_gateway($current_gateway) : null;

// Check if we need to show a message
$message = '';
if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $message = __('Gateway settings updated successfully.', 'members');
}
?>

<div class="wrap members-subscriptions-wrap">
    <?php if ($gateway) : ?>
        <!-- Single Gateway Settings -->
        <h1 class="wp-heading-inline">
            <?php printf(__('%s Gateway Settings', 'members'), esc_html($gateway->get_name())); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=members-gateways')); ?>" class="page-title-action"><?php _e('Back to Gateways', 'members'); ?></a>
        </h1>
        
        <?php if (!empty($message)) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>
        
        <hr class="wp-header-end">
        
        <div class="members-gateway-settings">
            <h3><?php echo esc_html($gateway->get_name()); ?> <?php _e('Configuration', 'members'); ?></h3>
            
            <form method="post" action="">
                <?php wp_nonce_field('members_save_gateway_settings_' . $gateway->get_id(), 'members_gateway_settings'); ?>
                <input type="hidden" name="gateway_id" value="<?php echo esc_attr($gateway->get_id()); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="members-gateway-enabled">
                                <?php _e('Enable Gateway', 'members'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                    id="members-gateway-enabled" 
                                    name="members_gateway[<?php echo esc_attr($gateway->get_id()); ?>][enabled]" 
                                    value="1" 
                                    class="members-gateway-toggle"
                                    data-gateway="<?php echo esc_attr($gateway->get_id()); ?>"
                                    <?php checked($gateway->is_enabled(), true); ?>>
                                <?php _e('Enable this payment gateway', 'members'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <div id="members-gateway-settings-<?php echo esc_attr($gateway->get_id()); ?>">
                    <table class="form-table">
                        <?php foreach ($gateway->get_settings_fields() as $key => $field) : ?>
                            <tr>
                                <th scope="row">
                                    <label for="members-gateway-<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($field['title']); ?>
                                    </label>
                                </th>
                                <td>
                                    <?php
                                    $value = $gateway->get_setting($key, isset($field['default']) ? $field['default'] : '');
                                    
                                    switch ($field['type']) {
                                        case 'checkbox':
                                            ?>
                                            <label>
                                                <input type="checkbox" 
                                                    id="members-gateway-<?php echo esc_attr($key); ?>" 
                                                    name="members_gateway[<?php echo esc_attr($gateway->get_id()); ?>][<?php echo esc_attr($key); ?>]" 
                                                    value="1" 
                                                    <?php checked($value, true); ?>>
                                                <?php echo isset($field['label']) ? esc_html($field['label']) : ''; ?>
                                            </label>
                                            <?php
                                            break;
                                            
                                        case 'text':
                                            ?>
                                            <input type="text" 
                                                id="members-gateway-<?php echo esc_attr($key); ?>" 
                                                name="members_gateway[<?php echo esc_attr($gateway->get_id()); ?>][<?php echo esc_attr($key); ?>]" 
                                                value="<?php echo esc_attr($value); ?>" 
                                                class="regular-text">
                                            <?php
                                            break;
                                            
                                        case 'password':
                                            ?>
                                            <input type="password" 
                                                id="members-gateway-<?php echo esc_attr($key); ?>" 
                                                name="members_gateway[<?php echo esc_attr($gateway->get_id()); ?>][<?php echo esc_attr($key); ?>]" 
                                                value="<?php echo esc_attr($value); ?>" 
                                                class="regular-text">
                                            <?php
                                            break;
                                            
                                        case 'textarea':
                                            ?>
                                            <textarea 
                                                id="members-gateway-<?php echo esc_attr($key); ?>" 
                                                name="members_gateway[<?php echo esc_attr($gateway->get_id()); ?>][<?php echo esc_attr($key); ?>]" 
                                                class="large-text" 
                                                rows="5"><?php echo esc_textarea($value); ?></textarea>
                                            <?php
                                            break;
                                            
                                        case 'select':
                                            ?>
                                            <select 
                                                id="members-gateway-<?php echo esc_attr($key); ?>" 
                                                name="members_gateway[<?php echo esc_attr($gateway->get_id()); ?>][<?php echo esc_attr($key); ?>]">
                                                <?php foreach ($field['options'] as $option_key => $option_value) : ?>
                                                    <option value="<?php echo esc_attr($option_key); ?>" <?php selected($value, $option_key); ?>>
                                                        <?php echo esc_html($option_value); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php
                                            break;
                                    }
                                    
                                    if (isset($field['description'])) {
                                        echo '<p class="description">' . wp_kses_post($field['description']) . '</p>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                
                <?php submit_button(__('Save Settings', 'members')); ?>
            </form>
        </div>
        
    <?php else : ?>
        <!-- Gateways List -->
        <h1 class="wp-heading-inline"><?php _e('Payment Gateways', 'members'); ?></h1>
        <hr class="wp-header-end">
        
        <div class="postbox">
            <div class="inside">
                <p><?php _e('Payment gateways allow you to accept various payment methods like credit cards or PayPal for your memberships.', 'members'); ?></p>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Gateway', 'members'); ?></th>
                    <th><?php _e('Description', 'members'); ?></th>
                    <th><?php _e('Status', 'members'); ?></th>
                    <th><?php _e('Actions', 'members'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gateways as $gateway) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($gateway->get_name()); ?></strong>
                        </td>
                        <td><?php echo esc_html($gateway->get_description()); ?></td>
                        <td>
                            <?php if ($gateway->is_enabled()) : ?>
                                <span class="members-sub-status members-sub-status-active"><?php _e('Enabled', 'members'); ?></span>
                            <?php else : ?>
                                <span class="members-sub-status members-sub-status-cancelled"><?php _e('Disabled', 'members'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=members-gateways&gateway=' . $gateway->get_id())); ?>" class="button"><?php _e('Settings', 'members'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</style>