/**
 * Members Subscriptions - Frontend JavaScript
 */
(function($) {
    'use strict';

    /**
     * Frontend functionality for the Members Subscriptions addon
     */
    var MembersSubscriptionsFrontend = {
        
        /**
         * Initialize the frontend JS
         */
        init: function() {
            this.initSubscriptionForm();
            this.initPaymentMethods();
            this.initAccountPage();
        },

        /**
         * Initialize subscription form
         */
        initSubscriptionForm: function() {
            // Handle plan selection
            $('.members-subscription-plan').on('click', function(e) {
                if (!$(e.target).hasClass('members-subscription-plan-select-button')) {
                    e.preventDefault();
                    MembersSubscriptionsFrontend.selectPlan($(this));
                }
            });
            
            $('.members-subscription-plan-select-button').on('click', function(e) {
                e.preventDefault();
                MembersSubscriptionsFrontend.selectPlan($(this).closest('.members-subscription-plan'));
            });
            
            // Form submission handling
            $('.members-subscription-form form').on('submit', function(e) {
                e.preventDefault();
                MembersSubscriptionsFrontend.processForm($(this));
            });
        },
        
        /**
         * Select a subscription plan
         * 
         * @param {jQuery} planElement The plan element to select
         */
        selectPlan: function(planElement) {
            // Deselect all plans
            $('.members-subscription-plan').removeClass('selected');
            $('.members-subscription-plan-select-button').text(MembersSubscriptions.i18n.selectPlan);
            
            // Select the clicked plan
            planElement.addClass('selected');
            planElement.find('.members-subscription-plan-select-button').text(MembersSubscriptions.i18n.selected);
            
            // Update the hidden input with the selected plan
            var planId = planElement.data('plan-id');
            $('input[name="plan_id"]').val(planId);
            
            // Show the payment form if a plan is selected
            if (planId) {
                $('.members-payment-form').slideDown();
            } else {
                $('.members-payment-form').slideUp();
            }
        },
        
        /**
         * Initialize payment method selection
         */
        initPaymentMethods: function() {
            // Handle payment method selection
            $('.members-payment-method').on('click', function(e) {
                if (!$(e.target).is('input[type="radio"]')) {
                    $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
                }
            });
            
            // Toggle payment method content sections
            $('input[name="payment_method"]').on('change', function() {
                var selectedMethod = $(this).val();
                
                // Hide all payment method content sections
                $('.members-payment-method-content').slideUp();
                
                // Show the selected payment method content
                $('.members-payment-method').removeClass('selected');
                $(this).closest('.members-payment-method').addClass('selected')
                       .find('.members-payment-method-content').slideDown();
                
                // Toggle required attributes based on selected method
                MembersSubscriptionsFrontend.toggleRequiredFields(selectedMethod);
            });
            
            // Initialize with the default selected payment method
            $('input[name="payment_method"]:checked').trigger('change');
        },
        
        /**
         * Toggle required fields based on payment method
         * 
         * @param {string} paymentMethod The selected payment method
         */
        toggleRequiredFields: function(paymentMethod) {
            // Reset all required fields
            $('.members-payment-method-content input, .members-payment-method-content select').prop('required', false);
            
            // Set required fields for the selected payment method
            $('.members-payment-method-content[data-method="' + paymentMethod + '"] .required').prop('required', true);
        },
        
        /**
         * Process the subscription form
         * 
         * @param {jQuery} form The form element
         */
        processForm: function(form) {
            // Validate the form
            if (!MembersSubscriptionsFrontend.validateForm(form)) {
                return false;
            }
            
            // Show loading state
            form.find('button[type="submit"]').prop('disabled', true).text(MembersSubscriptions.i18n.processing);
            
            // Submit the form data via AJAX
            $.ajax({
                url: MembersSubscriptions.ajaxUrl,
                type: 'POST',
                data: form.serialize() + '&action=members_process_subscription&nonce=' + MembersSubscriptions.nonce,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        form.before('<div class="members-message members-message-success">' + MembersSubscriptions.i18n.successSubscription + '</div>');
                        
                        // If redirect URL is provided, redirect
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            // Reset form
                            form.get(0).reset();
                            $('.members-payment-form').slideUp();
                            form.find('button[type="submit"]').prop('disabled', false).text(MembersSubscriptions.i18n.subscribe);
                        }
                    } else {
                        // Show error message
                        form.before('<div class="members-message members-message-error">' + (response.message || MembersSubscriptions.i18n.errorPayment) + '</div>');
                        form.find('button[type="submit"]').prop('disabled', false).text(MembersSubscriptions.i18n.subscribe);
                    }
                },
                error: function() {
                    // Show error message
                    form.before('<div class="members-message members-message-error">' + MembersSubscriptions.i18n.errorPayment + '</div>');
                    form.find('button[type="submit"]').prop('disabled', false).text(MembersSubscriptions.i18n.subscribe);
                }
            });
        },
        
        /**
         * Validate the subscription form
         * 
         * @param {jQuery} form The form element
         * @return {boolean} Whether the form is valid
         */
        validateForm: function(form) {
            var isValid = true;
            
            // Clear previous errors
            $('.members-form-error').remove();
            $('.members-form-row').removeClass('error');
            $('.members-message-error').remove();
            
            // Check if a plan is selected
            if (!$('.members-subscription-plan.selected').length) {
                form.before('<div class="members-message members-message-error">' + MembersSubscriptions.i18n.selectPlan + '</div>');
                isValid = false;
            }
            
            // Check required fields
            form.find('input[required], select[required]').each(function() {
                if (!$(this).val()) {
                    var formRow = $(this).closest('.members-form-row');
                    formRow.addClass('error');
                    formRow.append('<div class="members-form-error">' + MembersSubscriptions.i18n.requiredField + '</div>');
                    isValid = false;
                }
            });
            
            // Check payment method
            if (!form.find('input[name="payment_method"]:checked').length) {
                form.find('.members-payment-methods').before('<div class="members-message members-message-error">' + MembersSubscriptions.i18n.selectPaymentMethod + '</div>');
                isValid = false;
            }
            
            return isValid;
        },
        
        /**
         * Initialize account page functionality
         */
        initAccountPage: function() {
            // Handle account tab navigation
            $('.members-account-tab a').on('click', function(e) {
                e.preventDefault();
                
                var tabId = $(this).attr('href').substring(1);
                
                // Update active tab
                $('.members-account-tab').removeClass('active');
                $(this).parent().addClass('active');
                
                // Show active tab content
                $('.members-account-tab-content').removeClass('active');
                $('#' + tabId).addClass('active');
                
                // Update URL hash
                window.location.hash = tabId;
            });
            
            // Initialize with the tab from the URL hash, or default to the first tab
            var initialTab = window.location.hash ? window.location.hash.substring(1) : $('.members-account-tab:first a').attr('href').substring(1);
            $('.members-account-tab a[href="#' + initialTab + '"]').trigger('click');
            
            // Handle subscription actions
            $('.members-subscription-action').on('click', function(e) {
                var action = $(this).data('action');
                
                // Handle cancel action confirmation
                if (action === 'cancel' && !confirm(MembersSubscriptions.i18n.confirmCancel)) {
                    e.preventDefault();
                    return false;
                }
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        MembersSubscriptionsFrontend.init();
    });
    
})(jQuery);