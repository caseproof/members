/**
 * Products Admin JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Products list table functionality
    const productsTable = document.querySelector('.wp-list-table');
    if (productsTable) {
        // Handle bulk actions
        const bulkActions = document.querySelector('.tablenav-pages #bulk-action-selector-top');
        const bulkActionsSubmit = document.querySelector('.tablenav-pages #doaction');
        
        if (bulkActions && bulkActionsSubmit) {
            bulkActionsSubmit.addEventListener('click', function(e) {
                const action = bulkActions.value;
                
                // Confirm delete action
                if (action === 'delete' && !confirm('Are you sure you want to delete the selected products? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        }
        
        // Handle export button
        const exportButton = document.querySelector('button[name="export"]');
        if (exportButton) {
            exportButton.addEventListener('click', function() {
                const productsForm = document.getElementById('products-filter');
                
                // Add hidden field for export
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'export';
                hiddenInput.value = 'csv';
                
                productsForm.appendChild(hiddenInput);
                productsForm.submit();
            });
        }
        
        // Toggle visibility of specific columns on mobile
        function adjustColumnsForScreenSize() {
            const screenWidth = window.innerWidth;
            
            if (screenWidth < 782) {
                // Hide columns on small screens
                document.querySelectorAll('.column-roles, .column-active_members, .column-total_revenue').forEach(function(column) {
                    column.style.display = 'none';
                });
            } else {
                // Show columns on larger screens
                document.querySelectorAll('.column-roles, .column-active_members, .column-total_revenue').forEach(function(column) {
                    column.style.display = 'table-cell';
                });
            }
        }
        
        // Run on page load and resize
        adjustColumnsForScreenSize();
        window.addEventListener('resize', adjustColumnsForScreenSize);
    }
    
    // Product details modal functionality
    const detailsButtons = document.querySelectorAll('.view-details');
    if (detailsButtons.length > 0) {
        detailsButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const productId = this.dataset.productId;
                const modal = document.getElementById('product-details-' + productId);
                
                if (modal) {
                    modal.style.display = 'block';
                    
                    // Close modal when clicking close button
                    const closeButton = modal.querySelector('.modal-close');
                    if (closeButton) {
                        closeButton.addEventListener('click', function() {
                            modal.style.display = 'none';
                        });
                    }
                    
                    // Close modal when clicking outside
                    window.addEventListener('click', function(event) {
                        if (event.target === modal) {
                            modal.style.display = 'none';
                        }
                    });
                }
            });
        });
    }
});