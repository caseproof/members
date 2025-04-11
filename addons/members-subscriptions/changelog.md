# Changelog

## 1.0.2 - 2025-04-11

### Major Fixes

- Added missing products_meta database table
  - Created migration file for products_meta table
  - Fixed issue preventing adding and editing subscription products
  - Added admin notification system for database updates
  - Added database update utility
  - Updated version markers throughout codebase
  
- Added Subscription Products admin menu
  - Added dedicated submenu page for managing products
  - Improved product post type registration with better UI settings
  - Added CSS styling for product editing interface
  - Added helper function for subscription period options

## 1.0.1 - 2025-04-11

### Security Enhancements

- Fixed unsanitized POST data in payment processing
  - Replaced direct array merge of $_POST data with sanitization of specific allowed fields
  - Added proper type casting and sanitization for form fields
  
- Improved output escaping in admin views
  - Added proper sanitization and escaping of request parameters in subscriptions page
  - Added sanitization for status, page, and date inputs in transaction views
  - Fixed escaping in the gateways page for better XSS protection
  
- Enhanced capability checks for administrative functions
  - Added explicit capability validation to all admin page rendering functions
  - Added proper access control with wp_die error messages for unauthorized access
  - Maintained existing capability checks in REST API endpoints
  
- Added validation for role assignments
  - Added validation checks to ensure roles exist before assigning to users
  - Added comprehensive validation to role removal function
  - Added error logging for invalid role assignments
  - Added fallback to default roles for better error handling

### Bug Fixes

- Fixed fatal error with duplicate function declaration
  - Removed duplicate `get_subscription()` function in functions-subscriptions.php
  - Fixed references to use the function from functions-db.php
  
- Fixed syntax errors
  - Fixed unescaped apostrophe in Members add-ons configuration (addons.php)
  - Fixed unescaped apostrophe in Stripe gateway settings (class-stripe-gateway.php)
  
- Fixed PHP compatibility errors
  - Fixed incompatible method declaration in Renewal_Reminder_Email class
  - Added missing abstract method implementations in email classes
  
- Fixed form submission errors
  - Completely redesigned the gateway settings form submission to fix "expired link" error
  - Simplified nonce verification with a consistent action name
  - Added proper sanitization for all gateway settings

## 1.0.0 - Initial Release

- Initial version of the Members Subscriptions addon
- Added subscription management functionality
- Integrated Stripe payment gateway
- Created admin interfaces for managing subscriptions and transactions
- Added role-based access control for memberships