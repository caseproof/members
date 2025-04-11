<?php

namespace Members\Subscriptions\gateways;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions;

/**
 * Manual Gateway
 * For processing manual payments that need admin approval
 */
class Manual_Gateway extends Gateway {

    /**
     * Initialize the gateway
     */
    protected function init() {
        $this->id = 'manual';
        $this->name = __('Manual Payment', 'members');
        $this->description = __('Manual payments processed by the site administrator.', 'members');
        $this->supports_subscriptions = true;
        $this->supports_one_time = true;
        $this->supports_refunds = false;
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
                'label'   => __('Enable Manual Gateway', 'members'),
                'default' => true,
            ],
            'title' => [
                'title'   => __('Title', 'members'),
                'type'    => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'members'),
                'default' => __('Manual Payment', 'members'),
            ],
            'description' => [
                'title'   => __('Description', 'members'),
                'type'    => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'members'),
                'default' => __('Your payment will be processed manually by the site administrator. You will receive further instructions via email.', 'members'),
            ],
            'instructions' => [
                'title'   => __('Payment Instructions', 'members'),
                'type'    => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.', 'members'),
                'default' => __('Please send your payment to: Payment Address', 'members'),
            ],
            'auto_complete' => [
                'title'   => __('Auto Complete', 'members'),
                'type'    => 'checkbox',
                'label'   => __('Automatically complete transactions (not recommended)', 'members'),
                'default' => false,
                'description' => __('If checked, memberships will be activated immediately without admin approval.', 'members'),
            ],
        ];
    }
    
    /**
     * Payment fields displayed on checkout
     *
     * @return string
     */
    public function payment_fields() {
        $description = $this->get_setting('description');
        
        ob_start();
        
        if (!empty($description)) {
            echo '<p>' . wp_kses_post($description) . '</p>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Validate payment fields
     *
     * @param array $data
     * @return bool|\WP_Error
     */
    public function validate_payment_fields($data) {
        return true;
    }
    
    /**
     * Process payment
     *
     * @param array $payment_data
     * @return array
     */
    public function process_payment($payment_data) {
        try {
            // Create transaction record
            $transaction_data = [
                'user_id'    => $payment_data['user_id'],
                'product_id' => $payment_data['product_id'],
                'amount'     => $payment_data['amount'],
                'total'      => $payment_data['amount'],
                'trans_num'  => uniqid('manual-'),
                'status'     => $this->get_setting('auto_complete', false) ? 'complete' : 'pending',
                'txn_type'   => 'payment',
                'gateway'    => $this->id,
            ];
            
            $transaction_id = Subscriptions\create_transaction($transaction_data);
            
            if (!$transaction_id) {
                throw new \Exception(__('Error recording transaction.', 'members'));
            }
            
            return [
                'success'        => true,
                'transaction_id' => $transaction_id,
                'redirect'       => !empty($payment_data['redirect']) ? $payment_data['redirect'] : '',
                'message'        => $this->get_setting('auto_complete', false) 
                    ? __('Payment completed successfully.', 'members')
                    : __('Payment received. Awaiting admin approval.', 'members'),
            ];
            
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
        try {
            // Create subscription record
            $subscription_data = [
                'user_id'     => $payment_data['user_id'],
                'product_id'  => $payment_data['product_id'],
                'gateway'     => $this->id,
                'status'      => $this->get_setting('auto_complete', false) ? 'active' : 'pending',
                'subscr_id'   => uniqid('manual-sub-'),
                'period_type' => $payment_data['period_type'],
                'period'      => $payment_data['period'],
                'price'       => $payment_data['amount'],
                'total'       => $payment_data['amount'],
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
            
            // Create transaction for the first payment
            $transaction_data = [
                'user_id'         => $payment_data['user_id'],
                'product_id'      => $payment_data['product_id'],
                'amount'          => (!empty($payment_data['trial']) && !empty($payment_data['trial_days'])) ? 
                                      $payment_data['trial_amount'] : $payment_data['amount'],
                'total'           => (!empty($payment_data['trial']) && !empty($payment_data['trial_days'])) ? 
                                      $payment_data['trial_amount'] : $payment_data['amount'],
                'trans_num'       => uniqid('manual-txn-'),
                'status'          => $this->get_setting('auto_complete', false) ? 'complete' : 'pending',
                'txn_type'        => 'payment',
                'gateway'         => $this->id,
                'subscription_id' => $subscription_id,
            ];
            
            $transaction_id = Subscriptions\create_transaction($transaction_data);
            
            if (!$transaction_id) {
                throw new \Exception(__('Error recording transaction.', 'members'));
            }
            
            return [
                'success'         => true,
                'subscription_id' => $subscription_id,
                'transaction_id'  => $transaction_id,
                'redirect'        => !empty($payment_data['redirect']) ? $payment_data['redirect'] : '',
                'message'         => $this->get_setting('auto_complete', false) 
                                     ? __('Subscription created successfully.', 'members')
                                     : __('Subscription received. Awaiting admin approval.', 'members'),
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}