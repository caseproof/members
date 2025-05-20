<?php

namespace Members\Subscriptions\gateways;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions;
use Members\Subscriptions\Exceptions\Gateway_Exception;
use Members\Subscriptions\Exceptions\API_Exception;

/**
 * Stripe Gateway
 */
class Stripe_Gateway extends Gateway {

    /**
     * Initialize the gateway
     */
    protected function init() {
        $this->id = 'stripe';
        $this->name = __('Stripe', 'members');
        $this->description = __('Pay with your credit card via Stripe.', 'members');
        $this->supports_subscriptions = true;
        $this->supports_one_time = true;
        $this->supports_refunds = true;
        
        // Include Stripe SDK if not already included
        if (!class_exists('\Stripe\Stripe')) {
            // Check if we need to load Stripe SDK
            if (!$this->maybe_load_stripe_sdk()) {
                // If we can't load the SDK, disable support for subscriptions and refunds
                $this->supports_subscriptions = false;
                $this->supports_refunds = false;
            }
        }
        
        // Initialize Stripe API if gateway is enabled
        if ($this->is_enabled()) {
            $this->init_api();
        }
    }
    
    /**
     * Maybe load Stripe SDK
     * 
     * @return bool True if SDK was loaded or already exists
     */
    protected function maybe_load_stripe_sdk() {
        // First check if Stripe is already loaded by another plugin
        if (class_exists('\Stripe\Stripe')) {
            return true;
        }
        
        // Try to load from Composer autoloader if available
        $composer_autoloader = __DIR__ . '/../../../../vendor/autoload.php';
        if (file_exists($composer_autoloader)) {
            require_once $composer_autoloader;
            return class_exists('\Stripe\Stripe');
        }
        
        // As a fallback, we'll try bundling the SDK directly
        // NOTE: In a real implementation, use Composer to require stripe/stripe-php
        // or include the PHP library directly from GitHub
        return false;
    }
    
