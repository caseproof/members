<?php
/**
 * Utility file to manually trigger rewrite rules flush
 * 
 * Include this file directly (require '/path/to/flush-rules.php')
 * to manually flush rewrite rules when having URL issues with products
 */

// Check for direct file access
if (!defined('ABSPATH')) {
    define('WP_USE_THEMES', false);
    require_once('../../../../wp-load.php');
}

// Make sure user has appropriate permissions
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

/**
 * Display the flush rules page
 */
function flush_rules_page() {
    echo '<div style="max-width: 800px; margin: 40px auto; padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">';
    echo '<h1>Members Subscriptions - Flush Rewrite Rules</h1>';

    // Re-register the post type
    if (class_exists('\\Members\\Subscriptions\\Plugin')) {
        $plugin = \Members\Subscriptions\Plugin::get_instance();
        echo '<p>Re-registering post types...</p>';
        $plugin->register_post_types();
    } else {
        echo '<p>Error: Plugin class not found. Make sure the Members Subscriptions plugin is activated.</p>';
    }

    // Flush rewrite rules
    echo '<p>Flushing rewrite rules...</p>';
    flush_rewrite_rules();
    
    echo '<p style="color: green; font-weight: bold;">✓ Rewrite rules have been flushed successfully!</p>';
    
    echo '<p>Try visiting your product pages now:</p>';
    echo '<ul>';
    echo '<li><a href="' . home_url('/membership-products/') . '">Products Archive</a></li>';
    
    // List existing products
    $products = get_posts(['post_type' => 'members_product', 'numberposts' => 5]);
    if (!empty($products)) {
        foreach ($products as $product) {
            echo '<li><a href="' . get_permalink($product) . '">' . $product->post_title . '</a></li>';
        }
    }
    
    echo '</ul>';
    
    echo '<p>If you continue to experience 404 errors, try these additional troubleshooting steps:</p>';
    echo '<ol>';
    echo '<li>Go to WordPress Settings → Permalinks and click "Save Changes" without changing anything</li>';
    echo '<li>Check that your theme supports custom post types properly</li>';
    echo '<li>Enable debug logging by adding <code>define(\'WP_DEBUG\', true);</code> to your wp-config.php file</li>';
    echo '<li>Try using the root template files at the plugin root level</li>';
    echo '</ol>';
    
    echo '<p><a href="' . admin_url('edit.php?post_type=members_product') . '">Return to Products</a></p>';
    echo '</div>';
}

// Execute the function
flush_rules_page();