# Members Subscriptions - Product Specification

## Overview

Members Subscriptions is an advanced addon for the Members plugin that transforms it into a complete membership and subscription management solution. This document outlines the detailed specifications for the product's functionality, user flows, and implementation details.

## Core Components

### 1. Subscription Products

#### Functionality
- Create subscription products with detailed settings
- Set pricing (one-time or recurring)
- Configure billing periods (daily, weekly, monthly, yearly)
- Offer trial periods with custom pricing
- Assign WordPress roles upon purchase
- Set access expiration for non-recurring products
- Redirect users after successful purchase
- Display products on frontend with customizable templates

#### Implementation Details
- Custom post type: `members_product`
- Meta boxes for product settings
- Custom meta table for efficient data storage
- Public or private visibility options
- Comprehensive template system with multiple fallbacks:
  - WordPress template hierarchy (single-members_product.php)
  - Theme override templates
  - Plugin templates directory
  - Direct rendering as last resort
- Shortcode for embedding product information: `[members_subscription_form]`
- 404 error prevention with advanced URL handling

### 2. Payment Processing

#### Functionality
- Multiple payment gateway support
- Secure checkout process
- Transaction recording and management
- Subscription creation and activation
- Trial period handling
- Tax calculation and application
- Receipt generation and distribution
- Validation and error handling

#### Gateway Support
- **Stripe**: Credit card processing with subscription support
- **PayPal**: Support for PayPal Standard and PayPal Commerce
- **Authorize.net**: Credit card processing with subscription support
- **Manual**: Offline payment processing for manual verification
- Extensible framework for additional gateways

#### Implementation Details
- Gateway manager with plugin architecture
- Secure API communication
- Webhook processing for external events
- PCI-compliant design (no card data stored)
- Comprehensive error handling
- Secure token-based payment forms
- Support for 3D Secure/SCA for European compliance

### 3. Subscription Management

#### Functionality
- Automated subscription renewals
- Cancellation handling
- Upgrade/downgrade processes
- Status management (active, pending, cancelled, expired, failed, suspended)
- Subscription metadata tracking
- Member access control based on status
- Renewal reminders and notifications
- Dunning management for failed payments

#### Implementation Details
- Custom subscriptions table
- Status transition system
- Expiration calculation
- Renewal triggering mechanisms
- Integration with WordPress user roles
- Event logging system
- Retry mechanisms for failed payments

### 4. User Account Management

#### Functionality
- View active subscriptions
- Cancel or update subscriptions
- View transaction history
- Update payment methods
- Download receipts/invoices
- Profile management
- Subscription renewal
- Password management

#### Implementation Details
- Account dashboard (`[members_account]` shortcode)
- Subscription management interface
- Payment method management screen
- Transaction history display
- Access control based on subscription status
- Shortcodes for embedding specific account sections

### 5. Administrator Tools

#### Functionality
- Member management
- Subscription editing
- Transaction tracking
- Revenue reporting
- Payment gateway configuration
- Email notification templates
- Subscription status updates
- Manual payment recording
- Refund processing
- System status information

#### Implementation Details
- Admin dashboard with overview statistics
- Members list with filtering options
- Subscription management interface
- Transaction management interface
- Settings pages for configuration
- Exportable reports in CSV format
- Batch operations for subscription management

### 6. Email Notifications

#### Functionality
- New subscription notifications
- Renewal reminders
- Payment receipts
- Subscription status changes
- Trial expiration notices
- Credit card expiration reminders
- Failed payment notifications
- Admin notifications for important events

#### Implementation Details
- Email template system
- Customizable content with merge tags
- HTML and plain text formats
- Scheduled delivery system
- Admin notification options
- Queueing system for reliable delivery
- Email logging and tracking

### 7. Content Restriction

#### Functionality
- Restrict content based on subscription status
- Teaser content options
- Redirect options for restricted content
- Custom restriction messages
- Override options at the post/page level
- Category/tag level restrictions
- Custom post type support

#### Implementation Details
- Integration with Members plugin restriction system
- Enhanced restriction rules based on subscription status
- Custom shortcodes for conditional content display
- Template tag functions for theme integration
- Admin UI for managing restriction rules

## User Flows

### 1. Purchasing a Subscription

1. User visits the product page
2. Views membership details and pricing
3. Clicks "Subscribe Now"
4. Enters payment information
5. Completes purchase
6. Receives confirmation and welcome email
7. Gains access to restricted content
8. Subscription status is set to active
9. User is redirected to thank you page or account dashboard

### 2. Managing a Subscription

1. User logs into their account
2. Views current subscriptions
3. Can view transaction history
4. Can update payment method
5. Can cancel subscription
6. Can upgrade/downgrade subscription
7. Receives confirmation of any changes
8. Access permissions are updated automatically

### 3. Subscription Renewal Process

1. System detects upcoming renewal
2. Sends renewal reminder to member
3. Processes renewal payment on due date
4. On success:
   - Updates subscription expiration date
   - Sends receipt email
   - Maintains user access
5. On failure:
   - Retries payment based on configured rules
   - Sends failed payment notification
   - Updates subscription status if retries fail

### 4. Administrator Management

1. Admin views subscriptions dashboard
2. Can view all members and their subscription status
3. Can edit subscription details (dates, status, etc.)
4. Can process refunds or manual payments
5. Can view transaction history
6. Can generate reports on revenue and growth
7. Can manage payment gateway settings
8. Can customize email notifications

## Data Architecture

### Tables

1. **members_subscriptions**
   - Stores subscription data
   - Links users to products
   - Tracks renewal dates and status
   - Stores gateway-specific subscription IDs
   - Records billing information

2. **members_transactions**
   - Records all financial transactions
   - Links to subscriptions
   - Stores payment details and statuses
   - Supports refund tracking
   - Includes tax information

3. **members_products_meta**
   - Stores product configuration
   - Pricing and access settings
   - Trial and billing period information
   - Redirection settings
   - Role assignments

4. **members_customer_data**
   - Stores customer payment profiles
   - Securely manages payment tokens
   - Tracks billing information
   - Links to WordPress user accounts

## Integration Points

### Members Plugin
- Role assignment/removal
- Content restriction system
- User capabilities management
- Admin interface integration

### WordPress Core
- User management
- Post type API
- Admin interface
- Scheduling system (for renewals)
- Template hierarchy

### Payment Gateways
- API integration
- Webhook processing
- IPN/notification handling
- Refund processing
- Subscription management

### Themes
- Template overriding
- Shortcode embedding
- Template tag functions
- CSS styling hooks

## Extensibility

### Hooks and Filters
- Gateway extension points
- Template customization hooks
- Process modification filters
- Notification customization
- Validation filters
- Access control filters

### Developer API
- Programmatic subscription creation
- Custom gateway development
- Access control integration
- Event listeners for subscription changes
- Subscription status management
- Transaction processing

## Template System

### Template Hierarchy
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

## Future Enhancements (Roadmap)

### Phase 2
- Coupon/discount system
- Bundle products
- Group memberships
- Advanced reporting
- Multiple subscription management
- Tiered pricing options

### Phase 3
- Subscription plan switching
- Pro-rating system
- Member directory
- Member communication tools
- Advanced analytics
- Affiliate system integration
- Drip content delivery

---

*This specification is subject to change as the product evolves. Features may be added, modified, or removed based on user feedback and market demands.*