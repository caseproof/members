<?php

namespace Members\Subscriptions\gateways;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Base Gateway Class
 * All payment gateways should extend this class
 */
abstract class Gateway {

    /**
     * Gateway ID
     *
     * @var string
     */
    protected $id = '';

    /**
     * Gateway name
     *
     * @var string
     */
    protected $name = '';

    /**
     * Gateway description
     *
     * @var string
     */
    protected $description = '';

    /**
     * Whether this gateway supports subscriptions
     *
     * @var bool
     */
    protected $supports_subscriptions = false;

    /**
     * Whether this gateway supports one-time payments
     *
     * @var bool
     */
    protected $supports_one_time = true;

    /**
     * Whether this gateway supports refunds
     *
     * @var bool
     */
    protected $supports_refunds = false;

    /**
     * Gateway settings
     *
     * @var array
     */
    protected $settings = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
        $this->load_settings();
    }

    /**
     * Initialize the gateway
     * Should be overridden by child classes
     */
    abstract protected function init();

    /**
     * Load gateway settings from database
     */
    protected function load_settings() {
        $this->settings = get_option('members_gateway_' . $this->id, []);
    }

    /**
     * Get gateway ID
     *
     * @return string
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get gateway name
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get gateway description
     *
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Check if gateway supports subscriptions
     *
     * @return bool
     */
    public function supports_subscriptions() {
        return $this->supports_subscriptions;
    }

    /**
     * Check if gateway supports one-time payments
     *
     * @return bool
     */
    public function supports_one_time() {
        return $this->supports_one_time;
    }

    /**
     * Check if gateway supports refunds
     *
     * @return bool
     */
    public function supports_refunds() {
        return $this->supports_refunds;
    }

    /**
     * Get gateway settings
     *
     * @return array
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get a specific setting
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get_setting($key, $default = '') {
        // If the setting exists, get it
        if (isset($this->settings[$key])) {
            $value = $this->settings[$key];
            
            // Handle potentially problematic values for form fields
            if (is_array($value) && !$this->is_multidimensional_array($value)) {
                // For checkboxes or similar fields that expect booleans
                // Return the first value for checkbox fields where '1' is expected
                return reset($value);
            }
            
            return $value;
        }
        
        return $default;
    }
    
    /**
     * Check if an array is multidimensional
     * 
     * @param array $array
     * @return bool
     */
    private function is_multidimensional_array($array) {
        if (!is_array($array)) {
            return false;
        }
        
        foreach ($array as $value) {
            if (is_array($value)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if gateway is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        $enabled = $this->get_setting('enabled', false);
        
        // Make sure the output is explicitly a boolean
        return !empty($enabled);
    }

    /**
     * Get admin settings fields
     * Should be overridden by child classes
     *
     * @return array
     */
    public function get_settings_fields() {
        return [
            'enabled' => [
                'title'   => __('Enable Gateway', 'members'),
                'type'    => 'checkbox',
                'default' => false,
            ],
        ];
    }

    /**
     * Process payment
     * Should be overridden by child classes
     *
     * @param array $payment_data
     * @return array
     */
    abstract public function process_payment($payment_data);

    /**
     * Process subscription payment
     * Should be overridden by child classes that support subscriptions
     *
     * @param array $payment_data
     * @return array
     */
    public function process_subscription($payment_data) {
        return [
            'success' => false,
            'message' => __('This gateway does not support subscription payments.', 'members'),
        ];
    }

    /**
     * Process refund
     * Should be overridden by child classes that support refunds
     *
     * @param int   $transaction_id
     * @param float $amount
     * @param string $reason
     * @return bool
     */
    public function process_refund($transaction_id, $amount = null, $reason = '') {
        return false;
    }

    /**
     * Output gateway payment fields
     * Should be overridden by child classes
     *
     * @return string
     */
    abstract public function payment_fields();

    /**
     * Validate payment fields
     * Should be overridden by child classes
     *
     * @param array $data
     * @return bool|WP_Error
     */
    abstract public function validate_payment_fields($data);

    /**
     * Process webhook
     * Should be overridden by child classes that use webhooks
     */
    public function process_webhook() {
        // Default implementation does nothing
    }

    /**
     * Register webhook endpoints
     * Should be overridden by child classes that use webhooks
     */
    public function register_webhook_endpoints() {
        // Default implementation does nothing
    }

    /**
     * Save settings
     *
     * @param array $settings
     * @return bool
     */
    public function save_settings($settings) {
        $sanitized = $this->sanitize_settings($settings);
        $this->settings = $sanitized;
        return update_option('members_gateway_' . $this->id, $sanitized);
    }

    /**
     * Sanitize settings
     *
     * @param array $settings
     * @return array
     */
    protected function sanitize_settings($settings) {
        $fields = $this->get_settings_fields();
        $sanitized = [];

        foreach ($fields as $key => $field) {
            if (!isset($settings[$key])) {
                $sanitized[$key] = isset($field['default']) ? $field['default'] : '';
                continue;
            }

            switch ($field['type']) {
                case 'checkbox':
                    $sanitized[$key] = !empty($settings[$key]);
                    break;
                case 'text':
                case 'password':
                    $sanitized[$key] = sanitize_text_field($settings[$key]);
                    break;
                case 'textarea':
                    $sanitized[$key] = sanitize_textarea_field($settings[$key]);
                    break;
                case 'select':
                    $sanitized[$key] = sanitize_text_field($settings[$key]);
                    break;
                default:
                    $sanitized[$key] = $settings[$key];
                    break;
            }
        }

        return $sanitized;
    }
}