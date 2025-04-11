# Members Subscriptions

A subscription add-on for the Members plugin that allows you to create paid memberships with recurring payments.

## Features

- Create membership products with one-time or recurring payments
- Support for multiple payment gateways
- Automatic role assignment and removal
- Subscription management
- Subscription form shortcodes
- Transaction tracking

## Installation

1. Install and activate the Members plugin
2. Upload the members-subscriptions directory to your /wp-content/plugins/members/addons/ directory
3. Activate the add-on from the WordPress admin panel

## Usage

### Creating Membership Products

1. Go to Members > Subscription Products
2. Click "Add New Product"
3. Enter product details, including:
   - Title and description
   - Price information (one-time or recurring)
   - Access control (roles)
   - Redirect URL after purchase

### Displaying Subscription Forms

The plugin automatically adds subscription forms to product pages. You can also use shortcodes to display forms anywhere:

#### Basic Subscription Form

```
[members_subscription_form product_id="123"]
```

This displays a simple subscription form for the specified product.

#### Product Details with Subscription Form

```
[members_product_details product_id="123" show_form="yes"]
```

This displays product details (title, description, price) and a subscription form.

### User Registration & Checkout

The plugin provides a seamless registration and checkout process:

1. New users (not logged in) will see a registration form with payment details
2. Existing users will see just the subscription form
3. After submission:
   - For new users, a WordPress account is created automatically 
   - The user is assigned the appropriate roles
   - A subscription record is created
   - The user is redirected to the content or thank you page

### Payment Processing

The plugin includes a basic manual payment gateway by default. Additional gateways can be configured in the Members > Payment Gateways section.

## Shortcode Reference

### members_subscription_form

Displays a subscription form for a product.

**Parameters:**
- `product_id` (required): The ID of the membership product.

**Example:**
```
[members_subscription_form product_id="123"]
```

### members_product_details

Displays product details with an optional subscription form.

**Parameters:**
- `product_id` (required): The ID of the membership product.
- `show_form` (optional): Whether to display the subscription form. Default: "yes".

**Example:**
```
[members_product_details product_id="123" show_form="yes"]
```

## Customization

You can customize the appearance of subscription forms and product pages using CSS. The plugin includes basic styling, but you can override these styles in your theme's stylesheet.

## Support

For support or feature requests, please create a new issue on GitHub or contact the plugin author.