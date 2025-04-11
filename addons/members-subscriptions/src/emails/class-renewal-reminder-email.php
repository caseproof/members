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
     * Set subscription and days
     *
     * @param object $subscription Subscription object
     * @param int    $days        Number of days until renewal
     */
    public function set_data($subscription, $days) {
        $this->subscription = $subscription;
        $this->days = $days;
        $this->recipient = $this->get_user_email($subscription->user_id);
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