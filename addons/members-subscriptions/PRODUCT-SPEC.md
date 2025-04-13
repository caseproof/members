# Members Subscriptions Add-on

A comprehensive subscription management add-on for the Members plugin that adds membership products, payment processing, and subscription management capabilities.

## Features

### Membership Products

- **Custom Post Type**: Provides a 'members_product' post type for creating and managing membership offerings
- **Product Configuration**: 
  - One-time and recurring payment options
  - Customizable billing cycles (days, weeks, months, years)
  - Free and paid trial periods
  - Limited-time access for one-time payments

### User Management

- **Role-Based Access**: Automatically assigns and removes WordPress roles based on subscription status
- **Registration Integration**: Combined user registration and subscription checkout in a single form
- **Account Management**: Allows members to view and manage their subscriptions

### Payment Processing

- **Manual Payments**: Built-in support for manual payment processing
- **Gateway Support**: Framework for additional payment gateways
- **Transaction Recording**: Maintains a record of all payment transactions

### Content Protection

- **Content Restriction**: Restricts access to content based on membership status
- **Shortcodes**: Easy-to-use shortcodes for displaying products and subscription forms anywhere

## Technical Details

### Database Tables

The add-on creates and manages the following custom database tables:

1. `wp_members_transactions`
   - Records all payment transactions
   - Stores user ID, product ID, amount, status, gateway info, and timestamps
   - Provides transaction history and receipt generation

2. `wp_members_subscriptions`
   - Tracks active and past subscriptions
   - Stores subscription details, period information, and status
   - Manages recurring billing cycles and expiration dates

3. `wp_members_products_meta`
   - Stores product configuration details 
   - Supplements the standard WordPress post meta
   - Optimizes performance for product data retrieval

4. `wp_members_transactions_meta`
   - Stores additional transaction data
   - Supports custom fields for transaction records
   - Enables integration with external systems

### Database Reliability Features

- **Auto-verification**: Automatically checks for required database tables before operations
- **Table Creation**: Dynamically creates missing tables to prevent data loss
- **Fallback Storage**: Uses WordPress user meta as backup storage if tables are unavailable
- **Admin Notifications**: Alerts administrators about database issues with one-click repair

### Form Processing System

- **User Registration**: Creates WordPress users, assigns roles, and logs users in automatically
- **Subscription Processing**: Creates subscription records, processes payments, and manages access rights
- **Admin Post Handlers**: Uses WordPress admin-post.php handlers for form submission

### Template System

- **Custom Templates**: Provides dedicated templates for product display and checkout
- **Theme Override Support**: Allows theme customization while maintaining core functionality
- **Fallback System**: Multi-layered template fallback to ensure display in any theme

## Integration Points

### WordPress Core

- Integrates with WordPress users and roles system
- Uses WordPress custom post types for product management
- Leverages WordPress rewrite rules for product URLs

### Members Plugin

- Extends Members plugin role management capabilities
- Adds subscription capabilities to the Members access control system
- Maintains compatibility with the Members UI

## Usage

### Creating Products

1. Go to Members > Subscription Products
2. Add a new product with title, description, and pricing details
3. Configure access control by selecting which roles to assign
4. Optionally set a redirect URL for after purchase

### Displaying Products

Products can be displayed using:

1. **Automatic Archives**: Visit /membership-products/ to see all products
2. **Single Product Pages**: Each product has its own dedicated page
3. **Shortcodes**: 
   - `[members_subscription_form product_id="123"]` - Display just the subscription form
   - `[members_product_details product_id="123"]` - Display product details with form

### User Registration & Checkout

The add-on provides a combined registration and checkout process:

1. New users see a registration form with checkout details
2. Existing users see only the checkout form
3. On form submission:
   - New users are created
   - Subscription records are generated
   - WordPress roles are assigned
   - Users are redirected to content or thank you page

## System Resilience

The Members Subscriptions add-on includes several features to ensure reliable operation:

- **Multi-layered Database Operations**: 
  - Gracefully handles missing or corrupted database tables
  - Uses three independent methods for database operations with automatic fallback
  - Includes duplicate prevention with explicit existence checks
  - Sequential execution of creation methods with intelligent success tracking
  
- **Triple-redundant Storage System**: 
  - Primary storage: Custom database tables (optimized for performance)
  - Secondary storage: WordPress user meta (for user-specific backup)
  - Tertiary storage: WordPress options table (for global system backup)
  - Additional mapping table for cross-referencing across storage methods
  
- **Comprehensive Error Detection & Handling**:
  - Detailed error logging with diagnostic information
  - Status tracking for all insertion attempts and methods
  - Clean retry mechanisms that prevent duplicate records
  - Success reporting to identify which methods worked
  
- **Self-healing Capabilities**:
  - Automatic table creation with simple schema for maximum compatibility
  - Minimal field requirements to maximize success probability
  - Direct SQL operations that bypass potential middleware issues
  - Last resort mechanisms for challenging environments
  
- **Diagnostic & Administrative Tools**:
  - Built-in debug-subscriptions.php tool with comprehensive reporting
  - One-click repair options for database issues
  - Status tracking for troubleshooting subscription problems
  - Data recovery from fallback storage mechanisms

## Future Development

- Additional payment gateway integrations (Stripe, PayPal)
- Subscription management capabilities (pause, resume, upgrade)
- More advanced content restriction options
- Enhanced reporting and analytics
- Improved migration tools for importing from other membership plugins
- Advanced caching for high-performance sites