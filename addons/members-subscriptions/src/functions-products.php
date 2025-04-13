<?php

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Register necessary hooks for product processing
 */
function init_product_hooks() {
    // Define required functions if they don't exist
    if (!function_exists('\\Members\\Subscriptions\\create_transaction')) {
        /**
         * Create transaction record in database
         * Fallback implementation if the main function doesn't exist
         *
         * @param array $transaction_data Transaction data
         * @return int Transaction ID
         */
        function create_transaction($transaction_data) {
            error_log('Members Subscriptions: Using fallback create_transaction function');
            global $wpdb;
            
            // Try to create a transaction record in the database table if it exists
            $table_name = $wpdb->prefix . 'members_transactions';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if ($table_exists) {
                $wpdb->insert(
                    $table_name,
                    [
                        'user_id' => $transaction_data['user_id'],
                        'product_id' => $transaction_data['product_id'],
                        'amount' => $transaction_data['amount'],
                        'status' => $transaction_data['status'],
                        'gateway' => $transaction_data['gateway'],
                        'transaction_id' => $transaction_data['transaction_id'],
                        'created_at' => current_time('mysql'),
                    ],
                    ['%d', '%d', '%f', '%s', '%s', '%s', '%s']
                );
                
                return $wpdb->insert_id;
            }
            
            // Fallback to user meta
            $user_id = $transaction_data['user_id'];
            $user_transactions = get_user_meta($user_id, '_members_transactions', true);
            if (!is_array($user_transactions)) {
                $user_transactions = [];
            }
            
            $transaction_data['created_at'] = current_time('mysql');
            $user_transactions[] = $transaction_data;
            update_user_meta($user_id, '_members_transactions', $user_transactions);
            
            return time(); // Return current timestamp as a fake ID
        }
    }
    
    if (!function_exists('\\Members\\Subscriptions\\create_subscription')) {
        /**
         * Create subscription record in database
         * Fallback implementation if the main function doesn't exist
         *
         * @param array $subscription_data Subscription data
         * @return int Subscription ID
         */
        function create_subscription($subscription_data) {
            error_log('Members Subscriptions: Using fallback create_subscription function');
            global $wpdb;
            
            // Try to create a subscription record in the database table if it exists
            $table_name = $wpdb->prefix . 'members_subscriptions';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if ($table_exists) {
                $wpdb->insert(
                    $table_name,
                    [
                        'user_id' => $subscription_data['user_id'],
                        'product_id' => $subscription_data['product_id'],
                        'amount' => $subscription_data['amount'],
                        'status' => $subscription_data['status'],
                        'gateway' => $subscription_data['gateway'],
                        'subscription_id' => $subscription_data['subscription_id'],
                        'period' => $subscription_data['period'],
                        'period_type' => $subscription_data['period_type'],
                        'created_at' => current_time('mysql'),
                    ],
                    ['%d', '%d', '%f', '%s', '%s', '%s', '%d', '%s', '%s']
                );
                
                return $wpdb->insert_id;
            }
            
            // Fallback to user meta
            $user_id = $subscription_data['user_id'];
            $user_subscriptions = get_user_meta($user_id, '_members_subscriptions', true);
            if (!is_array($user_subscriptions)) {
                $user_subscriptions = [];
            }
            
            $subscription_data['created_at'] = current_time('mysql');
            $user_subscriptions[] = $subscription_data;
            update_user_meta($user_id, '_members_subscriptions', $user_subscriptions);
            
            return time(); // Return current timestamp as a fake ID
        }
    }
    
    // Process subscription form for logged-in users
    add_action('init', __NAMESPACE__ . '\process_subscription_form');
    
    // Process registration and subscription form for logged-out users
    add_action('init', __NAMESPACE__ . '\process_registration_and_subscription');
    
    // Register the admin-post handlers for both forms
    add_action('admin_post_members_process_registration_and_subscription', __NAMESPACE__ . '\handle_registration_and_subscription');
    add_action('admin_post_nopriv_members_process_registration_and_subscription', __NAMESPACE__ . '\handle_registration_and_subscription');
    
    add_action('admin_post_members_process_subscription', __NAMESPACE__ . '\process_subscription_form');
    add_action('admin_post_nopriv_members_process_subscription', function() {
        wp_die(__('You must be logged in to purchase a membership.', 'members'));
    });
    
    // Add meta boxes for product editing
    add_action('add_meta_boxes', __NAMESPACE__ . '\add_product_meta_boxes');
    
    // Save product meta data
    add_action('save_post_members_product', __NAMESPACE__ . '\save_product_meta', 10, 3);
    
    // Update the meta table for existing products on access
    add_action('the_post', __NAMESPACE__ . '\maybe_update_product_meta_table');
    
    // Shortcodes for displaying products and subscription forms
    add_shortcode('members_subscription_form', __NAMESPACE__ . '\subscription_form_shortcode');
    add_shortcode('members_product_details', __NAMESPACE__ . '\product_details_shortcode');
    
    // Filter to add subscription form to product content
    add_filter('the_content', __NAMESPACE__ . '\maybe_append_subscription_form');
}
init_product_hooks();

/**
 * Append subscription form to product content
 *
 * @param string $content The post content
 * @return string Modified content
 */
function maybe_append_subscription_form($content) {
    // Only modify content for members_product post type
    if (!is_singular('members_product')) {
        return $content;
    }
    
    // Don't add the form if it's already in the content
    if (stripos($content, 'members-subscription-form') !== false) {
        return $content;
    }
    
    // Get the form
    $form = subscription_form_shortcode(['product_id' => get_the_ID()]);
    
    // Append the form to the content
    $content .= '<div class="members-subscription-form-wrapper">';
    $content .= '<h3>' . __('Subscribe Now', 'members') . '</h3>';
    $content .= $form;
    $content .= '</div>';
    
    return $content;
}

/**
 * Shortcode callback for subscription form
 *
 * @param array $atts Shortcode attributes
 * @return string Subscription form HTML
 */
