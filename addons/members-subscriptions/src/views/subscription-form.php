<?php
/**
 * Subscription Form Template
 *
 * This template displays the subscription signup form.
 */

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

// Get product
$product_id = absint($atts['product_id']);
$product = get_post($product_id);

if (!$product || $product->post_type !== 'members_product') {
    return;
}

// Get product data
$price = get_product_meta($product_id, '_price', 0);
$is_recurring = get_product_meta($product_id, '_recurring', false);
$period = get_product_meta($product_id, '_period', 1);
$period_type = get_product_meta($product_id, '_period_type', 'month');
$has_trial = get_product_meta($product_id, '_has_trial', false);
$trial_days = get_product_meta($product_id, '_trial_days', 0);
$trial_price = get_product_meta($product_id, '_trial_price', 0);

// Get payment gateways
if (class_exists('\\Members\\Subscriptions\\gateways\\Gateway_Manager')) {
    $gateway_manager = gateways\Gateway_Manager::get_instance();
    $payment_methods = $gateway_manager->get_payment_methods();
} else {
    // Fallback if gateways aren't loaded
    $payment_methods = [];
}

// Check if user is logged in
$user_id = get_current_user_id();
$redirect_url = get_product_meta($product_id, '_redirect_url', '');

// Check if user already has access
if ($user_id && user_has_access($user_id, $product_id)) {
    echo '<div class="members-already-subscribed">';
    echo '<p>' . __('You already have access to this membership.', 'members') . '</p>';
    if (!empty($redirect_url)) {
        echo '<a href="' . esc_url($redirect_url) . '" class="button">' . __('Go to Membership Content', 'members') . '</a>';
    }
    echo '</div>';
    return;
}

// Generate nonce
$nonce = wp_create_nonce('members_subscribe_nonce');

// Main container
echo '<div class="members-subscription-container">';

// Product details
echo '<div class="members-subscription-form-header">';
echo '<h2>' . esc_html($product->post_title) . '</h2>';
if (!empty($product->post_excerpt)) {
    echo '<div class="members-product-excerpt">' . wp_kses_post($product->post_excerpt) . '</div>';
}
echo '</div>';

// Subscription form
echo '<form id="members-payment-form" class="members-subscription-form" method="post">';

// Logged out message
if (!$user_id) {
    echo '<div class="members-message members-message-info">';
    echo '<p>' . __('Please log in or create an account to purchase this membership.', 'members') . '</p>';
    echo '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="members-form-submit">' . __('Log In', 'members') . '</a> ';
    echo '<a href="' . esc_url(wp_registration_url()) . '" class="members-form-submit">' . __('Register', 'members') . '</a>';
    echo '</div>';
    echo '</form>';
    echo '</div>'; // Close main container
    return;
}

// Subscription details - Formatted as a plan
echo '<div class="members-subscription-plans">';
echo '<div class="members-subscription-plan selected" data-plan-id="' . esc_attr($product_id) . '">';

// Plan header
echo '<div class="members-subscription-plan-header">';
echo '<div class="members-subscription-plan-title">' . esc_html($product->post_title) . '</div>';

// Price display
echo '<div class="members-subscription-plan-price">';
echo esc_html(number_format_i18n($price, 2));
echo '</div>';

// Billing period
echo '<div class="members-subscription-plan-billing">';
if ($is_recurring) {
    echo esc_html(sprintf(__('Billed %s', 'members'), format_subscription_period($period, $period_type)));
    
    if ($has_trial && $trial_days > 0) {
        echo '<div class="members-trial-details">';
        if ($trial_price > 0) {
            echo '<br>' . sprintf(__('Trial: %s for the first %d days', 'members'), 
                number_format_i18n($trial_price, 2), $trial_days);
        } else {
            echo '<br>' . sprintf(__('Free %d-day trial', 'members'), $trial_days);
        }
        echo '</div>';
    }
} else {
    echo esc_html(__('One-time payment', 'members'));
}
echo '</div>'; // End billing period

echo '</div>'; // End plan header

