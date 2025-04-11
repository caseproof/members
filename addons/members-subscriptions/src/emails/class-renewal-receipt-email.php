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
}