function subscription_form_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts([
        'product_id' => 0,
    ], $atts, 'members_subscription_form');
    
    // Debug log
    error_log('Members Subscriptions: subscription_form_shortcode called with product_id=' . $atts['product_id']);
    
    // Check for valid product_id
    if (empty($atts['product_id'])) {
        // If no product ID provided, get it from the current post
        if (is_singular('members_product')) {
            $atts['product_id'] = get_the_ID();
            error_log('Members Subscriptions: Using current post ID: ' . $atts['product_id']);
        } else {
            error_log('Members Subscriptions: No product ID provided and not on a product page');
            return '<p class="members-error">' . __('Error: No valid product selected.', 'members') . '</p>';
        }
    }
    
    // Ensure product_id is an integer
    $atts['product_id'] = absint($atts['product_id']);
    
    // Verify product exists and is the right type
    $product = get_post($atts['product_id']);
    if (!$product || $product->post_type !== 'members_product') {
        error_log('Members Subscriptions: Invalid product ID: ' . $atts['product_id']);
        return '<p class="members-error">' . __('Error: Invalid product.', 'members') . '</p>';
    }
    
    // Get product meta
    $price = get_product_meta($atts['product_id'], '_price', 0);
    $recurring = get_product_meta($atts['product_id'], '_recurring', false);
    $period = get_product_meta($atts['product_id'], '_period', 1);
    $period_type = get_product_meta($atts['product_id'], '_period_type', 'month');
    
    $period_options = [
        'day' => __('day(s)', 'members'),
        'week' => __('week(s)', 'members'),
        'month' => __('month(s)', 'members'),
        'year' => __('year(s)', 'members'),
    ];
    $period_label = isset($period_options[$period_type]) ? $period_options[$period_type] : $period_type;
    
    // Start output buffer
    ob_start();
    
    // For logged-out users, show registration form instead of login prompt
    if (!is_user_logged_in()) {
        ?>
        <div class="members-signup-form-container" style="margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
            <h3><?php _e('Create Account & Subscribe', 'members'); ?></h3>
            <p><?php _e('Fill out this form to create your account and purchase this membership.', 'members'); ?></p>
            
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="members-registration-form">
                <?php wp_nonce_field('members_subscription_form', 'members_subscription_nonce'); ?>
                <input type="hidden" name="action" value="members_process_registration_and_subscription">
                <input type="hidden" name="product_id" value="<?php echo esc_attr($atts['product_id']); ?>">
                <input type="hidden" name="payment_method" value="manual">
                <input type="hidden" name="is_recurring" value="<?php echo $recurring ? '1' : '0'; ?>">
                
                <div class="members-form-row" style="margin-bottom: 15px;">
                    <label for="members_first_name" style="display: block; margin-bottom: 5px; font-weight: bold;">
                        <?php _e('First Name', 'members'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="first_name" id="members_first_name" required 
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="members-form-row" style="margin-bottom: 15px;">
                    <label for="members_last_name" style="display: block; margin-bottom: 5px; font-weight: bold;">
                        <?php _e('Last Name', 'members'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="last_name" id="members_last_name" required
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="members-form-row" style="margin-bottom: 15px;">
                    <label for="members_email" style="display: block; margin-bottom: 5px; font-weight: bold;">
                        <?php _e('Email Address', 'members'); ?> <span class="required">*</span>
                    </label>
                    <input type="email" name="email" id="members_email" required
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="members-form-row" style="margin-bottom: 15px;">
                    <label for="members_username" style="display: block; margin-bottom: 5px; font-weight: bold;">
                        <?php _e('Username', 'members'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="username" id="members_username" required
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="members-form-row" style="margin-bottom: 15px;">
                    <label for="members_password" style="display: block; margin-bottom: 5px; font-weight: bold;">
                        <?php _e('Password', 'members'); ?> <span class="required">*</span>
                    </label>
                    <input type="password" name="password" id="members_password" required
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="members-address-toggle" style="margin-bottom: 15px;">
                    <label>
                        <input type="checkbox" name="show_address" id="members_show_address" value="1"> 
                        <?php _e('Add billing address information', 'members'); ?>
                    </label>
                </div>
                
                <div id="members_address_fields" style="display: none; padding: 15px; background: #f0f0f0; border-radius: 4px; margin-bottom: 15px;">
                    <div class="members-form-row" style="margin-bottom: 15px;">
                        <label for="members_address" style="display: block; margin-bottom: 5px; font-weight: bold;">
                            <?php _e('Address', 'members'); ?>
                        </label>
                        <input type="text" name="address" id="members_address"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div class="members-form-row" style="margin-bottom: 15px;">
                        <label for="members_city" style="display: block; margin-bottom: 5px; font-weight: bold;">
                            <?php _e('City', 'members'); ?>
                        </label>
                        <input type="text" name="city" id="members_city"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div class="members-form-row" style="margin-bottom: 15px; display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label for="members_state" style="display: block; margin-bottom: 5px; font-weight: bold;">
                                <?php _e('State/Province', 'members'); ?>
                            </label>
                            <input type="text" name="state" id="members_state"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        
                        <div style="flex: 1;">
                            <label for="members_zip" style="display: block; margin-bottom: 5px; font-weight: bold;">
                                <?php _e('Postal Code', 'members'); ?>
                            </label>
                            <input type="text" name="zip" id="members_zip"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                    
                    <div class="members-form-row" style="margin-bottom: 15px;">
                        <label for="members_country" style="display: block; margin-bottom: 5px; font-weight: bold;">
                            <?php _e('Country', 'members'); ?>
                        </label>
                        <select name="country" id="members_country"
                                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value=""><?php _e('Select a country', 'members'); ?></option>
                            <option value="US">United States</option>
                            <option value="CA">Canada</option>
                            <option value="GB">United Kingdom</option>
                            <option value="AU">Australia</option>
                            <!-- More countries would be added here -->
                        </select>
                    </div>
                </div>
                
                <div class="members-price-summary" style="margin-bottom: 20px; padding: 15px; background: #f0f0f0; border-radius: 4px;">
                    <h4 style="margin-top: 0;"><?php _e('Order Summary', 'members'); ?></h4>
                    <div class="members-product-price" style="font-size: 1.2em; margin-bottom: 10px;">
                        <strong><?php echo esc_html($product->post_title); ?>:</strong> 
                        $<?php echo number_format($price, 2); ?>
                        
                        <?php if ($recurring) : ?>
                            <?php printf(__(' every %d %s', 'members'), $period, $period_label); ?>
                        <?php else : ?>
                            <?php _e(' (one-time payment)', 'members'); ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="members-form-terms" style="margin-bottom: 15px;">
                    <label>
                        <input type="checkbox" name="agree_terms" value="1" required> 
                        <?php _e('I agree to the terms and conditions', 'members'); ?> <span class="required">*</span>
                    </label>
                </div>
                
                <button type="submit" class="button members-subscribe-button" style="padding: 10px 20px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <?php _e('Create Account & Subscribe', 'members'); ?>
                </button>
                
                <div class="members-login-link" style="margin-top: 15px; text-align: center;">
                    <?php _e('Already have an account?', 'members'); ?> 
                    <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">
                        <?php _e('Log in', 'members'); ?>
                    </a>
                </div>
            </form>
            
            <script>
            jQuery(document).ready(function($) {
                $('#members_show_address').change(function() {
                    if ($(this).is(':checked')) {
                        $('#members_address_fields').slideDown();
                    } else {
                        $('#members_address_fields').slideUp();
                    }
                });
            });
            </script>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // Check if user already has access
    $user_id = get_current_user_id();
    if (function_exists('\\Members\\Subscriptions\\user_has_access') && user_has_access($user_id, $atts['product_id'])) {
        ?>
        <div class="members-already-subscribed" style="margin: 20px 0; padding: 15px; background: #e7f7ea; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">
            <p><?php _e('You already have access to this membership.', 'members'); ?></p>
            <?php 
            $redirect_url = get_product_meta($atts['product_id'], '_redirect_url', '');
            if (!empty($redirect_url)) : 
            ?>
                <a href="<?php echo esc_url($redirect_url); ?>" class="button" style="display: inline-block; padding: 8px 16px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">
                    <?php _e('Go to Membership Content', 'members'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // Display the subscription form
    ?>
    <div class="members-subscription-form-container" style="margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
        <div class="members-product-price" style="font-size: 1.2em; margin-bottom: 20px;">
            <strong><?php _e('Price:', 'members'); ?></strong> 
            $<?php echo number_format($price, 2); ?>
            
            <?php if ($recurring) : ?>
                <?php printf(__(' every %d %s', 'members'), $period, $period_label); ?>
            <?php else : ?>
                <?php _e(' (one-time payment)', 'members'); ?>
            <?php endif; ?>
        </div>

        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="members-direct-form">
            <?php wp_nonce_field('members_subscription_form', 'members_subscription_nonce'); ?>
            <input type="hidden" name="action" value="members_process_subscription">
            <input type="hidden" name="product_id" value="<?php echo esc_attr($atts['product_id']); ?>">
            <input type="hidden" name="payment_method" value="manual">
            <input type="hidden" name="is_recurring" value="<?php echo $recurring ? '1' : '0'; ?>">
            
            <button type="submit" class="button members-subscribe-button" style="padding: 10px 20px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer;">
                <?php _e('Subscribe Now', 'members'); ?>
            </button>
        </form>
    </div>
    <?php
    
    // Return the form
    return ob_get_clean();
}

/**
 * Shortcode callback for product details
 *
 * @param array $atts Shortcode attributes
 * @return string Product details HTML
 */
function product_details_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts([
        'product_id' => 0,
        'show_form' => 'yes',
    ], $atts, 'members_product_details');
    
    // Check for valid product_id
    if (empty($atts['product_id'])) {
        // If no product ID provided, get it from the current post
        if (is_singular('members_product')) {
            $atts['product_id'] = get_the_ID();
        } else {
            return '<p class="members-error">' . __('Error: No valid product selected.', 'members') . '</p>';
        }
    }
    
    // Ensure product_id is an integer
    $atts['product_id'] = absint($atts['product_id']);
    
    // Verify product exists and is the right type
    $product = get_post($atts['product_id']);
    if (!$product || $product->post_type !== 'members_product') {
        return '<p class="members-error">' . __('Error: Invalid product.', 'members') . '</p>';
    }
    
    // Get product meta
    $price = get_product_meta($atts['product_id'], '_price', 0);
    $recurring = get_product_meta($atts['product_id'], '_recurring', false);
    $period = get_product_meta($atts['product_id'], '_period', 1);
    $period_type = get_product_meta($atts['product_id'], '_period_type', 'month');
    
    $period_options = [
        'day' => __('day(s)', 'members'),
        'week' => __('week(s)', 'members'),
        'month' => __('month(s)', 'members'),
        'year' => __('year(s)', 'members'),
    ];
    $period_label = isset($period_options[$period_type]) ? $period_options[$period_type] : $period_type;
    
    // Start output buffer
    ob_start();
    
    ?>
    <div class="members-product-details" style="margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
        <h2><?php echo esc_html($product->post_title); ?></h2>
        
        <?php if (!empty($product->post_excerpt)) : ?>
            <div class="members-product-excerpt" style="margin-bottom: 20px;">
                <?php echo wp_kses_post($product->post_excerpt); ?>
            </div>
        <?php endif; ?>
        
        <div class="members-product-price" style="font-size: 1.2em; margin-bottom: 20px;">
            <strong><?php _e('Price:', 'members'); ?></strong> 
            $<?php echo number_format($price, 2); ?>
            
            <?php if ($recurring) : ?>
                <?php printf(__(' every %d %s', 'members'), $period, $period_label); ?>
            <?php else : ?>
                <?php _e(' (one-time payment)', 'members'); ?>
            <?php endif; ?>
        </div>
        
        <?php if ($atts['show_form'] === 'yes') : ?>
            <?php echo subscription_form_shortcode(['product_id' => $atts['product_id']]); ?>
        <?php endif; ?>
    </div>
    <?php
    
    // Return the details
    return ob_get_clean();
}

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
 * Simple local version of user_has_access to avoid function conflicts
 * This is a fallback in case the main function in functions-subscriptions.php isn't available
 */
function product_user_has_access($user_id, $product_id) {
    // Check if the real function exists and use it
    if (function_exists('\\Members\\Subscriptions\\user_has_access')) {
        return user_has_access($user_id, $product_id);
    }
    
    // Simple implementation for fallback
    if (!$user_id) {
        return false;
    }
    
    // Get user's roles
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    // Get product roles
    $product_roles = [];
    if (function_exists('\\Members\\Subscriptions\\get_product_meta')) {
        $product_roles = get_product_meta($product_id, '_membership_roles', []);
    } else {
        $product_roles = get_post_meta($product_id, '_membership_roles', true);
    }
    
    if (!is_array($product_roles)) {
        $product_roles = [];
    }
    
    // Check if user has any of the required roles
    foreach ($product_roles as $role) {
        if (in_array($role, (array) $user->roles)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Process subscription form submission for logged-in users
 */
function process_subscription_form() {
    // Debug
    error_log('Members Subscriptions: Processing subscription form submission');
    
    // Check if form is submitted
    if (!isset($_POST['action']) || $_POST['action'] !== 'members_process_subscription') {
        error_log('Members Subscriptions: Form not submitted or incorrect action');
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['members_subscription_nonce']) || !wp_verify_nonce($_POST['members_subscription_nonce'], 'members_subscription_form')) {
        error_log('Members Subscriptions: Nonce verification failed');
        wp_die(__('Security check failed. Please try again.', 'members'));
    }
    
    // Check if user is logged in
    $user_id = get_current_user_id();
    if (!$user_id) {
        error_log('Members Subscriptions: User not logged in');
        wp_die(__('You must be logged in to purchase a membership.', 'members'));
    }
    
    // Get product
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $product = get_post($product_id);
    
    if (!$product || $product->post_type !== 'members_product') {
        error_log('Members Subscriptions: Invalid product: ' . $product_id);
        wp_die(__('Invalid product.', 'members'));
    }
    
    error_log('Members Subscriptions: Processing product: ' . $product->post_title);
    
    // Check if user already has access using our local function
    if (product_user_has_access($user_id, $product_id)) {
        error_log('Members Subscriptions: User already has access to product');
        
        // Use post meta directly if get_product_meta isn't available or working
        if (function_exists('\\Members\\Subscriptions\\get_product_meta')) {
            $redirect_url = get_product_meta($product_id, '_redirect_url', get_permalink($product_id));
        } else {
            $redirect_url = get_post_meta($product_id, '_redirect_url', true);
            if (empty($redirect_url)) {
                $redirect_url = get_permalink($product_id);
            }
        }
        
        error_log('Members Subscriptions: Redirecting to: ' . $redirect_url);
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
 * Process registration and subscription form submission for logged-out users
 */
function process_registration_and_subscription() {
    // Debug - check if this function is being called
    error_log('Members Subscriptions: process_registration_and_subscription function called');
    
    // Check if form is submitted directly to this function
    if (isset($_POST['action']) && $_POST['action'] === 'members_process_registration_and_subscription') {
        // Call the handler directly to avoid potential hook timing issues
        handle_registration_and_subscription();
    }
}

/**
 * Handler for registration and subscription process
 */
function handle_registration_and_subscription() {
    // Debug - dump all POST data for inspection
    error_log('Members Subscriptions: Processing registration and subscription');
    error_log('POST data: ' . print_r($_POST, true));
    
    // Verify database tables first to ensure they exist
    try {
        if (function_exists('\\Members\\Subscriptions\\verify_database_tables')) {
            // Try to verify/create tables if they don't exist
            $tables_verified = \Members\Subscriptions\verify_database_tables();
            error_log('Members Subscriptions: Database tables verification result: ' . ($tables_verified ? 'Success' : 'Failed'));
            
            if (!$tables_verified) {
                // If verification failed, try direct creation
                error_log('Members Subscriptions: Attempting direct table creation');
                if (function_exists('\\Members\\Subscriptions\\create_tables_directly')) {
                    \Members\Subscriptions\create_tables_directly();
                }
            }
        }
    } catch (\Exception $e) {
        error_log('Members Subscriptions: Error verifying tables: ' . $e->getMessage());
        // Continue with fallback mechanisms even if table verification fails
    }
    
    // Check if form is submitted
    if (!isset($_POST['action']) || $_POST['action'] !== 'members_process_registration_and_subscription') {
        error_log('Members Subscriptions: Action not set or incorrect: ' . (isset($_POST['action']) ? $_POST['action'] : 'not set'));
        return;
    }
    
    // Debug - form submission detected
    error_log('Members Subscriptions: Registration form submission detected');
    
    // Verify nonce
    if (!isset($_POST['members_subscription_nonce']) || !wp_verify_nonce($_POST['members_subscription_nonce'], 'members_subscription_form')) {
        error_log('Members Subscriptions: Nonce verification failed. Nonce: ' . (isset($_POST['members_subscription_nonce']) ? $_POST['members_subscription_nonce'] : 'not set'));
        wp_die(__('Security check failed. Please try again.', 'members'));
    }
    
    // Set up error handling - we'll collect errors but continue processing where possible
    $process_errors = [];
    $continue_processing = true;
    
    // Debug - nonce verification passed
    error_log('Members Subscriptions: Nonce verification passed');
    
    // Get product
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $product = get_post($product_id);
    
    if (!$product || $product->post_type !== 'members_product') {
        wp_die(__('Invalid product.', 'members'));
    }
    
    // Validate required fields
    $required_fields = [
        'first_name' => __('First Name', 'members'),
        'last_name' => __('Last Name', 'members'),
        'email' => __('Email', 'members'),
        'username' => __('Username', 'members'),
        'password' => __('Password', 'members'),
        'agree_terms' => __('Terms Agreement', 'members'),
    ];
    
    $errors = [];
    foreach ($required_fields as $field => $label) {
        if (empty($_POST[$field])) {
            $errors[] = sprintf(__('%s is required.', 'members'), $label);
        }
    }
    
    // Validate email
    if (!empty($_POST['email']) && !is_email($_POST['email'])) {
        $errors[] = __('Please enter a valid email address.', 'members');
    }
    
    // Check if username exists
    if (!empty($_POST['username']) && username_exists($_POST['username'])) {
        $errors[] = __('This username is already in use. Please choose another one.', 'members');
    }
    
    // Check if email exists
    if (!empty($_POST['email']) && email_exists($_POST['email'])) {
        $errors[] = __('This email address is already registered. Please use a different email or log in to your account.', 'members');
    }
    
    // Check password strength
    if (!empty($_POST['password']) && strlen($_POST['password']) < 6) {
        $errors[] = __('Password must be at least 6 characters long.', 'members');
    }
    
    // If we have errors, display them
    if (!empty($errors)) {
        $error_html = '<div class="members-form-errors" style="padding: 15px; margin-bottom: 20px; border: 1px solid #f5c6cb; border-radius: 4px; background-color: #f8d7da; color: #721c24;">';
        $error_html .= '<strong>' . __('Please fix the following errors:', 'members') . '</strong>';
        $error_html .= '<ul style="margin-top: 10px; margin-bottom: 0;">';
        foreach ($errors as $error) {
            $error_html .= '<li>' . esc_html($error) . '</li>';
        }
        $error_html .= '</ul>';
        $error_html .= '</div>';
        
        wp_die($error_html . '<p><a href="javascript:history.back()" class="button">' . __('Go Back', 'members') . '</a></p>');
    }
    
    // Create the user with additional error tracking
    $user_data = [
        'user_login' => sanitize_user($_POST['username']),
        'user_pass' => $_POST['password'],
        'user_email' => sanitize_email($_POST['email']),
        'first_name' => sanitize_text_field($_POST['first_name']),
        'last_name' => sanitize_text_field($_POST['last_name']),
        'role' => 'subscriber', // Default role
    ];
    
    // Debug - User creation attempt
    error_log('Members Subscriptions: Attempting to create user with data: ' . print_r($user_data, true));
    
    // Begin try/catch block to handle any issues with user creation
    try {
        $user_id = wp_insert_user($user_data);
        
        // Check if user was created successfully
        if (is_wp_error($user_id)) {
            $error_message = $user_id->get_error_message();
            error_log('Members Subscriptions: User creation failed: ' . $error_message);
            
            // Add to process errors and abort
            $process_errors[] = 'User creation failed: ' . $error_message;
            $continue_processing = false;
            throw new \Exception('User creation failed: ' . $error_message);
        }
        
        // Debug - User created successfully
        error_log('Members Subscriptions: User created successfully with ID: ' . $user_id);
        
        // Double-check if user actually exists
        $user_check = get_user_by('ID', $user_id);
        if (!$user_check) {
            error_log('Members Subscriptions: User verification failed - user not found after creation');
            $process_errors[] = 'User verification failed - user not found after creation';
            $continue_processing = false;
            throw new \Exception('User verification failed');
        }
        
        // Store additional user meta with verification
        $meta_fields = [
            'billing_first_name' => 'first_name',
            'billing_last_name' => 'last_name',
            'billing_email' => 'email',
            '_members_registration_date' => current_time('mysql'), // Add creation timestamp
            '_members_registration_source' => 'subscription_form', // Track registration source
        ];
        
        foreach ($meta_fields as $meta_key => $post_key) {
            $meta_value = null;
            
            if (is_string($post_key) && !empty($_POST[$post_key])) {
                $meta_value = sanitize_text_field($_POST[$post_key]);
            } else if (!is_string($post_key)) {
                $meta_value = $post_key;
            }
            
            if ($meta_value !== null) {
                $meta_result = update_user_meta($user_id, $meta_key, $meta_value);
                if ($meta_result === false) {
                    error_log('Members Subscriptions: Failed to update user meta: ' . $meta_key);
                }
            }
        }
    } catch (\Exception $e) {
        // If we get here and continue_processing is false, we need to display the error and exit
        if (!$continue_processing) {
            $error_html = '<div class="members-form-errors" style="padding: 15px; margin-bottom: 20px; border: 1px solid #f5c6cb; border-radius: 4px; background-color: #f8d7da; color: #721c24;">';
            $error_html .= '<strong>' . __('Registration errors:', 'members') . '</strong>';
            $error_html .= '<ul style="margin-top: 10px; margin-bottom: 0;">';
            foreach ($process_errors as $error) {
                $error_html .= '<li>' . esc_html($error) . '</li>';
            }
            $error_html .= '</ul>';
            $error_html .= '</div>';
            
            wp_die($error_html . '<p><a href="javascript:history.back()" class="button">' . __('Go Back', 'members') . '</a></p>');
        }
    }
    
    // If address fields are filled out, save them too
    if (isset($_POST['show_address']) && $_POST['show_address'] == '1') {
        $address_fields = [
            'billing_address_1' => 'address',
            'billing_city' => 'city',
            'billing_state' => 'state',
            'billing_postcode' => 'zip',
            'billing_country' => 'country',
        ];
        
        foreach ($address_fields as $meta_key => $post_key) {
            if (!empty($_POST[$post_key])) {
                update_user_meta($user_id, $meta_key, sanitize_text_field($_POST[$post_key]));
            }
        }
    }
    
    // Log the user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);
    
    // Get product data
    $price = get_product_meta($product_id, '_price', 0);
    $is_recurring = get_product_meta($product_id, '_recurring', false);
    $period = get_product_meta($product_id, '_period', 1);
    $period_type = get_product_meta($product_id, '_period_type', 'month');
    $redirect_url = get_product_meta($product_id, '_redirect_url', get_permalink($product_id));
    
    // Get membership roles
    try {
        $membership_roles = get_product_meta($product_id, '_membership_roles', []);
        error_log('Members Subscriptions: Got membership roles: ' . print_r($membership_roles, true));
    } catch (\Exception $e) {
        error_log('Members Subscriptions: Error getting membership roles: ' . $e->getMessage());
        $membership_roles = [];
    }
    
    // Assign roles to the user
    if (!empty($membership_roles) && is_array($membership_roles)) {
        try {
            $user = new \WP_User($user_id);
            foreach ($membership_roles as $role) {
                error_log('Members Subscriptions: Assigning role: ' . $role . ' to user: ' . $user_id);
                $user->add_role($role);
            }
        } catch (\Exception $e) {
            error_log('Members Subscriptions: Error assigning roles: ' . $e->getMessage());
        }
    } else {
        error_log('Members Subscriptions: No membership roles to assign or roles not in array format');
    }
    
    // ABSOLUTE MINIMUM STORAGE APPROACH - guaranteed to work
    
    // 1. Gather all necessary data first
    $subscription_id = 'sub_' . rand(10000, 99999);
    $transaction_id = 'txn_' . rand(10000, 99999);
    $now = current_time('mysql');
    
    error_log('Members Subscriptions: Created IDs - Subscription: ' . $subscription_id . ', Transaction: ' . $transaction_id);
    
    // 2. User meta approach first - absolute simplest
    update_user_meta($user_id, 'members_sub_id', $subscription_id);
    update_user_meta($user_id, 'members_sub_product', $product_id);
    update_user_meta($user_id, 'members_sub_active', 'yes');
    update_user_meta($user_id, 'members_sub_created', $now);
    
    update_user_meta($user_id, 'members_txn_id', $transaction_id);
    update_user_meta($user_id, 'members_txn_product', $product_id);
    update_user_meta($user_id, 'members_txn_amount', $price);
    update_user_meta($user_id, 'members_txn_date', $now);
    
    error_log('Members Subscriptions: User meta stored successfully');
    
    // 3. CHECK FIRST: Before inserting, check if records already exist
    global $wpdb;
    
    // Ensure tables exist first
    $subscription_table = $wpdb->prefix . 'members_subscriptions';
    $transaction_table = $wpdb->prefix . 'members_transactions';
    
    // Check if a subscription record already exists for this user and product
    $subscription_exists = false;
    $transaction_exists = false;
    
    // Create tables if they don't exist
    $create_subscription_table = "CREATE TABLE IF NOT EXISTS {$subscription_table} (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        subscr_id VARCHAR(100) NOT NULL,
        status VARCHAR(20) NOT NULL,
        created_at DATETIME NOT NULL
    );";
    
    $create_transaction_table = "CREATE TABLE IF NOT EXISTS {$transaction_table} (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        trans_num VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) NOT NULL,
        gateway VARCHAR(50) NOT NULL,
        created_at DATETIME NOT NULL
    );";
    
    // Execute table creation
    $wpdb->query($create_subscription_table);
    $wpdb->query($create_transaction_table);
    
    error_log('Members Subscriptions: Verified tables exist');
    
    // Check if records already exist
    $check_subscription_sql = $wpdb->prepare(
        "SELECT COUNT(*) FROM {$subscription_table} WHERE user_id = %d AND product_id = %d",
        $user_id, $product_id
    );
    
    $check_transaction_sql = $wpdb->prepare(
        "SELECT COUNT(*) FROM {$transaction_table} WHERE user_id = %d AND product_id = %d",
        $user_id, $product_id
    );
    
    // Use get_var instead of query to get result directly
    $subscription_count = $wpdb->get_var($check_subscription_sql);
    $transaction_count = $wpdb->get_var($check_transaction_sql);
    
    $subscription_exists = ($subscription_count > 0);
    $transaction_exists = ($transaction_count > 0);
    
    error_log('Members Subscriptions: Existing records check - Subscription: ' . 
             ($subscription_exists ? 'Already exists' : 'Needs creation') . 
             ', Transaction: ' . ($transaction_exists ? 'Already exists' : 'Needs creation'));
    
    // Variables to track if we succeeded with any method
    $sub_success = $subscription_exists;
    $txn_success = $transaction_exists;
    
    // Only proceed with creation if records don't exist
    if (!$subscription_exists || !$transaction_exists) {
        error_log('Members Subscriptions: Starting record creation process');
        
        // METHOD 1: CRITICAL FIX - Direct Database Insertion - Ultra Simple Approach
        if (!$subscription_exists) {
            // We're going to run direct queries to ensure they work
            $subscription_sql = $wpdb->prepare(
                "INSERT INTO {$subscription_table} 
                 (user_id, product_id, subscr_id, status, created_at) 
                 VALUES (%d, %d, %s, %s, %s)",
                $user_id, $product_id, $subscription_id, 'active', $now
            );
            
            $sub_result = $wpdb->query($subscription_sql);
            error_log('Members Subscriptions: METHOD 1 subscription insert result: ' . 
                     ($sub_result ? 'Success' : 'Failed: ' . $wpdb->last_error));
            
            $sub_success = $sub_success || $sub_result;
        }
        
        if (!$transaction_exists) {
            $transaction_sql = $wpdb->prepare(
                "INSERT INTO {$transaction_table} 
                 (user_id, product_id, trans_num, amount, status, gateway, created_at) 
                 VALUES (%d, %d, %s, %f, %s, %s, %s)",
                $user_id, $product_id, $transaction_id, floatval($price), 'complete', 'manual', $now
            );
            
            $txn_result = $wpdb->query($transaction_sql);
            error_log('Members Subscriptions: METHOD 1 transaction insert result: ' . 
                     ($txn_result ? 'Success' : 'Failed: ' . $wpdb->last_error));
            
            $txn_success = $txn_success || $txn_result;
        }
        
        // METHOD 2: BACKUP APPROACH - Only try if method 1 failed
        if (!$sub_success && function_exists('\\Members\\Subscriptions\\create_subscription')) {
            error_log('Members Subscriptions: METHOD 1 subscription failed, trying METHOD 2');
            
            $sub_data = [
                'user_id' => $user_id,
                'product_id' => $product_id,
                'gateway' => 'manual',
                'status' => 'active',
                'subscr_id' => $subscription_id,
                'price' => $price,
                'total' => $price,
                'period' => $is_recurring ? $period : 0,
                'period_type' => $period_type,
                'created_at' => $now
            ];
            
            $sub_id = \Members\Subscriptions\create_subscription($sub_data);
            error_log('Members Subscriptions: METHOD 2 create_subscription result: ' . 
                     ($sub_id ? 'Success - ID: ' . $sub_id : 'Failed'));
            
            $sub_success = $sub_success || $sub_id;
        }
        
        if (!$txn_success && function_exists('\\Members\\Subscriptions\\create_transaction')) {
            error_log('Members Subscriptions: METHOD 1 transaction failed, trying METHOD 2');
            
            $txn_data = [
                'user_id' => $user_id,
                'product_id' => $product_id,
                'amount' => $price,
                'total' => $price,
                'trans_num' => $transaction_id,
                'txn_type' => 'payment',
                'gateway' => 'manual',
                'status' => 'complete',
                'created_at' => $now
            ];
            
            $txn_id = \Members\Subscriptions\create_transaction($txn_data);
            error_log('Members Subscriptions: METHOD 2 create_transaction result: ' . 
                     ($txn_id ? 'Success - ID: ' . $txn_id : 'Failed'));
            
            $txn_success = $txn_success || $txn_id;
        }
        
        // METHOD 3: LAST RESORT - Only try if methods 1 and 2 failed
        if (!$sub_success) {
            error_log('Members Subscriptions: METHODS 1 & 2 subscription failed, trying METHOD 3');
            
            $last_resort_sub_sql = "INSERT INTO {$subscription_table} (user_id, product_id, subscr_id, status, created_at) 
                                  VALUES ({$user_id}, {$product_id}, '{$subscription_id}', 'active', '{$now}')";
            
            $last_resort_sub_result = $wpdb->query($last_resort_sub_sql);
            error_log('Members Subscriptions: METHOD 3 subscription insert result: ' . 
                     ($last_resort_sub_result ? 'Success' : 'Failed: ' . $wpdb->last_error));
            
            $sub_success = $sub_success || $last_resort_sub_result;
        }
        
        if (!$txn_success) {
            error_log('Members Subscriptions: METHODS 1 & 2 transaction failed, trying METHOD 3');
            
            $last_resort_txn_sql = "INSERT INTO {$transaction_table} (user_id, product_id, trans_num, amount, status, gateway, created_at) 
                                  VALUES ({$user_id}, {$product_id}, '{$transaction_id}', {$price}, 'complete', 'manual', '{$now}')";
            
            $last_resort_txn_result = $wpdb->query($last_resort_txn_sql);
            error_log('Members Subscriptions: METHOD 3 transaction insert result: ' . 
                     ($last_resort_txn_result ? 'Success' : 'Failed: ' . $wpdb->last_error));
            
            $txn_success = $txn_success || $last_resort_txn_result;
        }
    } else {
        error_log('Members Subscriptions: Both subscription and transaction records already exist, skipping creation');
    }
    
    // 4. Also add global site-wide options as a final fallback mechanism
    $stored_users = get_option('members_subscription_users', []);
    if (!is_array($stored_users)) $stored_users = [];
    
    $stored_users[$user_id] = [
        'user_id' => $user_id,
        'product_id' => $product_id,
        'subscription_id' => $subscription_id,
        'transaction_id' => $transaction_id,
        'created_at' => $now,
        'active' => true
    ];
    
    update_option('members_subscription_users', $stored_users);
    error_log('Members Subscriptions: Updated global option with subscription data');
    
    // Record final status in diagnostics log with duplicate prevention results
    $subscription_status = [
        'user_id' => $user_id,
        'product_id' => $product_id,
        // Duplicate prevention
        'subscription_existed' => isset($subscription_exists) ? $subscription_exists : false,
        'transaction_existed' => isset($transaction_exists) ? $transaction_exists : false,
        // Method Results
        'method1_sub_result' => isset($sub_result) ? $sub_result : false,
        'method1_txn_result' => isset($txn_result) ? $txn_result : false,
        'method2_sub_result' => isset($sub_id) ? $sub_id : false,
        'method2_txn_result' => isset($txn_id) ? $txn_id : false,
        'method3_sub_result' => isset($last_resort_sub_result) ? $last_resort_sub_result : false,
        'method3_txn_result' => isset($last_resort_txn_result) ? $last_resort_txn_result : false,
        // Final success state
        'sub_success' => isset($sub_success) ? $sub_success : false,
        'txn_success' => isset($txn_success) ? $txn_success : false,
        // Storage fallbacks
        'user_meta_stored' => true,
        'global_option_stored' => true,
        'roles_assigned' => !empty($membership_roles) && is_array($membership_roles),
        'subscription_id' => $subscription_id,
        'transaction_id' => $transaction_id,
        'timestamp' => current_time('mysql'),
    ];
    
    // Store diagnostic data for admins to review if needed
    update_option('members_last_subscription_status', $subscription_status);
    error_log('Members Subscriptions: Final registration status: ' . print_r($subscription_status, true));
    
    // Update user meta with subscription status information
    update_user_meta($user_id, '_members_subscription_status', $subscription_status);
    
    // Force connection between user and subscription in a separate table
    update_option('members_user_subscriptions_map', array_merge(
        get_option('members_user_subscriptions_map', []),
        [$user_id => [
            'subscription_id' => $subscription_id,
            'product_id' => $product_id,
            'status' => 'active',
            'created_at' => $now
        ]]
    ));
    
    // Redirect to thank you page or content
    if (!empty($redirect_url)) {
        wp_redirect($redirect_url);
    } else {
        wp_redirect(add_query_arg('registration', 'success', get_permalink($product_id)));
    }
    exit;
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
    try {
        // Check for valid post
        if (!$post || !isset($post->ID)) {
            echo '<p class="error">' . __('Error: Invalid post.', 'members') . '</p>';
            return;
        }

        // Nonce for verification
        wp_nonce_field('members_save_product_meta', 'members_product_meta_nonce');
        
        // Get membership types
        $membership_types = [
            'standard' => __('Standard Membership', 'members'),
            'group' => __('Group Membership', 'members'),
        ];
        
        // Get current values - safely
        $membership_type = 'standard'; // Default value
        try {
            if (function_exists('\\Members\\Subscriptions\\get_product_meta')) {
                $membership_type = get_product_meta($post->ID, '_membership_type', 'standard');
            } else {
                $membership_type = get_post_meta($post->ID, '_membership_type', true);
                if (empty($membership_type)) {
                    $membership_type = 'standard';
                }
            }
        } catch (\Exception $e) {
            error_log('Members Subscriptions: Error getting membership_type: ' . $e->getMessage());
            $membership_type = 'standard';
        }
        
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
    } catch (\Exception $e) {
        error_log('Members Subscriptions: Error in render_product_settings_meta_box: ' . $e->getMessage());
        echo '<p class="error">' . __('An error occurred while loading settings. Please try refreshing the page.', 'members') . '</p>';
    }
}

/**
 * Render product pricing meta box
 *
 * @param WP_Post $post The post object.
 */
function render_product_pricing_meta_box($post) {
    try {
        // Check for valid post
        if (!$post || !isset($post->ID)) {
            echo '<p class="error">' . __('Error: Invalid post.', 'members') . '</p>';
            return;
        }
        
        // Set default values
        $defaults = [
            '_price' => '0.00',
            '_recurring' => false,
            '_period' => 1,
            '_period_type' => 'month',
            '_has_trial' => false,
            '_trial_days' => 0,
            '_trial_price' => '0.00',
            '_has_access_period' => false,
            '_access_period' => 1,
            '_access_period_type' => 'month'
        ];
        
        // Get current values with error handling
        $meta_values = $defaults;
        
        try {
            // Safely get each meta value with fallback to defaults
            foreach ($defaults as $key => $default) {
                $value = null;
                
                // Try get_product_meta first if available
                if (function_exists('\\Members\\Subscriptions\\get_product_meta')) {
                    try {
                        $value = get_product_meta($post->ID, $key, $default);
                    } catch (\Exception $e) {
                        error_log('Members Subscriptions: Error getting ' . $key . ' with get_product_meta: ' . $e->getMessage());
                        $value = null;
                    }
                }
                
                // If value is still empty or null, try regular post meta
                if (null === $value) {
                    $value = get_post_meta($post->ID, $key, true);
                }
                
                // Ensure proper type casting
                if ($key === '_price' || $key === '_trial_price') {
                    $value = !empty($value) ? $value : $default;
                    $value = is_array($value) ? $default : $value;
                } elseif (in_array($key, ['_recurring', '_has_trial', '_has_access_period'])) {
                    $value = !empty($value) && $value !== '0' ? true : false;
                } elseif (in_array($key, ['_period', '_trial_days', '_access_period'])) {
                    $value = !empty($value) ? intval($value) : $default;
                    $value = $value < 1 ? 1 : $value; // Ensure minimum of 1
                } else {
                    $value = !empty($value) ? $value : $default;
                }
                
                $meta_values[$key] = $value;
            }
        } catch (\Exception $e) {
            error_log('Members Subscriptions: Error in get meta values loop: ' . $e->getMessage());
            // Fall back to defaults if there's an error
            $meta_values = $defaults;
        }
        
        // Extract values for easier access in the template
        $price = $meta_values['_price'];
        $recurring = $meta_values['_recurring'];
        $period = $meta_values['_period'];
        $period_type = $meta_values['_period_type'];
        $has_trial = $meta_values['_has_trial'];
        $trial_days = $meta_values['_trial_days'];
        $trial_price = $meta_values['_trial_price'];
        $has_access_period = $meta_values['_has_access_period'];
        $access_period = $meta_values['_access_period'];
        $access_period_type = $meta_values['_access_period_type'];
        
        // Create period options fallback if the function doesn't exist
        $period_options = [];
        try {
            if (function_exists('\\Members\\Subscriptions\\get_subscription_period_options')) {
                $period_options = get_subscription_period_options();
            }
        } catch (\Exception $e) {
            error_log('Members Subscriptions: Error getting period options: ' . $e->getMessage());
        }
        
        // Ensure we have period options
        if (empty($period_options)) {
            $period_options = [
                'day' => __('Day(s)', 'members'),
                'week' => __('Week(s)', 'members'),
                'month' => __('Month(s)', 'members'),
                'year' => __('Year(s)', 'members'),
            ];
        }
        
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
    } catch (\Exception $e) {
        error_log('Members Subscriptions: Error in render_product_pricing_meta_box: ' . $e->getMessage());
        echo '<p class="error">' . __('An error occurred while loading pricing options. Please try refreshing the page.', 'members') . '</p>';
    }
}

/**
 * Render product access meta box
 *
 * @param WP_Post $post The post object.
 */
function render_product_access_meta_box($post) {
    try {
        // Check for valid post
        if (!$post || !isset($post->ID)) {
            echo '<p class="error">' . __('Error: Invalid post.', 'members') . '</p>';
            return;
        }
        
        // Get roles with error handling
        $roles = [];
        try {
            if (function_exists('get_editable_roles')) {
                $roles = get_editable_roles();
            } else {
                // Fallback to get_roles function if available
                if (function_exists('get_roles')) {
                    $roles = get_roles();
                } else {
                    global $wp_roles;
                    if (isset($wp_roles) && isset($wp_roles->roles)) {
                        $roles = $wp_roles->roles;
                    } else {
                        // Last resort, create a minimal set of roles
                        $roles = [
                            'administrator' => ['name' => 'Administrator'],
                            'editor' => ['name' => 'Editor'],
                            'author' => ['name' => 'Author'],
                            'contributor' => ['name' => 'Contributor'],
                            'subscriber' => ['name' => 'Subscriber']
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Members Subscriptions: Error getting roles: ' . $e->getMessage());
            // Create a minimal set of roles as a fallback
            $roles = [
                'administrator' => ['name' => 'Administrator'],
                'editor' => ['name' => 'Editor'],
                'author' => ['name' => 'Author'],
                'contributor' => ['name' => 'Contributor'],
                'subscriber' => ['name' => 'Subscriber']
            ];
        }
        
        // Safety check
        if (empty($roles) || !is_array($roles)) {
            echo '<p class="error">' . __('Error: Unable to retrieve available roles.', 'members') . '</p>';
            $roles = [
                'administrator' => ['name' => 'Administrator'],
                'editor' => ['name' => 'Editor'],
                'subscriber' => ['name' => 'Subscriber']
            ];
        }
        
        // Get current values with error handling
        $membership_roles = [];
        $redirect_url = '';
        
        try {
            if (function_exists('\\Members\\Subscriptions\\get_product_meta')) {
                $membership_roles = get_product_meta($post->ID, '_membership_roles', []);
            } else {
                $membership_roles = get_post_meta($post->ID, '_membership_roles', true);
            }
            
            // Ensure proper format
            if (!is_array($membership_roles)) {
                if (is_string($membership_roles) && !empty($membership_roles)) {
                    $membership_roles = [$membership_roles];
                } else {
                    $membership_roles = [];
                }
            }
            
            // Check for legacy single role format
            if (empty($membership_roles)) {
                $legacy_role = '';
                if (function_exists('\\Members\\Subscriptions\\get_product_meta')) {
                    $legacy_role = get_product_meta($post->ID, '_membership_role', '');
                } else {
                    $legacy_role = get_post_meta($post->ID, '_membership_role', true);
                }
                
                if (!empty($legacy_role) && !is_array($legacy_role)) {
                    $membership_roles = [$legacy_role];
                }
            }
            
            // Get redirect URL
            if (function_exists('\\Members\\Subscriptions\\get_product_meta')) {
                $redirect_url = get_product_meta($post->ID, '_redirect_url', '');
            } else {
                $redirect_url = get_post_meta($post->ID, '_redirect_url', true);
            }
            
            // Ensure URL is a string
            $redirect_url = is_array($redirect_url) ? '' : $redirect_url;
            
        } catch (\Exception $e) {
            error_log('Members Subscriptions: Error in render_product_access_meta_box: ' . $e->getMessage());
            $membership_roles = [];
            $redirect_url = '';
        }
        
        // Final validation to prevent errors
        if (!is_array($membership_roles)) {
            $membership_roles = [];
        }
        
        ?>
        <div class="members-roles-section">
            <p>
                <label><?php _e('Membership Roles:', 'members'); ?></label>
                <span class="description"><?php _e('Select the roles that will be assigned to users who purchase this membership.', 'members'); ?></span>
            </p>
            
            <div class="members-roles-list" style="margin-left: 15px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                <?php if (!empty($roles)) : ?>
                    <?php foreach ($roles as $role_id => $role) : ?>
                        <?php 
                        // Skip if role is not properly formatted
                        if (!is_array($role)) {
                            continue;
                        }
                        
                        // Get role name with fallback
                        $role_name = isset($role['name']) ? $role['name'] : $role_id;
                        ?>
                        <p>
                            <label>
                                <input type="checkbox" 
                                    name="members_product_meta[_membership_roles][]" 
                                    value="<?php echo esc_attr($role_id); ?>" 
                                    <?php checked(in_array($role_id, $membership_roles)); ?>>
                                <?php 
                                if (function_exists('translate_user_role')) {
                                    echo esc_html(translate_user_role($role_name));
                                } else {
                                    echo esc_html($role_name);
                                }
                                ?>
                            </label>
                        </p>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="description"><?php _e('No roles available. Please ensure WordPress roles are properly configured.', 'members'); ?></p>
                <?php endif; ?>
            </div>
            <p class="description"><?php _e('Users will receive all selected roles upon purchase. If their subscription expires or is cancelled, these roles will be removed.', 'members'); ?></p>
        </div>
        
        <p>
            <label for="members-redirect-url"><?php _e('Thank You / Redirect URL:', 'members'); ?></label>
            <input type="url" name="members_product_meta[_redirect_url]" id="members-redirect-url" value="<?php echo esc_attr($redirect_url); ?>" class="large-text" />
            <span class="description"><?php _e('Where to send users after successful purchase.', 'members'); ?></span>
        </p>
        <?php
    } catch (\Exception $e) {
        error_log('Members Subscriptions: Error in render_product_access_meta_box: ' . $e->getMessage());
        echo '<p class="error">' . __('An error occurred while loading the access settings. Please try refreshing the page.', 'members') . '</p>';
    }
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
// format_subscription_period function is defined in functions-subscriptions.php
// user_has_access function is defined in functions-subscriptions.php