    /**
     * Initialize Stripe API
     */
    protected function init_api() {
        // Set API key
        $api_key = $this->get_setting('test_mode', false) ? 
            $this->get_setting('test_secret_key', '') : 
            $this->get_setting('live_secret_key', '');
            
        if (!empty($api_key) && class_exists('\Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey($api_key);
            \Stripe\Stripe::setAppInfo(
                'WordPress Members Subscriptions',
                Subscriptions\Plugin::VERSION,
                'https://github.com/memberpress/members'
            );
        }
    }
    
    /**
     * Get admin settings fields
     *
     * @return array
     */
    public function get_settings_fields() {
        return [
            'enabled' => [
                'title'   => __('Enable/Disable', 'members'),
                'type'    => 'checkbox',
                'label'   => __('Enable Stripe Gateway', 'members'),
                'default' => false,
            ],
            'test_mode' => [
                'title'   => __('Test Mode', 'members'),
                'type'    => 'checkbox',
                'label'   => __('Enable Test Mode', 'members'),
                'default' => true,
            ],
            'title' => [
                'title'   => __('Title', 'members'),
                'type'    => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'members'),
                'default' => __('Credit Card (Stripe)', 'members'),
            ],
            'description' => [
                'title'   => __('Description', 'members'),
                'type'    => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'members'),
                'default' => __('Pay with your credit card via Stripe.', 'members'),
            ],
            'test_publishable_key' => [
                'title' => __('Test Publishable Key', 'members'),
                'type'  => 'text',
            ],
            'test_secret_key' => [
                'title' => __('Test Secret Key', 'members'),
                'type'  => 'password',
            ],
            'live_publishable_key' => [
                'title' => __('Live Publishable Key', 'members'),
                'type'  => 'text',
            ],
            'live_secret_key' => [
                'title' => __('Live Secret Key', 'members'),
                'type'  => 'password',
            ],
            'webhook_secret' => [
                'title' => __('Webhook Secret', 'members'),
                'type'  => 'password',
                'description' => __('Secret for verifying webhooks from Stripe.', 'members'),
            ],
            'inline_cc_form' => [
                'title'   => __('Inline Credit Card Form', 'members'),
                'type'    => 'checkbox',
                'label'   => __('Enable inline credit card form', 'members'),
                'default' => true,
            ],
            'statement_descriptor' => [
                'title'       => __('Statement Descriptor', 'members'),
                'type'        => 'text',
                'description' => __('Extra information about a charge. This will appear on your customer\'s credit card statement.', 'members'),
                'default'     => get_bloginfo('name'),
            ],
        ];
    }
    
    /**
     * Get public key for Stripe
     * 
     * @return string
     */
    public function get_publishable_key() {
        return $this->get_setting('test_mode', false) ?
            $this->get_setting('test_publishable_key', '') :
            $this->get_setting('live_publishable_key', '');
    }
    
    /**
     * Payment fields displayed on checkout
     *
     * @return string
     */
    public function payment_fields() {
        $description = $this->get_setting('description');
        
        ob_start();
        ?>
        <div class="members-stripe-payment-form">
            <?php if (!empty($description)) : ?>
                <p><?php echo wp_kses_post($description); ?></p>
            <?php endif; ?>
            
            <div class="form-row form-row-wide">
                <label for="members-stripe-card-element">
                    <?php esc_html_e('Credit or debit card', 'members'); ?>
                </label>
                <div id="members-stripe-card-element">
                    <!-- A Stripe Element will be inserted here. -->
                </div>
                <div id="members-stripe-card-errors" role="alert"></div>
            </div>
            
            <input type="hidden" id="members-stripe-payment-method" name="stripe_payment_method" value="" />
        </div>
        
        <script>
            // This code would initialize Stripe Elements
            // In a real implementation, you would add the Stripe.js script and initialize Elements
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof Stripe === 'undefined') {
                    console.error('Stripe.js not loaded');
                    return;
                }
                
                var stripe = Stripe('<?php echo esc_js($this->get_publishable_key()); ?>');
                var elements = stripe.elements();
                
                var style = {
                    base: {
                        color: '#32325d',
                        fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                        fontSmoothing: 'antialiased',
                        fontSize: '16px',
                        '::placeholder': {
                            color: '#aab7c4'
                        }
                    },
                    invalid: {
                        color: '#fa755a',
                        iconColor: '#fa755a'
                    }
                };
                
                var card = elements.create('card', {style: style});
                card.mount('#members-stripe-card-element');
                
                card.on('change', function(event) {
                    var displayError = document.getElementById('members-stripe-card-errors');
                    if (event.error) {
                        displayError.textContent = event.error.message;
                    } else {
                        displayError.textContent = '';
                    }
                });
                
                var form = document.getElementById('members-payment-form');
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    
                    stripe.createPaymentMethod('card', card).then(function(result) {
                        if (result.error) {
                            var errorElement = document.getElementById('members-stripe-card-errors');
                            errorElement.textContent = result.error.message;
                        } else {
                            var paymentMethodInput = document.getElementById('members-stripe-payment-method');
                            paymentMethodInput.value = result.paymentMethod.id;
                            form.submit();
                        }
                    });
                });
            });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Validate payment fields
     *
     * @param array $data
     * @return bool|\WP_Error
     */
    public function validate_payment_fields($data) {
        if (empty($data['stripe_payment_method'])) {
            return new \WP_Error('stripe_payment_method_missing', __('Payment method missing. Please try again.', 'members'));
        }
        
        return true;
    }
    
    /**
     * Process payment
     *
     * @param array $payment_data
     * @return array
     */
    public function process_payment($payment_data) {
        if (!class_exists('\Stripe\Stripe')) {
            return [
                'success' => false,
                'message' => __('Stripe SDK not loaded. Please contact the site administrator.', 'members'),
            ];
        }
        
        try {
            // Get payment method from form submission
            $payment_method_id = !empty($payment_data['stripe_payment_method']) ? 
                $payment_data['stripe_payment_method'] : '';
            
            if (empty($payment_method_id)) {
                throw new \Exception(__('Payment method missing. Please try again.', 'members'));
            }
            
            // Create or get customer
            $customer = $this->get_or_create_customer($payment_data);
            
            if (empty($customer)) {
                throw new \Exception(__('Unable to create customer in Stripe.', 'members'));
            }
            
            // Attach payment method to customer
            $this->attach_payment_method_to_customer($payment_method_id, $customer->id);
            
            // Set as default payment method
            \Stripe\Customer::update($customer->id, [
                'invoice_settings' => [
                    'default_payment_method' => $payment_method_id,
                ],
            ]);
            
            // Create the charge
            $charge = \Stripe\PaymentIntent::create([
                'amount' => $this->get_stripe_amount($payment_data['amount']),
                'currency' => strtolower($this->get_setting('currency', 'usd')),
                'customer' => $customer->id,
                'payment_method' => $payment_method_id,
                'description' => sprintf(__('Order for %s', 'members'), $payment_data['product_name']),
                'confirm' => true,
                'statement_descriptor' => $this->get_statement_descriptor(),
                'metadata' => [
                    'user_id' => $payment_data['user_id'],
                    'product_id' => $payment_data['product_id'],
                ],
            ]);
            
            // Process successful payment
            if ($charge->status === 'succeeded') {
                // Prepare transaction data
                $transaction_data = [
                    'user_id' => $payment_data['user_id'],
                    'product_id' => $payment_data['product_id'],
                    'amount' => $payment_data['amount'],
                    'total' => $payment_data['amount'],
                    'trans_num' => $charge->id,
                    'status' => 'complete',
                    'txn_type' => 'payment',
                    'gateway' => $this->id,
                ];
                
                // Add expiration date if this is a one-time payment with limited access duration
                if (!empty($payment_data['has_access_period']) && !empty($payment_data['expires_at'])) {
                    $transaction_data['expires_at'] = $payment_data['expires_at'];
                }
                
                // Create transaction record
                $transaction_id = Subscriptions\create_transaction($transaction_data);
                
                if (!$transaction_id) {
                    throw new \Exception(__('Error recording transaction.', 'members'));
                }
                
                // Store additional transaction details as meta
                if ($transaction_id) {
                    // Store payment method details
                    $payment_method = \Stripe\PaymentMethod::retrieve($payment_method_id);
                    if ($payment_method && isset($payment_method->card)) {
                        Subscriptions\update_transaction_meta($transaction_id, '_card_brand', $payment_method->card->brand);
                        Subscriptions\update_transaction_meta($transaction_id, '_card_last4', $payment_method->card->last4);
                        Subscriptions\update_transaction_meta($transaction_id, '_card_exp_month', $payment_method->card->exp_month);
                        Subscriptions\update_transaction_meta($transaction_id, '_card_exp_year', $payment_method->card->exp_year);
                    }
                    
                    // Store customer information
                    Subscriptions\update_transaction_meta($transaction_id, '_stripe_customer_id', $customer->id);
                    
                    // Store payment intent details
                    Subscriptions\update_transaction_meta($transaction_id, '_payment_intent_id', $charge->id);
                    Subscriptions\update_transaction_meta($transaction_id, '_payment_method_id', $payment_method_id);
                    Subscriptions\update_transaction_meta($transaction_id, '_payment_created', date('Y-m-d H:i:s', $charge->created));
                    Subscriptions\update_transaction_meta($transaction_id, '_currency', strtoupper($charge->currency));
                    
                    // Store access period details if applicable
                    if (!empty($payment_data['has_access_period'])) {
                        Subscriptions\update_transaction_meta($transaction_id, '_has_access_period', '1');
                        Subscriptions\update_transaction_meta($transaction_id, '_access_period', $payment_data['access_period']);
                        Subscriptions\update_transaction_meta($transaction_id, '_access_period_type', $payment_data['access_period_type']);
                        
                        if (!empty($payment_data['expires_at'])) {
                            Subscriptions\update_transaction_meta($transaction_id, '_expires_at', $payment_data['expires_at']);
                        }
                    }
                    
                    // Store billing details if available
                    if (isset($payment_method->billing_details)) {
                        $billing = $payment_method->billing_details;
                        if (isset($billing->address)) {
                            $address_data = [];
                            foreach (['city', 'country', 'line1', 'line2', 'postal_code', 'state'] as $key) {
                                if (isset($billing->address->$key)) {
                                    $address_data[$key] = $billing->address->$key;
                                }
                            }
                            Subscriptions\update_transaction_meta($transaction_id, '_billing_address', $address_data);
                        }
                        
                        if (isset($billing->name)) {
                            Subscriptions\update_transaction_meta($transaction_id, '_billing_name', $billing->name);
                        }
                        
                        if (isset($billing->email)) {
                            Subscriptions\update_transaction_meta($transaction_id, '_billing_email', $billing->email);
                        }
                        
                        if (isset($billing->phone)) {
                            Subscriptions\update_transaction_meta($transaction_id, '_billing_phone', $billing->phone);
                        }
                    }
                }
                
                return [
                    'success' => true,
                    'transaction_id' => $transaction_id,
                    'redirect' => !empty($payment_data['redirect']) ? $payment_data['redirect'] : '',
                    'message' => __('Payment successful.', 'members'),
                ];
            } else if ($charge->status === 'requires_action') {
                // Handle 3D Secure if needed
                return [
                    'success' => false,
                    'requires_action' => true,
                    'payment_intent_client_secret' => $charge->client_secret,
                    'message' => __('Additional authentication required. Please complete the verification.', 'members'),
                ];
            } else {
                throw new \Exception(__('Payment failed.', 'members'));
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Process subscription
     *
     * @param array $payment_data
     * @return array
     */
    public function process_subscription($payment_data) {
        if (!class_exists('\Stripe\Stripe')) {
            return [
                'success' => false,
                'message' => __('Stripe SDK not loaded. Please contact the site administrator.', 'members'),
            ];
        }
        
        try {
            // Get payment method from form submission
            $payment_method_id = !empty($payment_data['stripe_payment_method']) ? 
                $payment_data['stripe_payment_method'] : '';
            
            if (empty($payment_method_id)) {
                throw new \Exception(__('Payment method missing. Please try again.', 'members'));
            }
            
            // Create or get customer
            $customer = $this->get_or_create_customer($payment_data);
            
            if (empty($customer)) {
                throw new \Exception(__('Unable to create customer in Stripe.', 'members'));
            }
            
            // Attach payment method to customer
            $this->attach_payment_method_to_customer($payment_method_id, $customer->id);
            
            // Set as default payment method
            \Stripe\Customer::update($customer->id, [
                'invoice_settings' => [
                    'default_payment_method' => $payment_method_id,
                ],
            ]);
            
            // Prepare subscription data
            $subscription_data = [
                'customer' => $customer->id,
                'items' => [
                    [
                        'price_data' => [
                            'unit_amount' => $this->get_stripe_amount($payment_data['amount']),
                            'currency' => strtolower($this->get_setting('currency', 'usd')),
                            'product_data' => [
                                'name' => $payment_data['product_name'],
                            ],
                            'recurring' => [
                                'interval' => $this->convert_interval_to_stripe($payment_data['period_type']),
                                'interval_count' => $payment_data['period'],
                            ],
                        ],
                    ],
                ],
                'default_payment_method' => $payment_method_id,
                'payment_behavior' => 'default_incomplete',
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'user_id' => $payment_data['user_id'],
                    'product_id' => $payment_data['product_id'],
                ],
            ];
            
            // Add trial if applicable
            if (!empty($payment_data['trial']) && !empty($payment_data['trial_days'])) {
                $subscription_data['trial_period_days'] = $payment_data['trial_days'];
            }
            
            // Create the subscription
            $subscription = \Stripe\Subscription::create($subscription_data);
            
            // Check if we need additional authorization (3D Secure)
            $requires_action = false;
            $payment_intent_client_secret = null;
            
            if ($subscription->status === 'incomplete' && 
                $subscription->latest_invoice->payment_intent && 
                $subscription->latest_invoice->payment_intent->status === 'requires_action') {
                $requires_action = true;
                $payment_intent_client_secret = $subscription->latest_invoice->payment_intent->client_secret;
            }
            
            // Create subscription record
            $subscription_data = [
                'user_id' => $payment_data['user_id'],
                'product_id' => $payment_data['product_id'],
                'gateway' => $this->id,
                'status' => ($subscription->status === 'active' || $subscription->status === 'trialing') ? 'active' : 'pending',
                'subscr_id' => $subscription->id,
                'period_type' => $payment_data['period_type'],
                'period' => $payment_data['period'],
                'price' => $payment_data['amount'],
                'total' => $payment_data['amount'],
            ];
            
            // Add trial data if present
            if (!empty($payment_data['trial']) && !empty($payment_data['trial_days'])) {
                $subscription_data['trial'] = 1;
                $subscription_data['trial_days'] = $payment_data['trial_days'];
                $subscription_data['trial_amount'] = !empty($payment_data['trial_amount']) ? $payment_data['trial_amount'] : 0;
                $subscription_data['trial_total'] = !empty($payment_data['trial_amount']) ? $payment_data['trial_amount'] : 0;
            }
            
            $subscription_id = Subscriptions\create_subscription($subscription_data);
            
            if (!$subscription_id) {
                throw new \Exception(__('Error recording subscription.', 'members'));
            }
            
            // Create transaction for the first payment (or trial)
            $transaction_data = [
                'user_id' => $payment_data['user_id'],
                'product_id' => $payment_data['product_id'],
                'amount' => (!empty($payment_data['trial']) && !empty($payment_data['trial_days'])) ? 
                    $payment_data['trial_amount'] : $payment_data['amount'],
                'total' => (!empty($payment_data['trial']) && !empty($payment_data['trial_days'])) ? 
                    $payment_data['trial_amount'] : $payment_data['amount'],
                'trans_num' => $subscription->latest_invoice->id,
                'status' => ($subscription->status === 'active' || $subscription->status === 'trialing') ? 'complete' : 'pending',
                'txn_type' => 'payment',
                'gateway' => $this->id,
                'subscription_id' => $subscription_id,
            ];
            
            $transaction_id = Subscriptions\create_transaction($transaction_data);
            
            if (!$transaction_id) {
                throw new \Exception(__('Error recording transaction.', 'members'));
            }
            
            // Store additional transaction details as meta
            if ($transaction_id) {
                // Store payment method details
                $payment_method = \Stripe\PaymentMethod::retrieve($payment_method_id);
                if ($payment_method && isset($payment_method->card)) {
                    Subscriptions\update_transaction_meta($transaction_id, '_card_brand', $payment_method->card->brand);
                    Subscriptions\update_transaction_meta($transaction_id, '_card_last4', $payment_method->card->last4);
                    Subscriptions\update_transaction_meta($transaction_id, '_card_exp_month', $payment_method->card->exp_month);
                    Subscriptions\update_transaction_meta($transaction_id, '_card_exp_year', $payment_method->card->exp_year);
                }
                
                // Store customer information
                Subscriptions\update_transaction_meta($transaction_id, '_stripe_customer_id', $customer->id);
                
                // Store subscription details
                Subscriptions\update_transaction_meta($transaction_id, '_subscription_id', $subscription->id);
                Subscriptions\update_transaction_meta($transaction_id, '_invoice_id', $subscription->latest_invoice->id);
                if (!empty($subscription->latest_invoice->payment_intent)) {
                    Subscriptions\update_transaction_meta($transaction_id, '_payment_intent_id', $subscription->latest_invoice->payment_intent->id);
                }
                Subscriptions\update_transaction_meta($transaction_id, '_payment_method_id', $payment_method_id);
                Subscriptions\update_transaction_meta($transaction_id, '_currency', strtoupper($subscription->currency));
                
                // Store subscription period details
                if (isset($subscription->current_period_start) && isset($subscription->current_period_end)) {
                    Subscriptions\update_transaction_meta($transaction_id, '_period_start', date('Y-m-d H:i:s', $subscription->current_period_start));
                    Subscriptions\update_transaction_meta($transaction_id, '_period_end', date('Y-m-d H:i:s', $subscription->current_period_end));
                }
                
                // Store trial information if applicable
                if (!empty($payment_data['trial']) && !empty($payment_data['trial_days'])) {
                    Subscriptions\update_transaction_meta($transaction_id, '_is_trial', '1');
                    Subscriptions\update_transaction_meta($transaction_id, '_trial_days', $payment_data['trial_days']);
                }
                
                // Store billing details if available
                if (isset($payment_method->billing_details)) {
                    $billing = $payment_method->billing_details;
                    if (isset($billing->address)) {
                        $address_data = [];
                        foreach (['city', 'country', 'line1', 'line2', 'postal_code', 'state'] as $key) {
                            if (isset($billing->address->$key)) {
                                $address_data[$key] = $billing->address->$key;
                            }
                        }
                        Subscriptions\update_transaction_meta($transaction_id, '_billing_address', $address_data);
                    }
                    
                    if (isset($billing->name)) {
                        Subscriptions\update_transaction_meta($transaction_id, '_billing_name', $billing->name);
                    }
                    
                    if (isset($billing->email)) {
                        Subscriptions\update_transaction_meta($transaction_id, '_billing_email', $billing->email);
                    }
                    
                    if (isset($billing->phone)) {
                        Subscriptions\update_transaction_meta($transaction_id, '_billing_phone', $billing->phone);
                    }
                }
            }
            
            // Return appropriate response based on status
            if ($requires_action) {
                return [
                    'success' => false,
                    'requires_action' => true,
                    'payment_intent_client_secret' => $payment_intent_client_secret,
                    'subscription_id' => $subscription_id,
                    'transaction_id' => $transaction_id,
                    'message' => __('Additional authentication required. Please complete the verification.', 'members'),
                ];
            }
            
            return [
                'success' => true,
                'subscription_id' => $subscription_id,
                'transaction_id' => $transaction_id,
                'redirect' => !empty($payment_data['redirect']) ? $payment_data['redirect'] : '',
                'message' => __('Subscription created successfully.', 'members'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Process refund
     *
     * @param int   $transaction_id
     * @param float $amount
     * @param string $reason
     * @return bool
     */
    public function process_refund($transaction_id, $amount = null, $reason = '') {
        if (!class_exists('\Stripe\Stripe')) {
            return false;
        }
        
        try {
            // Get transaction
            $transaction = Subscriptions\get_transaction($transaction_id);
            
            if (!$transaction) {
                return false;
            }
            
            // Check if transaction was processed by Stripe
            if ($transaction->gateway !== $this->id) {
                return false;
            }
            
            // Refund the charge
            $refund = \Stripe\Refund::create([
                'charge' => $transaction->trans_num,
                'amount' => $amount ? $this->get_stripe_amount($amount) : null,
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'transaction_id' => $transaction_id,
                    'reason' => $reason,
                ],
            ]);
            
            if ($refund->status === 'succeeded') {
                // Update transaction status
                Subscriptions\update_transaction($transaction_id, [
                    'status' => 'refunded',
                ]);
                
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Register webhook endpoints
     */
    public function register_webhook_endpoints() {
        add_action('rest_api_init', function() {
            register_rest_route('members/v1', '/stripe-webhook', [
                'methods'  => 'POST',
                'callback' => [$this, 'process_webhook'],
                'permission_callback' => '__return_true',
            ]);
        });
    }
    
    /**
     * Process webhook
     */
    public function process_webhook() {
        if (!class_exists('\Stripe\Stripe')) {
            Subscriptions\log_message('Stripe SDK not loaded when processing webhook', 'error');
            status_header(500);
            echo json_encode(['error' => 'Internal server error']);
            exit;
        }
        
        try {
            $webhook_secret = $this->get_setting('webhook_secret', '');
            
            if (empty($webhook_secret)) {
                Subscriptions\log_message('Webhook secret not configured', 'error');
                status_header(500);
                echo json_encode(['error' => 'Webhook secret not configured']);
                exit;
            }
            
            $payload = @file_get_contents('php://input');
            
            if (empty($payload)) {
                Subscriptions\log_message('Empty payload received in webhook', 'error');
                status_header(400);
                echo json_encode(['error' => 'Empty payload']);
                exit;
            }
            
            if (!isset($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
                Subscriptions\log_message('Stripe signature not present in webhook request', 'error');
                status_header(400);
                echo json_encode(['error' => 'No Stripe signature found in request']);
                exit;
            }
            
            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
            
            // Verify webhook signature
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
            
            // Log webhook received
            Subscriptions\log_message(
                sprintf('Webhook received: %s', $event->type),
                'info',
                ['event_id' => $event->id, 'event_type' => $event->type]
            );
            
            // Process various event types
            switch ($event->type) {
                case 'invoice.payment_succeeded':
                    $this->handle_invoice_payment_succeeded($event->data->object);
                    break;
                    
                case 'invoice.payment_failed':
                    $this->handle_invoice_payment_failed($event->data->object);
                    break;
                    
                case 'customer.subscription.deleted':
                    $this->handle_subscription_deleted($event->data->object);
                    break;
                    
                case 'customer.subscription.updated':
                    $this->handle_subscription_updated($event->data->object);
                    break;
                    
                case 'charge.refunded':
                    $this->handle_charge_refunded($event->data->object);
                    break;
                    
                case 'payment_intent.succeeded':
                    $this->handle_payment_intent_succeeded($event->data->object);
                    break;
                    
                case 'payment_intent.payment_failed':
                    $this->handle_payment_intent_failed($event->data->object);
                    break;
                    
                default:
                    Subscriptions\log_message(
                        sprintf('Unhandled webhook event type: %s', $event->type),
                        'info',
                        ['event_id' => $event->id]
                    );
                    break;
            }
            
            status_header(200);
            echo json_encode(['success' => true]);
            exit;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            Subscriptions\log_message(
                'Invalid Stripe webhook signature',
                'error',
                ['error' => $e->getMessage()]
            );
            status_header(400);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        } catch (\Stripe\Exception\UnexpectedValueException $e) {
            // Invalid payload
            Subscriptions\log_message(
                'Invalid Stripe webhook payload',
                'error',
                ['error' => $e->getMessage()]
            );
            status_header(400);
            echo json_encode(['error' => 'Invalid payload']);
            exit;
        } catch (\Exception $e) {
            // General error
            Subscriptions\log_message(
                'Error processing Stripe webhook',
                'error',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            );
            status_header(500);
            echo json_encode(['error' => 'Internal server error']);
            exit;
        }
    }
    
    /**
     * Handle invoice payment succeeded event
     *
     * @param \Stripe\Invoice $invoice
     */
    protected function handle_invoice_payment_succeeded($invoice) {
        Subscriptions\log_message(
            'Processing invoice.payment_succeeded webhook',
            'info',
            ['invoice_id' => $invoice->id]
        );
        
        if (empty($invoice->subscription)) {
            Subscriptions\log_message(
                'No subscription found in invoice',
                'info',
                ['invoice_id' => $invoice->id]
            );
            return;
        }
        
        // Find subscription by Stripe subscription ID
        $subscription = $this->get_subscription_by_stripe_id($invoice->subscription);
        
        if (!$subscription) {
            Subscriptions\log_message(
                'No matching subscription found in database',
                'warning',
                ['stripe_subscription_id' => $invoice->subscription]
            );
            return;
        }
        
        // Update subscription status if needed
        if ($subscription->status !== 'active') {
            Subscriptions\log_message(
                'Updating subscription status to active',
                'info',
                ['subscription_id' => $subscription->id]
            );
            
            Subscriptions\update_subscription($subscription->id, [
                'status' => 'active',
            ]);
            
            // Send activation email if it was previously inactive
            if ($subscription->status !== 'active' && function_exists('\\Members\\Subscriptions\\send_new_subscription_email')) {
                Subscriptions\send_new_subscription_email($subscription);
            }
        }
        
        // Check if we already have a transaction for this invoice
        $existing_transaction = $this->get_transaction_by_stripe_id($invoice->id);
        
        if ($existing_transaction) {
            // Update transaction status if needed
            if ($existing_transaction->status !== 'complete') {
                Subscriptions\log_message(
                    'Updating transaction status to complete',
                    'info',
                    ['transaction_id' => $existing_transaction->id]
                );
                
                Subscriptions\update_transaction($existing_transaction->id, [
                    'status' => 'complete',
                ]);
            }
            
            return;
        }
        
        // Create a new transaction
        $transaction_id = Subscriptions\create_transaction([
            'user_id' => $subscription->user_id,
            'product_id' => $subscription->product_id,
            'amount' => $invoice->amount_paid / 100, // Convert from cents
            'total' => $invoice->amount_paid / 100,
            'trans_num' => $invoice->id,
            'status' => 'complete',
            'txn_type' => 'payment',
            'gateway' => $this->id,
            'subscription_id' => $subscription->id,
        ]);
        
        Subscriptions\log_message(
            'Created new transaction for invoice',
            'info',
            ['transaction_id' => $transaction_id, 'invoice_id' => $invoice->id]
        );
        
        // Store additional transaction meta data
        if ($transaction_id) {
            // Store invoice details
            Subscriptions\update_transaction_meta($transaction_id, '_invoice_id', $invoice->id);
            Subscriptions\update_transaction_meta($transaction_id, '_invoice_number', $invoice->number);
            Subscriptions\update_transaction_meta($transaction_id, '_currency', strtoupper($invoice->currency));
            Subscriptions\update_transaction_meta($transaction_id, '_payment_created', date('Y-m-d H:i:s', $invoice->created));
            
            // Store subscription details
            Subscriptions\update_transaction_meta($transaction_id, '_subscription_id', $invoice->subscription);
            
            // Store customer details
            if (!empty($invoice->customer)) {
                Subscriptions\update_transaction_meta($transaction_id, '_stripe_customer_id', $invoice->customer);
            }
            
            // Store payment method details if available
            if (isset($invoice->payment_intent) && !empty($invoice->payment_intent)) {
                try {
                    $payment_intent = \Stripe\PaymentIntent::retrieve($invoice->payment_intent);
                    if ($payment_intent && isset($payment_intent->payment_method)) {
                        $payment_method = \Stripe\PaymentMethod::retrieve($payment_intent->payment_method);
                        if ($payment_method && isset($payment_method->card)) {
                            Subscriptions\update_transaction_meta($transaction_id, '_card_brand', $payment_method->card->brand);
                            Subscriptions\update_transaction_meta($transaction_id, '_card_last4', $payment_method->card->last4);
                            Subscriptions\update_transaction_meta($transaction_id, '_card_exp_month', $payment_method->card->exp_month);
                            Subscriptions\update_transaction_meta($transaction_id, '_card_exp_year', $payment_method->card->exp_year);
                        }
                        
                        // Store billing details if available
                        if (isset($payment_method->billing_details)) {
                            $billing = $payment_method->billing_details;
                            if (isset($billing->address)) {
                                $address_data = [];
                                foreach (['city', 'country', 'line1', 'line2', 'postal_code', 'state'] as $key) {
                                    if (isset($billing->address->$key)) {
                                        $address_data[$key] = $billing->address->$key;
                                    }
                                }
                                Subscriptions\update_transaction_meta($transaction_id, '_billing_address', $address_data);
                            }
                            
                            if (isset($billing->name)) {
                                Subscriptions\update_transaction_meta($transaction_id, '_billing_name', $billing->name);
                            }
                            
                            if (isset($billing->email)) {
                                Subscriptions\update_transaction_meta($transaction_id, '_billing_email', $billing->email);
                            }
                            
                            if (isset($billing->phone)) {
                                Subscriptions\update_transaction_meta($transaction_id, '_billing_phone', $billing->phone);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Just log and continue if we can't get payment method details
                    Subscriptions\log_message(
                        'Failed to retrieve payment method details',
                        'warning',
                        ['error' => $e->getMessage()]
                    );
                }
            }
            
            // Store period start and end dates
            if (isset($invoice->lines) && isset($invoice->lines->data) && !empty($invoice->lines->data)) {
                foreach ($invoice->lines->data as $line) {
                    if (isset($line->period) && isset($line->period->start) && isset($line->period->end)) {
                        Subscriptions\update_transaction_meta($transaction_id, '_period_start', date('Y-m-d H:i:s', $line->period->start));
                        Subscriptions\update_transaction_meta($transaction_id, '_period_end', date('Y-m-d H:i:s', $line->period->end));
                        break;
                    }
                }
            }
        }
        
        // Update renewal count
        Subscriptions\update_subscription($subscription->id, [
            'renewal_count' => $subscription->renewal_count + 1,
            'last_payment_at' => current_time('mysql'),
        ]);
        
        // Send payment receipt email
        if (function_exists('\\Members\\Subscriptions\\send_payment_receipt_email')) {
            $transaction = Subscriptions\get_transaction($transaction_id);
            if ($transaction) {
                Subscriptions\send_payment_receipt_email($transaction);
            }
        }
    }
    
    /**
     * Handle invoice payment failed event
     *
     * @param \Stripe\Invoice $invoice
     */
    protected function handle_invoice_payment_failed($invoice) {
        Subscriptions\log_message(
            'Processing invoice.payment_failed webhook',
            'info',
            ['invoice_id' => $invoice->id]
        );
        
        if (empty($invoice->subscription)) {
            Subscriptions\log_message(
                'No subscription found in invoice',
                'info',
                ['invoice_id' => $invoice->id]
            );
            return;
        }
        
        // Find subscription by Stripe subscription ID
        $subscription = $this->get_subscription_by_stripe_id($invoice->subscription);
        
        if (!$subscription) {
            Subscriptions\log_message(
                'No matching subscription found in database',
                'warning',
                ['stripe_subscription_id' => $invoice->subscription]
            );
            return;
        }
        
        // Get payment attempts
        $payment_attempts = isset($invoice->attempt_count) ? intval($invoice->attempt_count) : 1;
        
        // If payment has failed multiple times, update subscription status to suspended
        if ($payment_attempts >= 3 && $subscription->status === 'active') {
            Subscriptions\log_message(
                'Updating subscription status to suspended due to multiple failed payments',
                'info',
                ['subscription_id' => $subscription->id, 'attempts' => $payment_attempts]
            );
            
            Subscriptions\update_subscription($subscription->id, [
                'status' => 'suspended',
            ]);
            
            // Send suspension email if implemented
            if (function_exists('\\Members\\Subscriptions\\send_subscription_suspended_email')) {
                Subscriptions\send_subscription_suspended_email($subscription);
            }
        }
        
        // Check if we already have a transaction for this invoice
        $existing_transaction = $this->get_transaction_by_stripe_id($invoice->id);
        
        if ($existing_transaction) {
            // Update transaction status
            Subscriptions\log_message(
                'Updating transaction status to failed',
                'info',
                ['transaction_id' => $existing_transaction->id]
            );
            
            Subscriptions\update_transaction($existing_transaction->id, [
                'status' => 'failed',
                'failed_at' => current_time('mysql'),
            ]);
        } else {
            // Create a failed transaction
            $transaction_id = Subscriptions\create_transaction([
                'user_id' => $subscription->user_id,
                'product_id' => $subscription->product_id,
                'amount' => $invoice->amount_due / 100, // Convert from cents
                'total' => $invoice->amount_due / 100,
                'trans_num' => $invoice->id,
                'status' => 'failed',
                'txn_type' => 'payment',
                'gateway' => $this->id,
                'subscription_id' => $subscription->id,
                'failed_at' => current_time('mysql'),
            ]);
            
            Subscriptions\log_message(
                'Created new failed transaction for invoice',
                'info',
                ['transaction_id' => $transaction_id, 'invoice_id' => $invoice->id]
            );
        }
        
        // Send failed payment email if implemented
        if (function_exists('\\Members\\Subscriptions\\send_failed_payment_email')) {
            $transaction = $existing_transaction ?: Subscriptions\get_transaction_by_trans_num($invoice->id);
            if ($transaction) {
                Subscriptions\send_failed_payment_email($transaction);
            }
        }
    }
    
    /**
     * Handle subscription deleted event
     *
     * @param \Stripe\Subscription $stripe_subscription
     */
    protected function handle_subscription_deleted($stripe_subscription) {
        Subscriptions\log_message(
            'Processing customer.subscription.deleted webhook',
            'info',
            ['stripe_subscription_id' => $stripe_subscription->id]
        );
        
        // Find subscription by Stripe subscription ID
        $subscription = $this->get_subscription_by_stripe_id($stripe_subscription->id);
        
        if (!$subscription) {
            Subscriptions\log_message(
                'No matching subscription found in database',
                'warning',
                ['stripe_subscription_id' => $stripe_subscription->id]
            );
            return;
        }
        
        // Update subscription status
        Subscriptions\log_message(
            'Updating subscription status to cancelled',
            'info',
            ['subscription_id' => $subscription->id]
        );
        
        Subscriptions\update_subscription($subscription->id, [
            'status' => 'cancelled',
            'cancelled_at' => current_time('mysql'),
        ]);
        
        // Send cancellation email
        if (function_exists('\\Members\\Subscriptions\\send_cancelled_subscription_email')) {
            Subscriptions\send_cancelled_subscription_email($subscription);
        }
    }
    
    /**
     * Handle subscription updated event
     *
     * @param \Stripe\Subscription $stripe_subscription
     */
    protected function handle_subscription_updated($stripe_subscription) {
        Subscriptions\log_message(
            'Processing customer.subscription.updated webhook',
            'info',
            ['stripe_subscription_id' => $stripe_subscription->id]
        );
        
        // Find subscription by Stripe subscription ID
        $subscription = $this->get_subscription_by_stripe_id($stripe_subscription->id);
        
        if (!$subscription) {
            Subscriptions\log_message(
                'No matching subscription found in database',
                'warning',
                ['stripe_subscription_id' => $stripe_subscription->id]
            );
            return;
        }
        
        $status_map = [
            'active' => 'active',
            'trialing' => 'active', // Treat trialing as active
            'past_due' => 'active', // Keep as active to allow retries
            'canceled' => 'cancelled',
            'unpaid' => 'suspended',
            'incomplete' => 'pending',
            'incomplete_expired' => 'expired',
        ];
        
        $stripe_status = $stripe_subscription->status;
        $new_status = isset($status_map[$stripe_status]) ? $status_map[$stripe_status] : $subscription->status;
        
        // Update if status changed
        if ($new_status !== $subscription->status) {
            Subscriptions\log_message(
                sprintf('Updating subscription status from %s to %s', $subscription->status, $new_status),
                'info',
                ['subscription_id' => $subscription->id]
            );
            
            $update_data = ['status' => $new_status];
            
            // Add cancellation date if cancelled
            if ($new_status === 'cancelled') {
                $update_data['cancelled_at'] = current_time('mysql');
            }
            
            Subscriptions\update_subscription($subscription->id, $update_data);
            
            // Send appropriate emails based on status change
            if ($new_status === 'cancelled' && function_exists('\\Members\\Subscriptions\\send_cancelled_subscription_email')) {
                Subscriptions\send_cancelled_subscription_email($subscription);
            } elseif ($new_status === 'active' && $subscription->status !== 'active' && function_exists('\\Members\\Subscriptions\\send_resumed_subscription_email')) {
                Subscriptions\send_resumed_subscription_email($subscription);
            }
        }
        
        // Update next payment date if changed
        if (isset($stripe_subscription->current_period_end) && $stripe_subscription->current_period_end) {
            $next_payment_date = date('Y-m-d H:i:s', $stripe_subscription->current_period_end);
            
            if (empty($subscription->next_payment_at) || $subscription->next_payment_at !== $next_payment_date) {
                Subscriptions\update_subscription($subscription->id, [
                    'next_payment_at' => $next_payment_date,
                    'expires_at' => $next_payment_date,
                ]);
                
                Subscriptions\log_message(
                    'Updated subscription renewal date',
                    'info',
                    [
                        'subscription_id' => $subscription->id,
                        'next_payment_date' => $next_payment_date
                    ]
                );
            }
        }
    }
    
    /**
     * Handle payment intent succeeded event
     *
     * @param \Stripe\PaymentIntent $payment_intent
     */
    protected function handle_payment_intent_succeeded($payment_intent) {
        Subscriptions\log_message(
            'Processing payment_intent.succeeded webhook',
            'info',
            ['payment_intent_id' => $payment_intent->id]
        );
        
        // Check if we already have a transaction for this payment intent
        $existing_transaction = $this->get_transaction_by_stripe_id($payment_intent->id);
        
        if ($existing_transaction) {
            // Update transaction status if needed
            if ($existing_transaction->status !== 'complete') {
                Subscriptions\log_message(
                    'Updating transaction status to complete',
                    'info',
                    ['transaction_id' => $existing_transaction->id]
                );
                
                Subscriptions\update_transaction($existing_transaction->id, [
                    'status' => 'complete',
                    'completed_at' => current_time('mysql'),
                ]);
                
                // Send payment receipt email
                if (function_exists('\\Members\\Subscriptions\\send_payment_receipt_email')) {
                    Subscriptions\send_payment_receipt_email($existing_transaction);
                }
            }
        }
    }
    
    /**
     * Handle payment intent failed event
     *
     * @param \Stripe\PaymentIntent $payment_intent
     */
    protected function handle_payment_intent_failed($payment_intent) {
        Subscriptions\log_message(
            'Processing payment_intent.payment_failed webhook',
            'info',
            ['payment_intent_id' => $payment_intent->id]
        );
        
        // Check if we already have a transaction for this payment intent
        $existing_transaction = $this->get_transaction_by_stripe_id($payment_intent->id);
        
        if ($existing_transaction) {
            // Get the error message
            $error_message = '';
            if (isset($payment_intent->last_payment_error) && isset($payment_intent->last_payment_error->message)) {
                $error_message = $payment_intent->last_payment_error->message;
            }
            
            Subscriptions\log_message(
                'Updating transaction status to failed',
                'info',
                [
                    'transaction_id' => $existing_transaction->id,
                    'error' => $error_message
                ]
            );
            
            // Update transaction status
            Subscriptions\update_transaction($existing_transaction->id, [
                'status' => 'failed',
                'failed_at' => current_time('mysql'),
            ]);
            
            // Save error message in transaction meta if provided
            if (!empty($error_message)) {
                Subscriptions\update_transaction_meta($existing_transaction->id, '_error_message', $error_message);
            }
            
            // Send failed payment email
            if (function_exists('\\Members\\Subscriptions\\send_failed_payment_email')) {
                Subscriptions\send_failed_payment_email($existing_transaction);
            }
        }
    }
    
    /**
     * Handle charge refunded event
     *
     * @param \Stripe\Charge $charge
     */
    protected function handle_charge_refunded($charge) {
        // Find transaction by charge ID
        $transaction = $this->get_transaction_by_stripe_id($charge->id);
        
        if (!$transaction) {
            return;
        }
        
        // Update transaction status
        Subscriptions\update_transaction($transaction->id, [
            'status' => 'refunded',
        ]);
    }
    
    /**
     * Get subscription by Stripe subscription ID
     *
     * @param string $subscr_id
     * @return object|null
     */
    protected function get_subscription_by_stripe_id($subscr_id) {
        global $wpdb;
        
        $table = Subscriptions\get_subscriptions_table_name();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE subscr_id = %s AND gateway = %s",
            $subscr_id,
            $this->id
        ));
    }
    
    /**
     * Get transaction by Stripe transaction ID (invoice ID or charge ID)
     *
     * @param string $trans_num
     * @return object|null
     */
    protected function get_transaction_by_stripe_id($trans_num) {
        return Subscriptions\get_transaction_by_trans_num($trans_num);
    }
    
    /**
     * Get or create customer in Stripe
     *
     * @param array $payment_data
     * @return \Stripe\Customer
     */
    protected function get_or_create_customer($payment_data) {
        // Get user
        $user = get_userdata($payment_data['user_id']);
        
        if (!$user) {
            throw new \Exception(__('User not found.', 'members'));
        }
        
        // Check if customer already exists
        $customer_id = get_user_meta($user->ID, '_members_stripe_customer_id', true);
        
        if (!empty($customer_id)) {
            try {
                // Try to retrieve existing customer
                $customer = \Stripe\Customer::retrieve($customer_id);
                
                // Update customer info if needed
                if ($customer->email !== $user->user_email || empty($customer->name)) {
                    $customer = \Stripe\Customer::update($customer_id, [
                        'email' => $user->user_email,
                        'name' => $user->display_name,
                    ]);
                }
                
                return $customer;
            } catch (\Exception $e) {
                // Customer might have been deleted, create a new one
            }
        }
        
        // Create new customer
        $customer = \Stripe\Customer::create([
            'email' => $user->user_email,
            'name' => $user->display_name,
            'metadata' => [
                'user_id' => $user->ID,
            ],
        ]);
        
        // Save customer ID
        update_user_meta($user->ID, '_members_stripe_customer_id', $customer->id);
        
        return $customer;
    }
    
    /**
     * Attach payment method to customer
     *
     * @param string $payment_method_id
     * @param string $customer_id
     */
    protected function attach_payment_method_to_customer($payment_method_id, $customer_id) {
        try {
            $payment_method = \Stripe\PaymentMethod::retrieve($payment_method_id);
            $payment_method->attach(['customer' => $customer_id]);
        } catch (\Exception $e) {
            // Payment method might already be attached
        }
    }
    
    /**
     * Convert amount to Stripe format (cents)
     *
     * @param float $amount
     * @return int
     */
    protected function get_stripe_amount($amount) {
        return (int) round($amount * 100);
    }
    
    /**
     * Get statement descriptor
     *
     * @return string
     */
    protected function get_statement_descriptor() {
        $descriptor = $this->get_setting('statement_descriptor', get_bloginfo('name'));
        
        // Stripe allows a maximum of 22 characters
        return substr(preg_replace('/[^a-zA-Z0-9 ]/m', '', $descriptor), 0, 22);
    }
    
    /**
     * Convert interval to Stripe format
     *
     * @param string $interval
     * @return string
     */
    protected function convert_interval_to_stripe($interval) {
        switch ($interval) {
            case 'day':
                return 'day';
            case 'week':
                return 'week';
            case 'month':
                return 'month';
            case 'year':
                return 'year';
            default:
                return 'month';
        }
    }
}