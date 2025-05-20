<?php

namespace Members\Subscriptions\Emails;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Base transaction email class
 */
abstract class Transaction_Email extends Email {

    /**
     * Transaction data
     * 
     * @var object
     */
    protected $transaction;
    
    /**
     * User data
     * 
     * @var \WP_User
     */
    protected $user;
    
    /**
     * Product data
     * 
     * @var \WP_Post
     */
    protected $product;
    
    /**
     * Subscription data
     * 
     * @var object
     */
    protected $subscription;
    
    /**
     * Set transaction data
     * 
     * @param object $transaction
     * @return self
     */
    public function set_transaction($transaction) {
        $this->transaction = $transaction;
        
        // Set user and product data
        if (isset($transaction->user_id)) {
            $this->user = get_userdata($transaction->user_id);
        }
        
        if (isset($transaction->product_id)) {
            $this->product = get_post($transaction->product_id);
        }
        
        // Get subscription if applicable
        if (isset($transaction->subscription_id) && function_exists('\\Members\\Subscriptions\\get_subscription')) {
            $this->subscription = \Members\Subscriptions\get_subscription($transaction->subscription_id);
        }
        
        // Add transaction data
        $this->add_data('transaction', $transaction);
        $this->add_data('user', $this->user);
        $this->add_data('product', $this->product);
        $this->add_data('subscription', $this->subscription);
        
        return $this;
    }
    
    /**
     * Get transaction data
     * 
     * @return object
     */
    public function get_transaction() {
        return $this->transaction;
    }
    
    /**
     * Get placeholders for email content
     * 
     * @return array
     */
    protected function get_placeholders() {
        $placeholders = parent::get_placeholders();
        
        if ($this->user) {
            $placeholders['user_name'] = $this->user->display_name;
            $placeholders['user_email'] = $this->user->user_email;
            $placeholders['user_login'] = $this->user->user_login;
            $placeholders['user_id'] = $this->user->ID;
        }
        
        if ($this->product) {
            $placeholders['product_name'] = $this->product->post_title;
            $placeholders['product_id'] = $this->product->ID;
        }
        
        if ($this->transaction) {
            $placeholders['transaction_id'] = $this->transaction->id;
            $placeholders['transaction_status'] = $this->transaction->status;
            $placeholders['transaction_amount'] = $this->transaction->amount;
            $placeholders['transaction_total'] = $this->transaction->total;
            $placeholders['transaction_number'] = $this->transaction->trans_num;
            
            // Format tax data
            if (!empty($this->transaction->tax_amount)) {
                $placeholders['transaction_tax'] = $this->transaction->tax_amount;
                $placeholders['transaction_tax_rate'] = $this->transaction->tax_rate . '%';
            }
            
            // Format dates
            if (!empty($this->transaction->created_at)) {
                $placeholders['transaction_date'] = date_i18n(
                    get_option('date_format'),
                    strtotime($this->transaction->created_at)
                );
            }
            
            // Format gateway
            $gateways = [
                'stripe' => __('Stripe', 'members'),
                'paypal' => __('PayPal', 'members'),
                'manual' => __('Manual', 'members'),
            ];
            
            $placeholders['transaction_gateway'] = isset($gateways[$this->transaction->gateway])
                ? $gateways[$this->transaction->gateway]
                : $this->transaction->gateway;
        }
        
        if ($this->subscription) {
            $placeholders['subscription_id'] = $this->subscription->id;
        }
        
        // Generate login link
        $login_url = wp_login_url();
        $placeholders['login_link'] = '<a href="' . esc_url($login_url) . '">' . __('Login', 'members') . '</a>';
        
        // Get account page URL
        $account_page_id = get_option('members_account_page');
        $account_url = $account_page_id ? get_permalink($account_page_id) : home_url();
        $placeholders['account_link'] = '<a href="' . esc_url($account_url) . '">' . __('My Account', 'members') . '</a>';
        
        return $placeholders;
    }
}