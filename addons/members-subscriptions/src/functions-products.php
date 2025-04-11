<?php

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Register necessary hooks for product processing
 */
function init_product_hooks() {
    // Process subscription form
    add_action('init', __NAMESPACE__ . '\process_subscription_form');
    
    // Add meta boxes for product editing
    add_action('add_meta_boxes', __NAMESPACE__ . '\add_product_meta_boxes');
    
    // Save product meta data
    add_action('save_post_members_product', __NAMESPACE__ . '\save_product_meta', 10, 3);
    
    // Update the meta table for existing products on access
    add_action('the_post', __NAMESPACE__ . '\maybe_update_product_meta_table');
}
init_product_hooks();

/**
 * Update product meta table for existing products when viewed
 * This ensures that meta data is properly stored
 */
function maybe_update_product_meta_table($post) {
    if ($post->post_type !== 'members_product') {
        return;
    }
    
    // Basic product meta fields to ensure are stored in the meta table
    $default_fields = [
        '_price' => '0.00',
        '_recurring' => '0', 
        '_period' => '1',
        '_period_type' => 'month',
        '_has_trial' => '0',
        '_trial_days' => '0',
        '_trial_price' => '0.00',
        '_membership_roles' => []
    ];
    
    foreach ($default_fields as $key => $default) {
        // Use get_post_meta directly to avoid infinite loop
        $value = get_post_meta($post->ID, $key, true);
        
        // If meta exists in post meta but not in our table, add it
        if (!empty($value) || $value === '0' || $value === 0) {
            // Check if it exists in our custom table
            global $wpdb;
            $table_name = $wpdb->prefix . 'members_products_meta';
            
            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if ($table_exists) {
                $meta_exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_name WHERE product_id = %d AND meta_key = %s",
                        $post->ID,
                        $key
                    )
                );
                
                if (!$meta_exists) {
                    // Add to meta table
                    if (is_array($value)) {
                        $value = maybe_serialize($value);
                    }
                    
                    $wpdb->insert(
                        $table_name,
                        [
                            'product_id' => $post->ID,
                            'meta_key' => $key,
                            'meta_value' => $value
                        ],
                        ['%d', '%s', '%s']
                    );
                }
            }
        }
    }
}

// We use get_subscription_period_options() from functions-subscriptions.php

/**
 * Process subscription form submission
 */
