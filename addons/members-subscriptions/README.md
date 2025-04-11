# Members Subscriptions Add-on

## Overview
The Members Subscriptions Add-on extends the [Members plugin](https://wordpress.org/plugins/members/) with subscription and payment functionality, allowing you to create membership products with associated roles, process payments via Stripe, and manage user subscriptions.

## Features

### Membership Products
- Create and manage subscription products with custom prices and billing terms
- Support for one-time and recurring payment options
- Associate WordPress roles with products for automatic role assignment
- Optional trial periods for subscriptions

### Payment Processing
- Secure payment processing via Stripe
- Manual payment option for offline payments
- Support for coupons and discounts
- Transaction logging and management

### User Management
- Automatic role assignment based on subscription status
- Role validation to prevent security issues
- Subscription management interface for users
- Comprehensive admin tools for subscription management

### Security Features
- Input sanitization for all user-submitted data
- Output escaping in admin views to prevent XSS vulnerabilities 
- Capability checks for all administrative functions
- Validation for role assignments to prevent privilege escalation

## Requirements
- WordPress 5.0 or higher
- PHP 7.2 or higher
- Members plugin 3.0 or higher
- Stripe account (for Stripe payment gateway)

## Installation
1. Upload the `members-subscriptions` folder to the `/wp-content/plugins/members/addons/` directory
2. Activate the add-on through the Members Add-ons page
3. Configure payment gateways under Members > Payment Gateways
4. Create membership products under Members > Subscription Products

## Configuration
- **Payment Gateways**: Configure payment gateways under Members > Payment Gateways
- **Products**: Create and manage membership products under Members > Subscription Products
- **Subscriptions**: View and manage user subscriptions under Members > Subscriptions
- **Transactions**: View and manage payment transactions under Members > Transactions

## Shortcodes
- `[members_subscription_form product_id="X"]` - Displays a subscription form for a specific product
- `[members_account]` - Displays the user's account page with subscription information

## Changelog
See [changelog.md](changelog.md) for a complete list of changes and updates.

## License
This add-on is licensed under the same terms as the Members plugin.

## Credits
Developed by the MemberPress Team