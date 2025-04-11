<?php

namespace Members\Subscriptions\Emails;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renewal Reminder Email
 */
class Renewal_Reminder_Email extends Subscription_Email {

    /**
     * Number of days until renewal
     *
     * @var int
     */
    protected $days;

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'renewal_reminder';
        $this->title = __('Renewal Reminder', 'members');
        $this->description = __('Email sent to users to remind them of an upcoming renewal.', 'members');

        parent::__construct();
    }

    /**
     * Get default subject
     *
     * @return string
     */
    public function get_default_subject() {
        return __('Your membership renewal is coming up', 'members');
    }

    /**
     * Get default content
     *
     * @return string
     */
    public function get_default_content() {
        return __(
            "Hello {user_name},\n\n" .
            "Your membership for {product_name} will renew in {days} days on {renewal_date}.\n\n" .
            "Subscription details:\n" .
            "- Membership: {product_name}\n" .
            "- Amount: {amount}\n" .
            "- Next billing date: {renewal_date}\n\n" .
            "If you have any questions or want to make changes to your subscription, please contact us.\n\n" .
            "Thanks,\n" .
            "{site_name}",
            'members'
        );
    }

    /**
     * Set subscription and days (compatibility wrapper)
     *
     * @param mixed $data Either an array of data or a subscription object
     * @param int $days Optional number of days until renewal
     * @return self
     */
    public function set_data($data, $days = null) {
        // If $data is an object (subscription), convert to array format for parent method
        if (is_object($data) && $days !== null) {
            $subscription = $data;
            // Call parent with array format
            parent::set_data(['subscription' => $subscription, 'days' => $days]);
            
            // Set local properties
            $this->subscription = $subscription;
            $this->days = $days;
            $this->recipient = $this->get_user_email($subscription->user_id);
            
            // Also call set_subscription since that sets up other needed data
            $this->set_subscription($subscription);
        } else {
            // Normal array data format, call parent
            parent::set_data($data);
            
            // Extract days if present in array
            if (is_array($data) && isset($data['days'])) {
                $this->days = $data['days'];
            }
            
            // Extract subscription if present in array and not already set
            if (is_array($data) && isset($data['subscription']) && !isset($this->subscription)) {
                $this->subscription = $data['subscription'];
                $this->recipient = $this->get_user_email($this->subscription->user_id);
                $this->set_subscription($this->subscription);
            }
        }
        
        return $this;
    }

    /**
     * Get email tokens
     *
     * @return array
     */
    public function get_tokens() {
        $tokens = parent::get_tokens();
        
        $tokens['{days}'] = $this->days;
        $tokens['{renewal_date}'] = date_i18n(get_option('date_format'), strtotime($this->subscription->next_payment_at));
        
        return $tokens;
    }
}