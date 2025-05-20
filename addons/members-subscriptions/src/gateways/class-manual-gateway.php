<?php

namespace Members\Subscriptions\gateways;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions;

/**
 * Manual Gateway
 * For processing manual payments that need admin approval
 */
class Manual_Gateway extends Gateway {

    /**
     * Initialize the gateway
     */
    protected function init() {
        $this->id = 'manual';
        $this->name = __('Manual Payment', 'members');
        $this->description = __('Manual payments processed by the site administrator.', 'members');
        $this->supports_subscriptions = true;
        $this->supports_one_time = true;
        $this->supports_refunds = false;
        
        // Add hooks for this gateway
        add_action('admin_post_members_manual_approve_transaction', [$this, 'admin_approve_transaction']);
        add_action('admin_post_members_manual_reject_transaction', [$this, 'admin_reject_transaction']);
        
        // Hook into subscription events
        add_action('members_subscription_renewal_needed', [$this, 'handle_manual_renewal']);
        
        // Admin notification hooks
        add_action('members_transaction_created', [$this, 'maybe_notify_admin_transaction']);
        add_action('members_subscription_created', [$this, 'maybe_notify_admin_subscription']);
    }
    
    /**
     * Get admin settings fields
     *
     * @return array
     */
    public function get_settings_fields() {
        return [
            'enabled' => [
                'title'   => __('Enable/Disable', 'members'),
                'type'    => 'checkbox',
                'label'   => __('Enable Manual Gateway', 'members'),
                'default' => true,
            ],
            'title' => [
                'title'   => __('Title', 'members'),
                'type'    => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'members'),
                'default' => __('Manual Payment', 'members'),
            ],
            'description' => [
                'title'   => __('Description', 'members'),
                'type'    => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'members'),
                'default' => __('Your payment will be processed manually by the site administrator. You will receive further instructions via email.', 'members'),
            ],
            'instructions' => [
                'title'   => __('Payment Instructions', 'members'),
                'type'    => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.', 'members'),
                'default' => __('Please send your payment to: Payment Address', 'members'),
            ],
            'auto_complete' => [
                'title'   => __('Auto Complete', 'members'),
                'type'    => 'checkbox',
                'label'   => __('Automatically complete transactions (not recommended)', 'members'),
                'default' => false,
                'description' => __('If checked, memberships will be activated immediately without admin approval.', 'members'),
            ],
            'admin_emails' => [
                'title'   => __('Admin Notification Emails', 'members'),
                'type'    => 'text',
                'description' => __('Email addresses to receive notifications about new manual payments (comma separated).', 'members'),
                'default' => get_option('admin_email'),
            ],
            'auto_renew' => [
                'title'   => __('Auto Renew', 'members'),
                'type'    => 'checkbox',
                'label'   => __('Automatically renew subscriptions without requiring new payment', 'members'),
                'default' => false,
                'description' => __('If checked, subscriptions will automatically renew without requiring new payment (useful for membership sites without real payments).', 'members'),
            ],
        ];
    }
    
    /**
     * Payment fields displayed on checkout
     *
     * @return string
     */
    public function payment_fields() {
        $description = $this->get_setting('description');
        $instructions = $this->get_setting('instructions');
        
        ob_start();
        
        if (!empty($description)) {
            echo '<div class="members-payment-description">';
            echo '<p>' . wp_kses_post($description) . '</p>';
            echo '</div>';
        }
        
        if (!empty($instructions)) {
            echo '<div class="members-payment-instructions">';
            echo '<h4>' . __('Payment Instructions:', 'members') . '</h4>';
            echo '<div class="members-payment-instructions-content">';
            echo wp_kses_post(wpautop(wptexturize($instructions)));
            echo '</div>';
            echo '</div>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Validate payment fields
     *
     * @param array $data
     * @return bool|\WP_Error
     */
    public function validate_payment_fields($data) {
        // The manual gateway doesn't require any specific validation
        return true;
    }
    
    /**
     * Process payment
     *
     * @param array $payment_data
     * @return array
     */
    public function process_payment($payment_data) {
        try {
            // Create transaction record
            $transaction_data = [
                'user_id'    => $payment_data['user_id'],
                'product_id' => $payment_data['product_id'],
                'amount'     => $payment_data['amount'],
                'total'      => $payment_data['amount'],
                'trans_num'  => 'manual-' . uniqid(),
                'status'     => $this->get_setting('auto_complete', false) ? 'complete' : 'pending',
                'txn_type'   => 'payment',
                'gateway'    => $this->id,
            ];
            
            // Add metadata if available
            if (!empty($payment_data['first_name'])) {
                $transaction_data['first_name'] = $payment_data['first_name'];
            }
            
            if (!empty($payment_data['last_name'])) {
                $transaction_data['last_name'] = $payment_data['last_name'];
            }
            
            if (!empty($payment_data['email'])) {
                $transaction_data['email'] = $payment_data['email'];
            }
            
            // Create transaction
            $transaction_id = Subscriptions\create_transaction($transaction_data);
            
            if (!$transaction_id) {
                throw new \Exception(__('Error recording transaction.', 'members'));
            }
            
            // If transaction is complete, update user roles
            if ($this->get_setting('auto_complete', false)) {
                $this->maybe_update_user_roles($payment_data['user_id'], $payment_data['product_id']);
            }
            
            // Trigger action for transaction created
            do_action('members_transaction_created', $transaction_id, $transaction_data);
            
            // Generate success response
            $response = [
                'success'        => true,
                'transaction_id' => $transaction_id,
                'redirect'       => !empty($payment_data['redirect']) ? $payment_data['redirect'] : '',
                'message'        => $this->get_setting('auto_complete', false) 
                    ? __('Payment completed successfully.', 'members')
                    : __('Payment received. Awaiting admin approval.', 'members'),
            ];
            
            // Add one-time access expiration if applicable
            if (isset($payment_data['has_access_period']) && $payment_data['has_access_period']) {
                $expires_at = Subscriptions\calculate_subscription_expiration(
                    $payment_data['access_period'],
                    $payment_data['access_period_type']
                );
                
                // Create a subscription record for the limited access
                $subscription_data = [
                    'user_id'     => $payment_data['user_id'],
                    'product_id'  => $payment_data['product_id'],
                    'gateway'     => $this->id,
                    'status'      => $this->get_setting('auto_complete', false) ? 'active' : 'pending',
                    'subscr_id'   => 'manual-single-' . uniqid(),
                    'period_type' => $payment_data['access_period_type'],
                    'period'      => $payment_data['access_period'],
                    'price'       => $payment_data['amount'],
                    'total'       => $payment_data['amount'],
                    'expires_at'  => $expires_at,
                ];
                
                $subscription_id = Subscriptions\create_subscription($subscription_data);
                
                if ($subscription_id) {
                    // Update transaction with subscription ID
                    Subscriptions\update_transaction($transaction_id, ['subscription_id' => $subscription_id]);
                    
                    // Add subscription ID to response
                    $response['subscription_id'] = $subscription_id;
                }
            }
            
            return $response;
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Process subscription
     *
     * @param array $payment_data
     * @return array
     */
    public function process_subscription($payment_data) {
        try {
            // Calculate expiration date if needed
            $expires_at = null;
            if (!empty($payment_data['period']) && !empty($payment_data['period_type'])) {
                $expires_at = Subscriptions\calculate_subscription_expiration(
                    $payment_data['period'],
                    $payment_data['period_type']
                );
            }
            
            // Create subscription record
            $subscription_data = [
                'user_id'     => $payment_data['user_id'],
                'product_id'  => $payment_data['product_id'],
                'gateway'     => $this->id,
                'status'      => $this->get_setting('auto_complete', false) ? 'active' : 'pending',
                'subscr_id'   => 'manual-sub-' . uniqid(),
                'period_type' => $payment_data['period_type'],
                'period'      => $payment_data['period'],
                'price'       => $payment_data['amount'],
                'total'       => $payment_data['amount'],
                'expires_at'  => $expires_at,
            ];
            
            // Add trial data if present
            if (!empty($payment_data['trial']) && !empty($payment_data['trial_days'])) {
                $subscription_data['trial'] = 1;
                $subscription_data['trial_days'] = $payment_data['trial_days'];
                $subscription_data['trial_amount'] = !empty($payment_data['trial_amount']) ? $payment_data['trial_amount'] : 0;
                $subscription_data['trial_total'] = !empty($payment_data['trial_amount']) ? $payment_data['trial_amount'] : 0;
                
                // If there's a trial, set expiration based on trial days
                $subscription_data['expires_at'] = date('Y-m-d H:i:s', strtotime('+' . $payment_data['trial_days'] . ' days'));
            }
            
            $subscription_id = Subscriptions\create_subscription($subscription_data);
            
            if (!$subscription_id) {
                throw new \Exception(__('Error recording subscription.', 'members'));
            }
            
            // Create transaction for the first payment
            $transaction_data = [
                'user_id'         => $payment_data['user_id'],
                'product_id'      => $payment_data['product_id'],
                'amount'          => (!empty($payment_data['trial']) && !empty($payment_data['trial_days'])) ? 
                                      $payment_data['trial_amount'] : $payment_data['amount'],
                'total'           => (!empty($payment_data['trial']) && !empty($payment_data['trial_days'])) ? 
                                      $payment_data['trial_amount'] : $payment_data['amount'],
                'trans_num'       => 'manual-txn-' . uniqid(),
                'status'          => $this->get_setting('auto_complete', false) ? 'complete' : 'pending',
                'txn_type'        => 'payment',
                'gateway'         => $this->id,
                'subscription_id' => $subscription_id,
            ];
            
            $transaction_id = Subscriptions\create_transaction($transaction_data);
            
            if (!$transaction_id) {
                throw new \Exception(__('Error recording transaction.', 'members'));
            }
            
            // If subscription is active, update user roles
            if ($this->get_setting('auto_complete', false)) {
                $this->maybe_update_user_roles($payment_data['user_id'], $payment_data['product_id']);
            }
            
            // Trigger actions
            do_action('members_subscription_created', $subscription_id, $subscription_data);
            do_action('members_transaction_created', $transaction_id, $transaction_data);
            
            return [
                'success'         => true,
                'subscription_id' => $subscription_id,
                'transaction_id'  => $transaction_id,
                'redirect'        => !empty($payment_data['redirect']) ? $payment_data['redirect'] : '',
                'message'         => $this->get_setting('auto_complete', false) 
                                     ? __('Subscription created successfully.', 'members')
                                     : __('Subscription received. Awaiting admin approval.', 'members'),
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Admin approve transaction
     * Handles the admin approval of a manual transaction
     */
    public function admin_approve_transaction() {
        // Check user capabilities
        if (!current_user_can('edit_transactions')) {
            wp_die(__('You do not have permission to perform this action.', 'members'));
        }
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'approve_transaction')) {
            wp_die(__('Security check failed.', 'members'));
        }
        
        // Get transaction ID
        $transaction_id = isset($_GET['transaction_id']) ? absint($_GET['transaction_id']) : 0;
        
        if (!$transaction_id) {
            wp_die(__('Invalid transaction ID.', 'members'));
        }
        
        // Get transaction
        $transaction = Subscriptions\get_transaction($transaction_id);
        
        if (!$transaction) {
            wp_die(__('Transaction not found.', 'members'));
        }
        
        // Check if transaction is already complete
        if ($transaction->status === 'complete') {
            $redirect_url = add_query_arg([
                'page' => 'members-transactions',
                'message' => 'already_approved',
            ], admin_url('admin.php'));
            
            wp_redirect($redirect_url);
            exit;
        }
        
        // Update transaction status
        $updated = Subscriptions\update_transaction($transaction_id, ['status' => 'complete']);
        
        if (!$updated) {
            wp_die(__('Error updating transaction status.', 'members'));
        }
        
        // If there's a subscription ID, activate that subscription
        if (!empty($transaction->subscription_id)) {
            $subscription = Subscriptions\get_subscription($transaction->subscription_id);
            
            if ($subscription && $subscription->status === 'pending') {
                Subscriptions\activate_subscription($transaction->subscription_id);
            }
        } else {
            // One-time purchase without subscription - add user to the membership
            $this->maybe_update_user_roles($transaction->user_id, $transaction->product_id);
        }
        
        // Redirect back to transactions page
        $redirect_url = add_query_arg([
            'page' => 'members-transactions',
            'message' => 'approved',
        ], admin_url('admin.php'));
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Admin reject transaction
     * Handles the admin rejection of a manual transaction
     */
    public function admin_reject_transaction() {
        // Check user capabilities
        if (!current_user_can('edit_transactions')) {
            wp_die(__('You do not have permission to perform this action.', 'members'));
        }
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'reject_transaction')) {
            wp_die(__('Security check failed.', 'members'));
        }
        
        // Get transaction ID
        $transaction_id = isset($_GET['transaction_id']) ? absint($_GET['transaction_id']) : 0;
        
        if (!$transaction_id) {
            wp_die(__('Invalid transaction ID.', 'members'));
        }
        
        // Get transaction
        $transaction = Subscriptions\get_transaction($transaction_id);
        
        if (!$transaction) {
            wp_die(__('Transaction not found.', 'members'));
        }
        
        // Check if transaction is already failed
        if ($transaction->status === 'failed') {
            $redirect_url = add_query_arg([
                'page' => 'members-transactions',
                'message' => 'already_rejected',
            ], admin_url('admin.php'));
            
            wp_redirect($redirect_url);
            exit;
        }
        
        // Update transaction status
        $updated = Subscriptions\update_transaction($transaction_id, ['status' => 'failed']);
        
        if (!$updated) {
            wp_die(__('Error updating transaction status.', 'members'));
        }
        
        // If there's a subscription ID, cancel that subscription
        if (!empty($transaction->subscription_id)) {
            $subscription = Subscriptions\get_subscription($transaction->subscription_id);
            
            if ($subscription && ($subscription->status === 'pending' || $subscription->status === 'active')) {
                Subscriptions\update_subscription($transaction->subscription_id, ['status' => 'failed']);
            }
        }
        
        // Redirect back to transactions page
        $redirect_url = add_query_arg([
            'page' => 'members-transactions',
            'message' => 'rejected',
        ], admin_url('admin.php'));
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle manual renewal
     * This is called when a subscription needs to be renewed
     *
     * @param int $subscription_id
     */
    public function handle_manual_renewal($subscription_id) {
        // Get subscription
        $subscription = Subscriptions\get_subscription($subscription_id);
        
        if (!$subscription || $subscription->gateway !== $this->id) {
            return; // Not our subscription
        }
        
        // Check if auto-renewal is enabled
        if (!$this->get_setting('auto_renew', false)) {
            // If auto-renew is not enabled, expire the subscription
            Subscriptions\update_subscription($subscription_id, ['status' => 'expired']);
            
            // Remove user roles
            $this->maybe_remove_user_roles($subscription->user_id, $subscription->product_id);
            
            // Trigger expiration action
            do_action('members_subscription_expired', $subscription_id);
            
            return;
        }
        
        // Process renewal with auto-renew
        $renewal_result = Subscriptions\process_subscription_renewal($subscription_id);
        
        if (isset($renewal_result['success']) && $renewal_result['success']) {
            // Renewal successful
            // No need to do anything else - process_subscription_renewal handles role updates
        } else {
            // Renewal failed
            Subscriptions\update_subscription($subscription_id, ['status' => 'expired']);
            
            // Remove user roles
            $this->maybe_remove_user_roles($subscription->user_id, $subscription->product_id);
            
            // Trigger expiration action
            do_action('members_subscription_expired', $subscription_id);
        }
    }
    
    /**
     * Maybe notify admin of new transaction
     *
     * @param int   $transaction_id
     * @param array $transaction_data
     */
    public function maybe_notify_admin_transaction($transaction_id, $transaction_data) {
        // Only notify for our gateway
        if ($transaction_data['gateway'] !== $this->id) {
            return;
        }
        
        // Only notify for pending transactions
        if ($transaction_data['status'] !== 'pending') {
            return;
        }
        
        // Get admin emails
        $admin_emails = $this->get_setting('admin_emails');
        
        if (empty($admin_emails)) {
            return;
        }
        
        // Split emails by comma
        $emails = array_map('trim', explode(',', $admin_emails));
        
        if (empty($emails)) {
            return;
        }
        
        // Get basic transaction info
        $transaction = Subscriptions\get_transaction($transaction_id);
        if (!$transaction) {
            return;
        }
        
        $user = get_userdata($transaction->user_id);
        $product = get_post($transaction->product_id);
        
        if (!$user || !$product) {
            return;
        }
        
        // Prepare email content
        $subject = sprintf(__('New manual payment: %s', 'members'), $product->post_title);
        
        $message = sprintf(__('A new manual payment has been submitted and is awaiting approval:', 'members'), $product->post_title) . "\n\n";
        $message .= sprintf(__('User: %s (%s)', 'members'), $user->display_name, $user->user_email) . "\n";
        $message .= sprintf(__('Product: %s', 'members'), $product->post_title) . "\n";
        $message .= sprintf(__('Amount: $%s', 'members'), number_format($transaction->amount, 2)) . "\n";
        $message .= sprintf(__('Transaction ID: %s', 'members'), $transaction->trans_num) . "\n\n";
        
        // Add approval link
        $approve_url = wp_nonce_url(
            admin_url('admin-post.php?action=members_manual_approve_transaction&transaction_id=' . $transaction_id),
            'approve_transaction'
        );
        
        $reject_url = wp_nonce_url(
            admin_url('admin-post.php?action=members_manual_reject_transaction&transaction_id=' . $transaction_id),
            'reject_transaction'
        );
        
        $message .= __('To approve this payment, click the following link:', 'members') . "\n";
        $message .= $approve_url . "\n\n";
        
        $message .= __('To reject this payment, click the following link:', 'members') . "\n";
        $message .= $reject_url . "\n\n";
        
        $message .= __('Or manage all transactions from the admin panel:', 'members') . "\n";
        $message .= admin_url('admin.php?page=members-transactions') . "\n";
        
        // Send email to each admin
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        foreach ($emails as $email) {
            wp_mail($email, $subject, $message, $headers);
        }
    }
    
    /**
     * Maybe notify admin of new subscription
     *
     * @param int   $subscription_id
     * @param array $subscription_data
     */
    public function maybe_notify_admin_subscription($subscription_id, $subscription_data) {
        // Only notify for our gateway
        if ($subscription_data['gateway'] !== $this->id) {
            return;
        }
        
        // Only notify for pending subscriptions
        if ($subscription_data['status'] !== 'pending') {
            return;
        }
        
        // Get admin emails
        $admin_emails = $this->get_setting('admin_emails');
        
        if (empty($admin_emails)) {
            return;
        }
        
        // Split emails by comma
        $emails = array_map('trim', explode(',', $admin_emails));
        
        if (empty($emails)) {
            return;
        }
        
        // Get basic subscription info
        $subscription = Subscriptions\get_subscription($subscription_id);
        if (!$subscription) {
            return;
        }
        
        $user = get_userdata($subscription->user_id);
        $product = get_post($subscription->product_id);
        
        if (!$user || !$product) {
            return;
        }
        
        // Prepare email content
        $subject = sprintf(__('New manual subscription: %s', 'members'), $product->post_title);
        
        $message = sprintf(__('A new manual subscription has been created and is awaiting approval:', 'members'), $product->post_title) . "\n\n";
        $message .= sprintf(__('User: %s (%s)', 'members'), $user->display_name, $user->user_email) . "\n";
        $message .= sprintf(__('Product: %s', 'members'), $product->post_title) . "\n";
        $message .= sprintf(__('Amount: $%s / %d %s', 'members'), 
                          number_format($subscription->price, 2),
                          $subscription->period,
                          $subscription->period_type) . "\n";
        $message .= sprintf(__('Subscription ID: %s', 'members'), $subscription->subscr_id) . "\n\n";
        
        // Add link to manage subscriptions
        $message .= __('Manage all subscriptions from the admin panel:', 'members') . "\n";
        $message .= admin_url('admin.php?page=members-subscriptions') . "\n";
        
        // Send email to each admin
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        foreach ($emails as $email) {
            wp_mail($email, $subject, $message, $headers);
        }
    }
    
    /**
     * Update user roles based on product membership roles
     *
     * @param int $user_id
     * @param int $product_id
     */
    private function maybe_update_user_roles($user_id, $product_id) {
        $product_roles = Subscriptions\get_product_meta($product_id, '_membership_roles', []);
        
        if (empty($product_roles)) {
            return;
        }
        
        $user = get_userdata($user_id);
        
        if (!$user) {
            return;
        }
        
        foreach ($product_roles as $role) {
            $user->add_role($role);
        }
    }
    
    /**
     * Remove user roles based on product membership roles
     *
     * @param int $user_id
     * @param int $product_id
     */
    private function maybe_remove_user_roles($user_id, $product_id) {
        $product_roles = Subscriptions\get_product_meta($product_id, '_membership_roles', []);
        
        if (empty($product_roles)) {
            return;
        }
        
        $user = get_userdata($user_id);
        
        if (!$user) {
            return;
        }
        
        // Check if user has other active subscriptions with each role
        foreach ($product_roles as $role) {
            $other_active = false;
            
            // Get all user's active subscriptions
            $subscriptions = Subscriptions\get_subscriptions([
                'user_id' => $user_id,
                'status'  => 'active',
            ]);
            
            foreach ($subscriptions as $subscription) {
                if ($subscription->product_id == $product_id) {
                    continue; // Skip the product we're removing
                }
                
                $other_roles = Subscriptions\get_product_meta($subscription->product_id, '_membership_roles', []);
                
                if (in_array($role, $other_roles)) {
                    $other_active = true;
                    break;
                }
            }
            
            // Only remove the role if no other active subscription grants it
            if (!$other_active) {
                $user->remove_role($role);
            }
        }
    }
}