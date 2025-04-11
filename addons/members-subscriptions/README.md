# Members Subscriptions

A powerful subscription and payment processing addon for the Members plugin. Transform your WordPress site into a fully featured membership platform with recurring payments, role-based access control, and multiple payment gateway support.

## Key Features

### Membership Products
- Create and sell unlimited subscription products
- Set up one-time or recurring payments
- Offer trial periods with custom duration and pricing
- Assign WordPress roles upon purchase
- Control content access based on membership status
- Display products with customizable templates
- Archive page for browsing all available memberships

### Payment Processing
- Multiple payment gateway support
  - Stripe for credit card processing
  - PayPal (Standard and Commerce)
  - Authorize.net for credit card processing
  - Manual payments for offline transactions
  - Extendable gateway architecture
- Secure checkout process
- Tax handling capabilities
- Support for discount coupons (coming soon)
- European payment compliance (SCA/3D Secure)

### Subscription Management
- Automated subscription renewals
- Self-serve cancellation options
- Upgrade/downgrade capabilities
- Customizable email notifications
- Detailed subscription logs
- Multiple status support (active, pending, cancelled, expired, failed, suspended)
- Trial period handling
- Dunning management for failed payments

### User Account Management
- View active subscriptions
- Cancel or update subscriptions
- View transaction history
- Update payment methods
- Download receipts/invoices
- Self-service account management
- Password reset capabilities
- Update profile information

### Administrator Features
- Comprehensive subscription dashboard
- Transaction history and reporting
- Member management interface
- Payment gateway configuration
- Email notification templates
- Manual subscription management
- Refund processing
- Manual payment recording
- System status monitoring
- Performance optimization settings

### Content Restriction
- Restrict content based on subscription status
- Custom restriction messages
- Teaser content display options
- Content redirects for non-members
- Category and tag level restrictions
- Custom post type support
- Shortcodes for conditional content

## Technical Specifications

### Database Schema
- Custom tables for subscriptions, transactions, and product metadata
- Integration with WordPress user system
- Efficient data storage and retrieval
- Optimized queries for performance

### Architecture
- Object-oriented design with modern PHP practices
- Clean separation of concerns (MVC-inspired architecture)
- Extendable classes for custom development
- WordPress coding standards compliant
- Comprehensive template system with multiple fallbacks

### Integration
- Seamless integration with Members plugin
- Compatible with popular WordPress themes
- Template overriding for custom designs
- Hooks and filters for developer customization
- REST API integration points
- Developer API for programmatic control

### Security
- Secure payment processing
- PCI-compliant design principles
- Data validation and sanitization
- Protected customer information
- Token-based payment methods
- CSRF protection
- Input sanitization and validation

### Template System
1. WordPress template hierarchy files:
   - `/single-members_product.php` - For single product display
   - `/archive-members_product.php` - For product archives

2. Theme override locations:
   - `members/subscriptions/single-product.php`
   - `members/single-product.php`
   - `members-subscriptions/single-product.php`

3. Plugin templates directory:
   - `/templates/single-product.php`
   - `/templates/archive-product.php`

4. Direct rendering fallback for maximum compatibility

## Requirements

- WordPress 5.6 or higher
- PHP 7.2 or higher
- Members plugin 3.0 or higher
- SSL certificate (required for payment processing)
- MySQL 5.6 or higher / MariaDB 10.0 or higher

## Installation

1. Install and activate the Members plugin
2. Upload the Members Subscriptions addon to your plugins directory
3. Activate the addon through the WordPress admin panel
4. Configure payment gateways under Members > Payment Gateways
5. Create subscription products under Members > Subscription Products
6. Add subscription forms to your pages using the shortcode: `[members_subscription_form product_id="X"]`
7. Use `[members_account]` shortcode to display the member account dashboard

## Shortcodes

- `[members_subscription_form product_id="X"]` - Display a subscription form for a specific product
- `[members_account]` - Display the member account dashboard
- `[members_subscriptions]` - Display a list of user's current subscriptions
- `[members_transactions]` - Display a user's transaction history
- `[members_product_list]` - Display a grid of available membership products

## Documentation

For detailed documentation, visit [members-plugin.com/docs/subscriptions](https://members-plugin.com/docs/subscriptions)

## Support

Premium support is available for this addon. Please contact support through your account at [members-plugin.com/support](https://members-plugin.com/support)

## Changelog

See [changelog.md](changelog.md) for a complete list of changes.

## License

This plugin is licensed under the GPL v2 or later.

Copyright © 2025 MemberPress Team