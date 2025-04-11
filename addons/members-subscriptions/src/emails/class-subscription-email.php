<?php

namespace Members\Subscriptions\Emails;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Base subscription email class
 */
abstract class Subscription_Email extends Email {

    /**
     * Subscription data
     * 
     * @var object
     */
    protected $subscription;
    
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
     * Set subscription data
     * 
     * @param object $subscription
     * @return self
     */
    public function set_subscription($subscription) {
        $this->subscription = $subscription;
        
        // Set user and product data
        if (isset($subscription->user_id)) {
            $this->user = get_userdata($subscription->user_id);
        }
        
        if (isset($subscription->product_id)) {
            $this->product = get_post($subscription->product_id);
        }
        
        // Add subscription data
        $this->add_data('subscription', $subscription);
        $this->add_data('user', $this->user);
        $this->add_data('product', $this->product);
        
        return $this;
    }
    
    /**
     * Get subscription data
     * 
     * @return object
     */
    public function get_subscription() {
        return $this->subscription;
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
        
        if ($this->subscription) {
            $placeholders['subscription_id'] = $this->subscription->id;
            $placeholders['subscription_status'] = $this->subscription->status;
            $placeholders['subscription_amount'] = $this->subscription->price;
            
            // Format dates
            if (!empty($this->subscription->created_at)) {
                $placeholders['subscription_date'] = date_i18n(
                    get_option('date_format'),
                    strtotime($this->subscription->created_at)
                );
            }
            
            if (!empty($this->subscription->expires_at)) {
                $placeholders['subscription_expiry'] = date_i18n(
                    get_option('date_format'),
                    strtotime($this->subscription->expires_at)
                );
            }
            
            // Format gateway
            $gateways = [
                'stripe' => __('Stripe', 'members'),
                'paypal' => __('PayPal', 'members'),
                'manual' => __('Manual', 'members'),
            ];
            
            $placeholders['subscription_gateway'] = isset($gateways[$this->subscription->gateway])
                ? $gateways[$this->subscription->gateway]
                : $this->subscription->gateway;
                
            // Generate login link
            $login_url = wp_login_url();
            $placeholders['login_link'] = '<a href="' . esc_url($login_url) . '">' . __('Login', 'members') . '</a>';
            
            // Get account page URL
            $account_page_id = get_option('members_account_page');
            $account_url = $account_page_id ? get_permalink($account_page_id) : home_url();
            $placeholders['account_link'] = '<a href="' . esc_url($account_url) . '">' . __('My Account', 'members') . '</a>';
        }
        
        return $placeholders;
    }
}