<?php

namespace Members\Subscriptions\Emails;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Cancelled subscription email
 */
class Cancelled_Subscription_Email extends Subscription_Email {

    /**
     * Email ID
     * 
     * @var string
     */
    protected $id = 'cancelled_subscription';
    
    /**
     * Email title
     * 
     * @var string
     */
    protected $title = 'Cancelled Subscription';
    
    /**
     * Email description
     * 
     * @var string
     */
    protected $description = 'Email sent to the member and admin when a subscription is cancelled.';
    
    /**
     * Email subject
     * 
     * @var string
     */
    protected $subject = 'Subscription cancelled on {site_name}';
    
    /**
     * Email template
     * 
     * @var string
     */
    protected $template = 'subscription';
    
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
        if (!$this->subscription || !$this->user || !$this->product) {
            return '';
        }
        
        $content = '<p>' . sprintf(
            __('Hello %s,', 'members'),
            $this->user->display_name
        ) . '</p>';
        
        $content .= '<p>' . sprintf(
            __('Your subscription to %s on %s has been cancelled.', 'members'),
            '<strong>' . $this->product->post_title . '</strong>',
            get_bloginfo('name')
        ) . '</p>';
        
        $content .= '<h3>' . __('Subscription Details', 'members') . '</h3>';
        
        $content .= '<ul>';
        $content .= '<li>' . sprintf(__('Product: %s', 'members'), $this->product->post_title) . '</li>';
        $content .= '<li>' . sprintf(__('Status: %s', 'members'), $this->subscription->status) . '</li>';
        $content .= '<li>' . sprintf(__('Start Date: %s', 'members'), date_i18n(get_option('date_format'), strtotime($this->subscription->created_at))) . '</li>';
        
        if (!empty($this->subscription->cancelled_at)) {
            $content .= '<li>' . sprintf(__('Cancellation Date: %s', 'members'), date_i18n(get_option('date_format'), strtotime($this->subscription->cancelled_at))) . '</li>';
        }
        
        $content .= '</ul>';
        
        $content .= '<p>' . sprintf(
            __('You can view your subscription details from your %s.', 'members'),
            '<a href="' . esc_url(get_permalink(get_option('members_account_page'))) . '">' . __('account page', 'members') . '</a>'
        ) . '</p>';
        
        $content .= '<p>' . __('We\'re sorry to see you go! If you have any feedback or questions, please contact us.', 'members') . '</p>';
        
        // Replace placeholders
        return $this->replace_placeholders($content);
    }
}