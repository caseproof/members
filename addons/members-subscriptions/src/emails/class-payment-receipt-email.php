<?php

namespace Members\Subscriptions\Emails;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Payment receipt email
 */
class Payment_Receipt_Email extends Transaction_Email {

    /**
     * Email ID
     * 
     * @var string
     */
    protected $id = 'payment_receipt';
    
    /**
     * Email title
     * 
     * @var string
     */
    protected $title = 'Payment Receipt';
    
    /**
     * Email description
     * 
     * @var string
     */
    protected $description = 'Email sent to the member and admin when a payment is completed.';
    
    /**
     * Email subject
     * 
     * @var string
     */
    protected $subject = 'Payment receipt for {site_name}';
    
    /**
     * Email template
     * 
     * @var string
     */
    protected $template = 'transaction';
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Set admin recipient
        $this->add_recipient(get_option('admin_email'));
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
            __('Thank you for your payment on %s. This email serves as your receipt.', 'members'),
            get_bloginfo('name')
        ) . '</p>';
        
        $content .= '<h3>' . __('Payment Details', 'members') . '</h3>';
        
        $content .= '<table style="width: 100%; border-collapse: collapse;">';
        $content .= '<tr>';
        $content .= '<th style="text-align: left; border-bottom: 1px solid #ddd; padding: 8px;">' . __('Item', 'members') . '</th>';
        $content .= '<th style="text-align: right; border-bottom: 1px solid #ddd; padding: 8px;">' . __('Amount', 'members') . '</th>';
        $content .= '</tr>';
        
        $content .= '<tr>';
        $content .= '<td style="border-bottom: 1px solid #ddd; padding: 8px;">' . $this->product->post_title . '</td>';
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
        
        $content .= '<p>' . __('Thank you for your business!', 'members') . '</p>';
        
        // Replace placeholders
        return $this->replace_placeholders($content);
    }
}