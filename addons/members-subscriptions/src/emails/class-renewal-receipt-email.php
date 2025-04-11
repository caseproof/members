<?php

namespace Members\Subscriptions\Emails;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renewal Receipt Email
 */
class Renewal_Receipt_Email extends Transaction_Email {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'renewal_receipt';
        $this->title = __('Renewal Receipt', 'members');
        $this->description = __('Email sent to users after a successful subscription renewal.', 'members');

        parent::__construct();
    }

    /**
     * Get default subject
     *
     * @return string
     */
    public function get_default_subject() {
        return __('Your membership has been renewed', 'members');
    }

    /**
     * Get default content
     *
     * @return string
     */
    public function get_default_content() {
        return __(
            "Hello {user_name},\n\n" .
            "Your membership for {product_name} has been successfully renewed.\n\n" .
            "Receipt details:\n" .
            "- Transaction ID: {transaction_id}\n" .
            "- Date: {transaction_date}\n" .
            "- Amount: {amount}\n" .
            "- Next renewal date: {next_renewal_date}\n\n" .
            "Thank you for your continued membership!\n\n" .
            "If you have any questions, please contact us.\n\n" .
            "Thanks,\n" .
            "{site_name}",
            'members'
        );
    }

    /**
     * Get email tokens
     *
     * @return array
     */
    public function get_tokens() {
        $tokens = parent::get_tokens();
        
        // Get subscription
        $subscription = \Members\Subscriptions\get_subscription($this->transaction->subscription_id);
        
        if ($subscription) {
            $tokens['{next_renewal_date}'] = date_i18n(get_option('date_format'), strtotime($subscription->next_payment_at));
        } else {
            $tokens['{next_renewal_date}'] = 'N/A';
        }
        
        return $tokens;
    }
    
    /**
     * Get email content
     *
     * @return string
     */
    public function get_content() {
        if (!$this->transaction || !$this->user || !$this->product) {
            return '';
        }
        
        $content = '<p>' . sprintf(
            __('Hello %s,', 'members'),
            $this->user->display_name
        ) . '</p>';
        
        $content .= '<p>' . sprintf(
            __('Your membership for %s has been successfully renewed. This email serves as your receipt.', 'members'),
            '<strong>' . $this->product->post_title . '</strong>'
        ) . '</p>';
        
        $content .= '<h3>' . __('Payment Details', 'members') . '</h3>';
        
        $content .= '<table style="width: 100%; border-collapse: collapse;">';
        $content .= '<tr>';
        $content .= '<th style="text-align: left; border-bottom: 1px solid #ddd; padding: 8px;">' . __('Item', 'members') . '</th>';
        $content .= '<th style="text-align: right; border-bottom: 1px solid #ddd; padding: 8px;">' . __('Amount', 'members') . '</th>';
        $content .= '</tr>';
        
        $content .= '<tr>';
        $content .= '<td style="border-bottom: 1px solid #ddd; padding: 8px;">' . $this->product->post_title . ' (' . __('Renewal', 'members') . ')</td>';
        $content .= '<td style="text-align: right; border-bottom: 1px solid #ddd; padding: 8px;">' . number_format_i18n($this->transaction->amount, 2) . '</td>';
        $content .= '</tr>';
        
        // Add tax if applicable
        if (!empty($this->transaction->tax_amount) && $this->transaction->tax_amount > 0) {
            $content .= '<tr>';
            $content .= '<td style="border-bottom: 1px solid #ddd; padding: 8px;">' . __('Tax', 'members') . ' (' . $this->transaction->tax_rate . '%)</td>';
            $content .= '<td style="text-align: right; border-bottom: 1px solid #ddd; padding: 8px;">' . number_format_i18n($this->transaction->tax_amount, 2) . '</td>';
            $content .= '</tr>';
        }
        
        // Add total
        $content .= '<tr>';
        $content .= '<td style="font-weight: bold; padding: 8px;">' . __('Total', 'members') . '</td>';
        $content .= '<td style="text-align: right; font-weight: bold; padding: 8px;">' . number_format_i18n($this->transaction->total, 2) . '</td>';
        $content .= '</tr>';
        
        $content .= '</table>';
        
        $content .= '<h3>' . __('Transaction Information', 'members') . '</h3>';
        
        $content .= '<ul>';
        $content .= '<li>' . sprintf(__('Transaction ID: %s', 'members'), $this->transaction->trans_num) . '</li>';
        $content .= '<li>' . sprintf(__('Date: %s', 'members'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($this->transaction->created_at))) . '</li>';
        
        // Get subscription for next renewal date
        if ($this->transaction->subscription_id) {
            $subscription = \Members\Subscriptions\get_subscription($this->transaction->subscription_id);
            if ($subscription && !empty($subscription->next_payment_at)) {
                $content .= '<li>' . sprintf(__('Next Renewal Date: %s', 'members'), 
                            date_i18n(get_option('date_format'), strtotime($subscription->next_payment_at))) . '</li>';
            }
        }
        
        // Add gateway
        $gateways = [
            'stripe' => __('Stripe', 'members'),
            'paypal' => __('PayPal', 'members'),
            'manual' => __('Manual', 'members'),
        ];
        
        $gateway = isset($gateways[$this->transaction->gateway])
            ? $gateways[$this->transaction->gateway]
            : $this->transaction->gateway;
            
        $content .= '<li>' . sprintf(__('Payment Method: %s', 'members'), $gateway) . '</li>';
        
        $content .= '</ul>';
        
        $content .= '<p>' . sprintf(
            __('You can view your payment history from your %s.', 'members'),
            '<a href="' . esc_url(get_permalink(get_option('members_account_page'))) . '">' . __('account page', 'members') . '</a>'
        ) . '</p>';
        
        $content .= '<p>' . __('Thank you for your continued membership!', 'members') . '</p>';
        
        // Replace placeholders
        return $this->replace_placeholders($content);
    }
}