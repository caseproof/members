/**
 * Members Subscriptions - Admin JavaScript
 */
(function($) {
    'use strict';

    /**
     * Admin functionality for the Members Subscriptions addon
     */
    var MembersSubscriptionsAdmin = {
        
        /**
         * Initialize the admin JS
         */
        init: function() {
            this.initListTableActions();
            this.initFilterControls();
            this.initDatepickers();
            this.initGatewaySettings();
        },

        /**
         * Initialize list table action links
         */
        initListTableActions: function() {
            // Subscription actions
            $('.members-subscription-action').on('click', function(e) {
                var action = $(this).data('action');
                
                // Handle various action confirmations
                if (action === 'cancel' && !confirm(MembersSubscriptionsAdmin.i18n.confirmCancel)) {
                    e.preventDefault();
                    return false;
                } else if (action === 'delete' && !confirm(MembersSubscriptionsAdmin.i18n.confirmDelete)) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Transaction actions
            $('.members-transaction-action').on('click', function(e) {
                var action = $(this).data('action');
                
                // Handle refund confirmation
                if (action === 'refund' && !confirm(MembersSubscriptionsAdmin.i18n.confirmRefund)) {
                    e.preventDefault();
                    return false;
                } else if (action === 'delete' && !confirm(MembersSubscriptionsAdmin.i18n.confirmDelete)) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Bulk actions confirmation
            $('#doaction, #doaction2').on('click', function(e) {
                var selectedAction = $(this).siblings('select').val();
                
                if (selectedAction === 'delete' && !confirm(MembersSubscriptionsAdmin.i18n.confirmDelete)) {
                    e.preventDefault();
                    return false;
                } else if (selectedAction === 'cancel' && !confirm(MembersSubscriptionsAdmin.i18n.confirmCancel)) {
                    e.preventDefault();
                    return false;
                } else if (selectedAction === 'refund' && !confirm(MembersSubscriptionsAdmin.i18n.confirmRefund)) {
                    e.preventDefault();
                    return false;
                }
            });
        },
        
        /**
         * Initialize filter controls
         */
        initFilterControls: function() {
            // Auto-submit filters when they change
            $('.subscription-status-filter, .transaction-status-filter, .gateway-filter').on('change', function() {
                $(this).closest('form').submit();
            });
        },
        
        /**
         * Initialize datepickers for date range filters
         */
        initDatepickers: function() {
            // Check if datepicker is available (WP includes jQuery UI datepicker)
            if ($.fn.datepicker) {
                $('.members-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true
                });
            }
        },
        
        /**
         * Initialize gateway settings toggles
         */
        initGatewaySettings: function() {
            // Toggle gateway settings sections
            $('.members-gateway-toggle').on('change', function() {
                var gatewayId = $(this).data('gateway');
                var isEnabled = $(this).is(':checked');
                
                $('#members-gateway-settings-' + gatewayId).toggle(isEnabled);
                
                // Save the toggle state via AJAX
                $.post(MembersSubscriptionsAdmin.ajaxUrl, {
                    action: 'members_toggle_gateway',
                    gateway: gatewayId,
                    enabled: isEnabled ? 1 : 0,
                    nonce: MembersSubscriptionsAdmin.nonce
                });
            });
            
            // Initialize gateway settings visibility based on initial state
            $('.members-gateway-toggle').each(function() {
                var gatewayId = $(this).data('gateway');
                var isEnabled = $(this).is(':checked');
                
                $('#members-gateway-settings-' + gatewayId).toggle(isEnabled);
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        MembersSubscriptionsAdmin.init();
    });
    
})(jQuery);