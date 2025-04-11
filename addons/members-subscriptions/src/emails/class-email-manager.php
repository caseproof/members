<?php

namespace Members\Subscriptions\Emails;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions\Exceptions\Members_Exception;

/**
 * Email manager class
 */
class Email_Manager {

    /**
     * Instance of the email manager
     * 
     * @var self
     */
    private static $instance;
    
    /**
     * Available emails
     * 
     * @var array
     */
    private $emails = [];
    
    /**
     * Get the email manager instance (singleton)
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
        $this->register_emails();
    }
    
    /**
     * Register available emails
     */
    private function register_emails() {
        $this->emails = [
            'new_subscription' => new New_Subscription_Email(),
            'cancelled_subscription' => new Cancelled_Subscription_Email(),
            'payment_receipt' => new Payment_Receipt_Email(),
            'renewal_reminder' => new Renewal_Reminder_Email(),
            'renewal_receipt' => new Renewal_Receipt_Email(),
        ];
    }
    
    /**
     * Get email by ID
     * 
     * @param string $id
     * @return Email|null
     */
    public function get_email($id) {
        return isset($this->emails[$id]) ? $this->emails[$id] : null;
    }
    
    /**
     * Get all emails
     * 
     * @return array
     */
    public function get_emails() {
        return $this->emails;
    }
    
    /**
     * Send a new subscription email
     * 
     * @param object $subscription The subscription object
     * @param string $email_type The email type (admin or user)
     * @return bool
     * @throws Members_Exception
     */
    public function send_new_subscription_email($subscription, $email_type = 'user') {
        $email = $this->get_email('new_subscription');
        
        if (!$email) {
            throw new Members_Exception(
                __('New subscription email not found.', 'members'),
                404,
                null,
                ['email_id' => 'new_subscription']
            );
        }
        
        // Clear previous recipients
        $email->set_recipients([]);
        
        // Set subscription data
        $email->set_subscription($subscription);
        
        // Set recipient based on email type
        if ($email_type === 'admin') {
            $email->add_recipient(get_option('admin_email'));
            $email->set_subject(__('New subscription on your site', 'members'));
        } else {
            $user = get_userdata($subscription->user_id);
            
            if (!$user) {
                throw new Members_Exception(
                    __('User not found for subscription.', 'members'),
                    404,
                    null,
                    ['user_id' => $subscription->user_id]
                );
            }
            
            $email->add_recipient($user->user_email);
            $email->set_subject(__('Your new subscription on {site_name}', 'members'));
        }
        
        // Send email
        return $email->send();
    }
    
    /**
     * Send a cancelled subscription email
     * 
     * @param object $subscription The subscription object
     * @param string $email_type The email type (admin or user)
     * @return bool
     * @throws Members_Exception
     */
    public function send_cancelled_subscription_email($subscription, $email_type = 'user') {
        $email = $this->get_email('cancelled_subscription');
        
        if (!$email) {
            throw new Members_Exception(
                __('Cancelled subscription email not found.', 'members'),
                404,
                null,
                ['email_id' => 'cancelled_subscription']
            );
        }
        
        // Clear previous recipients
        $email->set_recipients([]);
        
        // Set subscription data
        $email->set_subscription($subscription);
        
        // Set recipient based on email type
        if ($email_type === 'admin') {
            $email->add_recipient(get_option('admin_email'));
            $email->set_subject(__('Subscription cancelled on your site', 'members'));
        } else {
            $user = get_userdata($subscription->user_id);
            
            if (!$user) {
                throw new Members_Exception(
                    __('User not found for subscription.', 'members'),
                    404,
                    null,
                    ['user_id' => $subscription->user_id]
                );
            }
            
            $email->add_recipient($user->user_email);
            $email->set_subject(__('Your subscription on {site_name} has been cancelled', 'members'));
        }
        
        // Send email
        return $email->send();
    }
    
    /**
     * Send a payment receipt email
     * 
     * @param object $transaction The transaction object
     * @param string $email_type The email type (admin or user)
     * @return bool
     * @throws Members_Exception
     */
    public function send_payment_receipt_email($transaction, $email_type = 'user') {
        $email = $this->get_email('payment_receipt');
        
        if (!$email) {
            throw new Members_Exception(
                __('Payment receipt email not found.', 'members'),
                404,
                null,
                ['email_id' => 'payment_receipt']
            );
        }
        
        // Clear previous recipients
        $email->set_recipients([]);
        
        // Set transaction data
        $email->set_transaction($transaction);
        
        // Set recipient based on email type
        if ($email_type === 'admin') {
            $email->add_recipient(get_option('admin_email'));
            $email->set_subject(__('New payment on your site', 'members'));
        } else {
            $user = get_userdata($transaction->user_id);
            
            if (!$user) {
                throw new Members_Exception(
                    __('User not found for transaction.', 'members'),
                    404,
                    null,
                    ['user_id' => $transaction->user_id]
                );
            }
            
            $email->add_recipient($user->user_email);
            $email->set_subject(__('Your payment receipt for {site_name}', 'members'));
        }
        
        // Send email
        return $email->send();
    }
}