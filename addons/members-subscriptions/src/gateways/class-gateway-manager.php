<?php

namespace Members\Subscriptions\gateways;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Gateway Manager
 * Manages all payment gateways
 */
class Gateway_Manager {

    /**
     * Singleton instance
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Registered gateways
     *
     * @var array
     */
    private $gateways = [];

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Register default gateways
        $this->register_default_gateways();
        
        // Hook into WordPress
        $this->init_hooks();
    }

    /**
     * Register default gateways
     */
    private function register_default_gateways() {
        // Manual gateway
        require_once __DIR__ . '/class-manual-gateway.php';
        $this->register_gateway(new Manual_Gateway());
        
        // Stripe gateway
        require_once __DIR__ . '/class-stripe-gateway.php';
        $this->register_gateway(new Stripe_Gateway());
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register webhook endpoints
        add_action('init', [$this, 'register_webhook_endpoints']);
        
        // Handle gateway settings
        add_action('admin_init', [$this, 'save_gateway_settings']);
    }

    /**
     * Register a gateway
     *
     * @param Gateway $gateway
     */
    public function register_gateway($gateway) {
        $this->gateways[$gateway->get_id()] = $gateway;
    }

    /**
     * Get all registered gateways
     *
     * @return array
     */
    public function get_gateways() {
        return $this->gateways;
    }

    /**
     * Get enabled gateways
     *
     * @return array
     */
    public function get_enabled_gateways() {
        $enabled = [];
        foreach ($this->gateways as $gateway) {
            if ($gateway->is_enabled()) {
                $enabled[$gateway->get_id()] = $gateway;
            }
        }
        return $enabled;
    }

    /**
     * Get a gateway by ID
     *
     * @param string $gateway_id
     * @return Gateway|null
     */
    public function get_gateway($gateway_id) {
        return isset($this->gateways[$gateway_id]) ? $this->gateways[$gateway_id] : null;
    }

    /**
     * Register webhook endpoints for all gateways
     */
    public function register_webhook_endpoints() {
        foreach ($this->gateways as $gateway) {
            $gateway->register_webhook_endpoints();
        }
    }

    /**
     * Get gateway payment methods for checkout
     *
     * @return array
     */
    public function get_payment_methods() {
        $methods = [];
        foreach ($this->get_enabled_gateways() as $gateway) {
            $methods[$gateway->get_id()] = [
                'id'          => $gateway->get_id(),
                'name'        => $gateway->get_name(),
                'description' => $gateway->get_description(),
                'fields'      => $gateway->payment_fields(),
            ];
        }
        return $methods;
    }

    /**
     * Process payment
     *
     * @param string $gateway_id
     * @param array  $payment_data
     * @return array
     */
    public function process_payment($gateway_id, $payment_data) {
        $gateway = $this->get_gateway($gateway_id);
        
        if (!$gateway || !$gateway->is_enabled()) {
            return [
                'success' => false,
                'message' => __('Invalid payment method.', 'members'),
            ];
        }
        
        return $gateway->process_payment($payment_data);
    }

    /**
     * Process subscription
     *
     * @param string $gateway_id
     * @param array  $payment_data
     * @return array
     */
    public function process_subscription($gateway_id, $payment_data) {
        $gateway = $this->get_gateway($gateway_id);
        
        if (!$gateway || !$gateway->is_enabled()) {
            return [
                'success' => false,
                'message' => __('Invalid payment method.', 'members'),
            ];
        }
        
        if (!$gateway->supports_subscriptions()) {
            return [
                'success' => false,
                'message' => __('This payment method does not support subscription payments.', 'members'),
            ];
        }
        
        return $gateway->process_subscription($payment_data);
    }

    /**
     * Process refund
     *
     * @param string $gateway_id
     * @param int    $transaction_id
     * @param float  $amount
     * @param string $reason
     * @return bool
     */
    public function process_refund($gateway_id, $transaction_id, $amount = null, $reason = '') {
        $gateway = $this->get_gateway($gateway_id);
        
        if (!$gateway || !$gateway->is_enabled() || !$gateway->supports_refunds()) {
            return false;
        }
        
        return $gateway->process_refund($transaction_id, $amount, $reason);
    }

    /**
     * Save gateway settings
     */
    public function save_gateway_settings() {
        // Early exit if this is not a form submission
        if (!isset($_POST['members_gateway_settings']) || !isset($_POST['gateway_id'])) {
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_payment_gateways')) {
            wp_die(__('You do not have permission to manage payment gateways.', 'members'));
            return;
        }
        
        $gateway_id = sanitize_text_field($_POST['gateway_id']);
        $gateway = $this->get_gateway($gateway_id);
        
        if (!$gateway) {
            wp_die(__('Invalid gateway.', 'members'));
            return;
        }
        
        // Verify nonce with generic action name to avoid expiration issues
        if (!check_admin_referer('members_save_gateway_settings', 'members_gateway_settings')) {
            return; // Nonce verification will handle the error message
        }
        
        // Get and sanitize settings
        $settings = isset($_POST['members_gateway'][$gateway_id]) ? $_POST['members_gateway'][$gateway_id] : [];
        $sanitized_settings = [];
        
        // Basic sanitization of settings
        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $sanitized_settings[$key] = array_map('sanitize_text_field', $value);
            } else {
                $sanitized_settings[$key] = sanitize_text_field($value);
            }
        }
        
        // Save settings
        $gateway->save_settings($sanitized_settings);
        
        // Redirect with success message
        wp_redirect(add_query_arg([
            'page'    => 'members-gateways',
            'gateway' => $gateway_id,
            'updated' => '1',
        ], admin_url('admin.php')));
        exit;
    }
}