// Plan features - extracted from post content
if (!empty($product->post_content)) {
    echo '<ul class="members-subscription-plan-features">';
    
    // Simple implementation - split content by new lines and create list items
    $features = explode("\n", strip_tags($product->post_content));
    foreach ($features as $feature) {
        $feature = trim($feature);
        if (!empty($feature)) {
            echo '<li>' . esc_html($feature) . '</li>';
        }
    }
    
    echo '</ul>';
}

// Already selected - since it's the only plan
echo '<button type="button" class="members-subscription-plan-select-button">' . __('Selected', 'members') . '</button>';

echo '</div>'; // End subscription plan
echo '</div>'; // End subscription plans

// Hidden input for selected plan
echo '<input type="hidden" name="plan_id" value="' . esc_attr($product_id) . '">';

// Payment section
echo '<div class="members-payment-form">';
echo '<h3 class="members-payment-form-title">' . __('Payment Information', 'members') . '</h3>';

// Payment methods
if (!empty($payment_methods)) {
    echo '<div class="members-payment-methods">';
    
    foreach ($payment_methods as $method) {
        // Set first method as default
        $is_first = ($payment_methods && isset(reset($payment_methods)['id']) && reset($payment_methods)['id'] === $method['id']);
        
        echo '<div class="members-payment-method' . ($is_first ? ' selected' : '') . '">';
        echo '<div class="members-payment-method-header">';
        echo '<input type="radio" name="payment_method" value="' . esc_attr($method['id']) . '" ' . 
             checked($is_first, true, false) . ' class="members-payment-method-radio">';
        
        // Add logo if available, use method ID to determine which logo to show
        if ($method['id'] === 'stripe') {
            echo '<img src="' . esc_url(plugins_url('/images/stripe-logo.png', dirname(dirname(__FILE__)))) . '" class="members-payment-method-logo" alt="Stripe">';
        } elseif ($method['id'] === 'paypal') {
            echo '<img src="' . esc_url(plugins_url('/images/paypal-logo.png', dirname(dirname(__FILE__)))) . '" class="members-payment-method-logo" alt="PayPal">';
        }
        
        echo '<span class="members-payment-method-title">' . esc_html($method['name']) . '</span>';
        echo '</div>'; // End header
        
        echo '<div class="members-payment-method-content" data-method="' . esc_attr($method['id']) . '">';
        echo '<div class="members-payment-method-description">';
        echo wp_kses_post(isset($method['description']) ? $method['description'] : '');
        echo '</div>';
        
        // Payment method specific fields
        if (isset($method['fields'])) {
            echo $method['fields'];
        }
        echo '</div>'; // End content
        echo '</div>'; // End payment method
    }
    
    echo '</div>'; // End payment methods
} else {
    // No payment methods yet, show a simple message
    echo '<div class="members-payment-methods-notice">';
    echo '<p>' . __('Payment methods will be configured by the administrator.', 'members') . '</p>';
    echo '</div>';
}

// Submit section
echo '<div class="members-form-row">';
wp_nonce_field('members_subscription_form', 'members_subscription_nonce');
echo '<input type="hidden" name="product_id" value="' . esc_attr($product_id) . '">';
echo '<input type="hidden" name="action" value="members_process_subscription">';
echo '<input type="hidden" name="is_recurring" value="' . ($is_recurring ? '1' : '0') . '">';
echo '<input type="hidden" name="redirect_url" value="' . esc_url($redirect_url) . '">';
echo '<button type="submit" class="members-form-submit">' . __('Complete Purchase', 'members') . '</button>';
echo '</div>'; // End submit row

echo '</div>'; // End payment form

echo '</form>';
echo '</div>'; // Close main container

// Required JavaScript for payment methods
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var paymentMethodInputs = document.querySelectorAll('input[name="payment_method"]');
    var paymentMethodFields = document.querySelectorAll('.members-payment-method-fields');
    
    function showSelectedPaymentMethod() {
        var selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
        
        paymentMethodFields.forEach(function(field) {
            if (field.id === 'members-payment-method-' + selectedMethod) {
                field.style.display = 'block';
            } else {
                field.style.display = 'none';
            }
        });
    }
    
    paymentMethodInputs.forEach(function(input) {
        input.addEventListener('change', showSelectedPaymentMethod);
    });
    
    // Show the initial selected method
    showSelectedPaymentMethod();
});
</script>
<?php