function process_subscription_form() {
    // Check if form is submitted
    if (!isset($_POST['action']) || $_POST['action'] !== 'members_process_subscription') {
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['members_subscription_nonce']) || !wp_verify_nonce($_POST['members_subscription_nonce'], 'members_subscription_form')) {
        wp_die(__('Security check failed. Please try again.', 'members'));
    }
    
    // Check if user is logged in
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_die(__('You must be logged in to purchase a membership.', 'members'));
    }
    
    // Get product
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $product = get_post($product_id);
    
    if (!$product || $product->post_type !== 'members_product') {
        wp_die(__('Invalid product.', 'members'));
    }
    
    // Check if user already has access
    if (function_exists('user_has_access') && user_has_access($user_id, $product_id)) {
        // Use post meta directly if get_product_meta isn't available or working
        if (function_exists('get_product_meta')) {
            $redirect_url = get_product_meta($product_id, '_redirect_url', get_permalink($product_id));
        } else {
            $redirect_url = get_post_meta($product_id, '_redirect_url', true);
            if (empty($redirect_url)) {
                $redirect_url = get_permalink($product_id);
            }
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    // Get payment method
    $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
    if (empty($payment_method)) {
        wp_die(__('Please select a payment method.', 'members'));
    }
    
    // Get gateway manager
    $gateway_manager = gateways\Gateway_Manager::get_instance();
    $gateway = $gateway_manager->get_gateway($payment_method);
    
    if (!$gateway || !$gateway->is_enabled()) {
        wp_die(__('Invalid payment method.', 'members'));
    }
    
    // Validate payment fields
    $validation = $gateway->validate_payment_fields($_POST);
    if (is_wp_error($validation)) {
        wp_die($validation->get_error_message());
    }
    
    // Get product data
    $price = get_product_meta($product_id, '_price', 0);
    $is_recurring = get_product_meta($product_id, '_recurring', false) && $gateway->supports_subscriptions();
    $period = get_product_meta($product_id, '_period', 1);
    $period_type = get_product_meta($product_id, '_period_type', 'month');
    $has_trial = get_product_meta($product_id, '_has_trial', false) && $gateway->supports_subscriptions();
    $trial_days = get_product_meta($product_id, '_trial_days', 0);
    $trial_price = get_product_meta($product_id, '_trial_price', 0);
    $redirect_url = get_product_meta($product_id, '_redirect_url', get_permalink($product_id));
    
    // Prepare payment data
    $payment_data = [
        'user_id' => $user_id,
        'product_id' => $product_id,
        'product_name' => $product->post_title,
        'amount' => $price,
        'redirect' => $redirect_url,
    ];
    
    // Add subscription data if recurring
    if ($is_recurring) {
        $payment_data['period'] = $period;
        $payment_data['period_type'] = $period_type;
        
        if ($has_trial && $trial_days > 0) {
            $payment_data['trial'] = true;
            $payment_data['trial_days'] = $trial_days;
            $payment_data['trial_amount'] = $trial_price;
        }
    } else {
        // Check if one-time payment has limited access duration
        $has_access_period = get_product_meta($product_id, '_has_access_period', false);
        
        if ($has_access_period) {
            $access_period = get_product_meta($product_id, '_access_period', 1);
            $access_period_type = get_product_meta($product_id, '_access_period_type', 'month');
            
            $payment_data['has_access_period'] = true;
            $payment_data['access_period'] = $access_period;
            $payment_data['access_period_type'] = $access_period_type;
            
            // Calculate expiration date
            $payment_data['expires_at'] = calculate_subscription_expiration($access_period, $access_period_type);
        }
    }
    
    // Add sanitized form data to payment_data - only include allowed fields
    $allowed_fields = array(
        'stripe_payment_method' => 'sanitize_text_field',
        'payment_method' => 'sanitize_text_field',
        'agree_terms' => 'intval',
        'coupon_code' => 'sanitize_text_field',
        'first_name' => 'sanitize_text_field',
        'last_name' => 'sanitize_text_field',
        'email' => 'sanitize_email',
        'address' => 'sanitize_text_field',
        'city' => 'sanitize_text_field',
        'state' => 'sanitize_text_field',
        'zip' => 'sanitize_text_field',
        'country' => 'sanitize_text_field',
    );
    
    foreach ($allowed_fields as $field => $sanitize_callback) {
        if (isset($_POST[$field])) {
            $payment_data[$field] = $sanitize_callback($_POST[$field]);
        }
    }
    
    // Process payment
    if ($is_recurring) {
        $result = $gateway_manager->process_subscription($payment_method, $payment_data);
    } else {
        $result = $gateway_manager->process_payment($payment_method, $payment_data);
    }
    
    // Handle payment result
    if (!empty($result['success'])) {
        // Success - redirect to thank you page
        if (!empty($result['redirect'])) {
            wp_redirect($result['redirect']);
        } else {
            wp_redirect(add_query_arg('payment_status', 'success', get_permalink($product_id)));
        }
        exit;
    } else {
        // Error - display message
        if (!empty($result['requires_action']) && !empty($result['payment_intent_client_secret'])) {
            // 3D Secure required - handle with JS
            // In a real implementation, this would be handled properly with a JS callback
            wp_die(__('Additional authentication required. Please go back and try again.', 'members'));
        } else {
            wp_die(isset($result['message']) ? $result['message'] : __('Payment failed. Please try again.', 'members'));
        }
    }
}

/**
 * Register meta boxes for product editing
 */
function add_product_meta_boxes() {
    add_meta_box(
        'members-product-settings',
        __('Membership Settings', 'members'),
        __NAMESPACE__ . '\render_product_settings_meta_box',
        'members_product',
        'normal',
        'high'
    );
    
    add_meta_box(
        'members-product-pricing',
        __('Pricing', 'members'),
        __NAMESPACE__ . '\render_product_pricing_meta_box',
        'members_product',
        'normal',
        'high'
    );
    
    add_meta_box(
        'members-product-access',
        __('Access Settings', 'members'),
        __NAMESPACE__ . '\render_product_access_meta_box',
        'members_product',
        'normal',
        'high'
    );
}

/**
 * Render product settings meta box
 *
 * @param WP_Post $post The post object.
 */
function render_product_settings_meta_box($post) {
    // Nonce for verification
    wp_nonce_field('members_save_product_meta', 'members_product_meta_nonce');
    
    // Get membership types
    $membership_types = [
        'standard' => __('Standard Membership', 'members'),
        'group' => __('Group Membership', 'members'),
    ];
    
    // Get current values
    $membership_type = get_product_meta($post->ID, '_membership_type', 'standard');
    
    ?>
    <p>
        <label for="members-membership-type"><?php _e('Membership Type:', 'members'); ?></label>
        <select name="members_product_meta[_membership_type]" id="members-membership-type">
            <?php foreach ($membership_types as $type => $label) : ?>
                <option value="<?php echo esc_attr($type); ?>" <?php selected($membership_type, $type); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php
}

/**
 * Render product pricing meta box
 *
 * @param WP_Post $post The post object.
 */
function render_product_pricing_meta_box($post) {
    // Get current values and ensure they're not arrays to avoid warnings
    $price = get_product_meta($post->ID, '_price', '0.00');
    $price = is_array($price) ? '0.00' : $price;
    
    $recurring = get_product_meta($post->ID, '_recurring', false);
    $recurring = is_array($recurring) ? false : (bool)$recurring;
    
    $period = get_product_meta($post->ID, '_period', 1);
    $period = is_array($period) ? 1 : (int)$period;
    
    $period_type = get_product_meta($post->ID, '_period_type', 'month');
    $period_type = is_array($period_type) ? 'month' : $period_type;
    
    $has_trial = get_product_meta($post->ID, '_has_trial', false);
    $has_trial = is_array($has_trial) ? false : (bool)$has_trial;
    
    $trial_days = get_product_meta($post->ID, '_trial_days', 0);
    $trial_days = is_array($trial_days) ? 0 : (int)$trial_days;
    
    $trial_price = get_product_meta($post->ID, '_trial_price', '0.00');
    $trial_price = is_array($trial_price) ? '0.00' : $trial_price;
    
    $has_access_period = get_product_meta($post->ID, '_has_access_period', false);
    $has_access_period = is_array($has_access_period) ? false : (bool)$has_access_period;
    
    $access_period = get_product_meta($post->ID, '_access_period', 1);
    $access_period = is_array($access_period) ? 1 : (int)$access_period;
    
    $access_period_type = get_product_meta($post->ID, '_access_period_type', 'month');
    $access_period_type = is_array($access_period_type) ? 'month' : $access_period_type;
    
    // Get period options
    $period_options = get_subscription_period_options();
    
    ?>
    <p>
        <label for="members-price"><?php _e('Price:', 'members'); ?></label>
        <input type="text" name="members_product_meta[_price]" id="members-price" value="<?php echo esc_attr($price); ?>" class="regular-text" />
    </p>
    
    <p>
        <label>
            <input type="checkbox" name="members_product_meta[_recurring]" value="1" <?php checked($recurring); ?> id="members-recurring" />
            <?php _e('Recurring Payment', 'members'); ?>
        </label>
    </p>
    
    <div id="members-recurring-options" style="<?php echo $recurring ? '' : 'display: none;'; ?>">
        <p>
            <label for="members-period"><?php _e('Billing Cycle:', 'members'); ?></label>
            <input type="number" name="members_product_meta[_period]" id="members-period" value="<?php echo esc_attr($period); ?>" min="1" step="1" style="width: 60px;" />
            <select name="members_product_meta[_period_type]" id="members-period-type">
                <?php foreach ($period_options as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($period_type, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        
        <p>
            <label>
                <input type="checkbox" name="members_product_meta[_has_trial]" value="1" <?php checked($has_trial); ?> id="members-has-trial" />
                <?php _e('Offer Trial', 'members'); ?>
            </label>
        </p>
        
        <div id="members-trial-options" style="<?php echo $has_trial ? '' : 'display: none;'; ?>">
            <p>
                <label for="members-trial-days"><?php _e('Trial Days:', 'members'); ?></label>
                <input type="number" name="members_product_meta[_trial_days]" id="members-trial-days" value="<?php echo esc_attr($trial_days); ?>" min="1" step="1" />
            </p>
            
            <p>
                <label for="members-trial-price"><?php _e('Trial Price:', 'members'); ?></label>
                <input type="text" name="members_product_meta[_trial_price]" id="members-trial-price" value="<?php echo esc_attr($trial_price); ?>" />
                <span class="description"><?php _e('Set to 0 for a free trial.', 'members'); ?></span>
            </p>
        </div>
    </div>
    
    <div id="members-one-time-options" style="<?php echo !$recurring ? '' : 'display: none;'; ?>">
        <p>
            <label>
                <input type="checkbox" name="members_product_meta[_has_access_period]" value="1" <?php checked($has_access_period); ?> id="members-has-access-period" />
                <?php _e('Limited Access Duration', 'members'); ?>
            </label>
            <span class="description"><?php _e('Set a time limit for membership access.', 'members'); ?></span>
        </p>
        
        <div id="members-access-period-options" style="<?php echo $has_access_period ? '' : 'display: none;'; ?>">
            <p>
                <label for="members-access-period"><?php _e('Access Duration:', 'members'); ?></label>
                <input type="number" name="members_product_meta[_access_period]" id="members-access-period" value="<?php echo esc_attr($access_period); ?>" min="1" step="1" style="width: 60px;" />
                <select name="members_product_meta[_access_period_type]" id="members-access-period-type">
                    <?php foreach ($period_options as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($access_period_type, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="description"><?php _e('After this time, membership access will expire.', 'members'); ?></span>
            </p>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#members-recurring').change(function() {
            if ($(this).is(':checked')) {
                $('#members-recurring-options').show();
                $('#members-one-time-options').hide();
            } else {
                $('#members-recurring-options').hide();
                $('#members-one-time-options').show();
            }
        });
        
        $('#members-has-trial').change(function() {
            if ($(this).is(':checked')) {
                $('#members-trial-options').show();
            } else {
                $('#members-trial-options').hide();
            }
        });
        
        $('#members-has-access-period').change(function() {
            if ($(this).is(':checked')) {
                $('#members-access-period-options').show();
            } else {
                $('#members-access-period-options').hide();
            }
        });
    });
    </script>
    <?php
}

/**
 * Render product access meta box
 *
 * @param WP_Post $post The post object.
 */
function render_product_access_meta_box($post) {
    // Get roles
    $roles = get_editable_roles();
    
    // Get current values and sanitize
    $membership_roles = get_product_meta($post->ID, '_membership_roles', []);
    
    // Make sure it's an array and handle legacy format
    if (!is_array($membership_roles)) {
        // Handle backward compatibility for single role or invalid value
        if (is_string($membership_roles) && !empty($membership_roles)) {
            $membership_roles = [$membership_roles];
        } else {
            $membership_roles = [];
        }
    }
    
    // Double check for a legacy role if the array is empty
    if (empty($membership_roles)) {
        $legacy_role = get_product_meta($post->ID, '_membership_role', '');
        if (!empty($legacy_role) && !is_array($legacy_role)) {
            $membership_roles = [$legacy_role];
        }
    }
    
    $redirect_url = get_product_meta($post->ID, '_redirect_url', '');
    $redirect_url = is_array($redirect_url) ? '' : $redirect_url;
    
    ?>
    <div class="members-roles-section">
        <p>
            <label><?php _e('Membership Roles:', 'members'); ?></label>
            <span class="description"><?php _e('Select the roles that will be assigned to users who purchase this membership.', 'members'); ?></span>
        </p>
        
        <div class="members-roles-list" style="margin-left: 15px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
            <?php foreach ($roles as $role_id => $role) : ?>
                <p>
                    <label>
                        <input type="checkbox" 
                               name="members_product_meta[_membership_roles][]" 
                               value="<?php echo esc_attr($role_id); ?>" 
                               <?php checked(in_array($role_id, $membership_roles)); ?>>
                        <?php echo esc_html(translate_user_role($role['name'])); ?>
                    </label>
                </p>
            <?php endforeach; ?>
        </div>
        <p class="description"><?php _e('Users will receive all selected roles upon purchase. If their subscription expires or is cancelled, these roles will be removed.', 'members'); ?></p>
    </div>
    
    <p>
        <label for="members-redirect-url"><?php _e('Thank You / Redirect URL:', 'members'); ?></label>
        <input type="url" name="members_product_meta[_redirect_url]" id="members-redirect-url" value="<?php echo esc_attr($redirect_url); ?>" class="large-text" />
        <span class="description"><?php _e('Where to send users after successful purchase.', 'members'); ?></span>
    </p>
    <?php
}

/**
 * Save product meta data
 *
 * @param int     $post_id The post ID.
 * @param WP_Post $post    The post object.
 * @param bool    $update  Whether this is an existing post being updated.
 */
function save_product_meta($post_id, $post, $update) {
    // Check if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Check nonce
    if (!isset($_POST['members_product_meta_nonce']) || !wp_verify_nonce($_POST['members_product_meta_nonce'], 'members_save_product_meta')) {
        return;
    }
    
    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Process meta data
    if (isset($_POST['members_product_meta'])) {
        $meta_data = $_POST['members_product_meta'];
        
        // Sanitize and save each field
        $fields = [
            '_membership_type' => 'sanitize_text_field',
            '_price' => 'sanitize_text_field',
            '_recurring' => 'intval',
            '_period' => 'absint',
            '_period_type' => 'sanitize_text_field',
            '_has_trial' => 'intval',
            '_trial_days' => 'absint',
            '_trial_price' => 'sanitize_text_field',
            '_has_access_period' => 'intval',
            '_access_period' => 'absint',
            '_access_period_type' => 'sanitize_text_field',
            '_redirect_url' => 'esc_url_raw',
        ];
        
        foreach ($fields as $key => $sanitize_callback) {
            $value = isset($meta_data[$key]) ? $meta_data[$key] : '';
            
            // Handle arrays properly
            if (is_array($value)) {
                // Skip arrays that shouldn't be here - they should be handled separately
                continue;
            }
            
            // Apply sanitization callback
            $sanitized_value = $sanitize_callback($value);
            
            if ($key === '_price' || $key === '_trial_price') {
                $sanitized_value = (float) $sanitized_value;
            }
            
            update_product_meta($post_id, $key, $sanitized_value);
        }
        
        // Handle membership roles (special case for array of values)
        if (isset($meta_data['_membership_roles'])) {
            // Ensure it's an array to avoid issues
            $membership_roles = is_array($meta_data['_membership_roles']) ? $meta_data['_membership_roles'] : [$meta_data['_membership_roles']];
            
            // Sanitize each role
            $roles = array_map('sanitize_text_field', $membership_roles);
            update_product_meta($post_id, '_membership_roles', $roles);
            
            // For backward compatibility, also save the first role in _membership_role
            $first_role = !empty($roles) ? $roles[0] : '';
            update_product_meta($post_id, '_membership_role', $first_role);
        } else {
            // If no roles selected, save empty arrays
            update_product_meta($post_id, '_membership_roles', []);
            update_product_meta($post_id, '_membership_role', '');
        }
    }
}

// get_product_meta function is now defined in functions-db.php

/**
 * Format subscription period
 *
 * @param int    $period      The period value
 * @param string $period_type The period type (day, week, month, year)
 * @return string Formatted period string
 */
function format_subscription_period($period, $period_type) {
    $period = intval($period);
    $period_options = array(
        'day' => _n('daily', 'every %d days', $period, 'members'),
        'week' => _n('weekly', 'every %d weeks', $period, 'members'),
        'month' => _n('monthly', 'every %d months', $period, 'members'),
        'year' => _n('yearly', 'every %d years', $period, 'members'),
    );
    
    $format = isset($period_options[$period_type]) ? $period_options[$period_type] : '';
    
    if (empty($format)) {
        return '';
    }
    
    if ($period === 1) {
        return $format; // Returns "daily", "weekly", etc.
    }
    
    return sprintf($format, $period); // Returns "every X days", "every X weeks", etc.
}

/**
 * Check if user has access to a product
 *
 * @param int $user_id    The user ID
 * @param int $product_id The product ID
 * @return bool True if user has access, false otherwise
 */
function user_has_access($user_id, $product_id) {
    // Check if user is logged in
    if (!$user_id) {
        return false;
    }
    
    // Check if user has an active subscription for this product
    global $wpdb;
    $table_name = $wpdb->prefix . 'members_subscriptions';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if ($table_exists) {
        $subscription = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d AND product_id = %d AND status = 'active'",
                $user_id,
                $product_id
            )
        );
        
        if ($subscription) {
            return true;
        }
    }
    
    // Check if user has the required role
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $product_roles = get_product_meta($product_id, '_membership_roles', []);
    
    if (empty($product_roles)) {
        return false;
    }
    
    foreach ($product_roles as $role) {
        if (in_array($role, (array) $user->roles)) {
            return true;
        }
    }
    
    return false;
}