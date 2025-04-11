<?php

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions\Emails\Email_Manager;
use Members\Subscriptions\Emails\Renewal_Reminder_Email;
use Members\Subscriptions\Emails\Renewal_Receipt_Email;

/**
 * Send a new subscription email
 * 
 * @param object $subscription The subscription object
 * @param string $email_type The email type (admin, user, or both)
 * @return bool
 */
function send_new_subscription_email($subscription, $email_type = 'both') {
    $email_manager = Email_Manager::get_instance();
    $success = true;
    
    try {
        if ($email_type === 'both' || $email_type === 'user') {
            $success = $email_manager->send_new_subscription_email($subscription, 'user') && $success;
        }
        
        if ($email_type === 'both' || $email_type === 'admin') {
            $success = $email_manager->send_new_subscription_email($subscription, 'admin') && $success;
        }
    } catch (\Exception $e) {
        log_message('Error sending new subscription email: ' . $e->getMessage(), 'error', [
            'subscription_id' => $subscription->id,
            'email_type' => $email_type,
            'exception' => $e->getMessage(),
        ]);
        
        $success = false;
    }
    
    return $success;
}

/**
 * Send a cancelled subscription email
 * 
 * @param object $subscription The subscription object
 * @param string $email_type The email type (admin, user, or both)
 * @return bool
 */
function send_cancelled_subscription_email($subscription, $email_type = 'both') {
    $email_manager = Email_Manager::get_instance();
    $success = true;
    
    try {
        if ($email_type === 'both' || $email_type === 'user') {
            $success = $email_manager->send_cancelled_subscription_email($subscription, 'user') && $success;
        }
        
        if ($email_type === 'both' || $email_type === 'admin') {
            $success = $email_manager->send_cancelled_subscription_email($subscription, 'admin') && $success;
        }
    } catch (\Exception $e) {
        log_message('Error sending cancelled subscription email: ' . $e->getMessage(), 'error', [
            'subscription_id' => $subscription->id,
            'email_type' => $email_type,
            'exception' => $e->getMessage(),
        ]);
        
        $success = false;
    }
    
    return $success;
}

/**
 * Send a payment receipt email
 * 
 * @param object $transaction The transaction object
 * @param string $email_type The email type (admin, user, or both)
 * @return bool
 */
function send_payment_receipt_email($transaction, $email_type = 'both') {
    $email_manager = Email_Manager::get_instance();
    $success = true;
    
    try {
        if ($email_type === 'both' || $email_type === 'user') {
            $success = $email_manager->send_payment_receipt_email($transaction, 'user') && $success;
        }
        
        if ($email_type === 'both' || $email_type === 'admin') {
            $success = $email_manager->send_payment_receipt_email($transaction, 'admin') && $success;
        }
    } catch (\Exception $e) {
        log_message('Error sending payment receipt email: ' . $e->getMessage(), 'error', [
            'transaction_id' => $transaction->id,
            'email_type' => $email_type,
            'exception' => $e->getMessage(),
        ]);
        
        $success = false;
    }
    
    return $success;
}

/**
 * Send a renewal reminder email
 * 
 * @param object $subscription The subscription object
 * @param int    $days        Number of days until renewal
 * @param string $email_type  The email type (admin, user, or both)
 * @return bool
 */
function send_renewal_reminder_email($subscription, $days, $email_type = 'user') {
    $email_manager = Email_Manager::get_instance();
    $success = true;
    
    try {
        // Create the email
        $email = new Renewal_Reminder_Email();
        $email->set_data($subscription, $days);
        
        if ($email_type === 'both' || $email_type === 'user') {
            $email->set_recipient_type('user');
            $success = $email->send() && $success;
        }
        
        if ($email_type === 'both' || $email_type === 'admin') {
            $email->set_recipient_type('admin');
            $success = $email->send() && $success;
        }
    } catch (\Exception $e) {
        log_message('Error sending renewal reminder email: ' . $e->getMessage(), 'error', [
            'subscription_id' => $subscription->id,
            'days' => $days,
            'email_type' => $email_type,
            'exception' => $e->getMessage(),
        ]);
        
        $success = false;
    }
    
    return $success;
}

/**
 * Send a renewal receipt email
 * 
 * @param object $transaction The transaction object
 * @param string $email_type  The email type (admin, user, or both)
 * @return bool
 */
function send_renewal_receipt_email($transaction, $email_type = 'both') {
    $email_manager = Email_Manager::get_instance();
    $success = true;
    
    try {
        // Create the email
        $email = new Renewal_Receipt_Email();
        $email->set_transaction($transaction);
        
        if ($email_type === 'both' || $email_type === 'user') {
            $email->set_recipient_type('user');
            $success = $email->send() && $success;
        }
        
        if ($email_type === 'both' || $email_type === 'admin') {
            $email->set_recipient_type('admin');
            $success = $email->send() && $success;
        }
    } catch (\Exception $e) {
        log_message('Error sending renewal receipt email: ' . $e->getMessage(), 'error', [
            'transaction_id' => $transaction->id,
            'email_type' => $email_type,
            'exception' => $e->getMessage(),
        ]);
        
        $success = false;
    }
    
    return $success;
}