<?php
/**
 * The Template for displaying single members_product post type
 * WordPress will use this file automatically based on template hierarchy
 */

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined('ABSPATH') || exit;

// Load in the header
get_header();

// Get product meta - directly using get_post_meta
$price = get_post_meta(get_the_ID(), '_price', true);
if (is_array($price)) {
    $price = reset($price);
}
$price = !empty($price) ? floatval($price) : 0;

$recurring = get_post_meta(get_the_ID(), '_recurring', true);
$recurring = !empty($recurring) && $recurring !== '0';

$period = get_post_meta(get_the_ID(), '_period', true);
$period = !empty($period) ? intval($period) : 1;

$period_type = get_post_meta(get_the_ID(), '_period_type', true);
$period_type = !empty($period_type) ? $period_type : 'month';

$period_options = array(
    'day' => __('day(s)', 'members'),
    'week' => __('week(s)', 'members'),
    'month' => __('month(s)', 'members'),
    'year' => __('year(s)', 'members'),
);

$period_label = isset($period_options[$period_type]) ? $period_options[$period_type] : $period_type;
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">
        <?php while (have_posts()) : the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class('members-product'); ?>>
                <header class="entry-header">
                    <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
                </header>

                <div class="entry-content">
                    <?php the_content(); ?>
                    
                    <div class="members-product-pricing">
                        <h3><?php _e('Membership Details', 'members'); ?></h3>
                        
                        <div class="members-product-price">
                            <strong><?php _e('Price:', 'members'); ?></strong> 
                            $<?php echo number_format($price, 2); ?>
                            
                            <?php if ($recurring) : ?>
                                <?php printf(__(' every %d %s', 'members'), $period, $period_label); ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="members-subscription-form-container">
                        <h3><?php _e('Subscribe Now', 'members'); ?></h3>
                        <?php 
                        // Include the subscription form
                        if (function_exists('\\Members\\Subscriptions\\Plugin::get_instance')) {
                            $plugin = Plugin::get_instance();
                            echo $plugin->subscription_form_shortcode(['product_id' => get_the_ID()]);
                        } else {
                            // Fallback in case the plugin instance isn't available
                            if (is_user_logged_in()) : ?>
                                <div class="members-product-purchase">
                                    <a href="<?php echo esc_url(add_query_arg('product_id', get_the_ID(), site_url('/checkout/'))); ?>" class="button">
                                        <?php _e('Subscribe Now', 'members'); ?>
                                    </a>
                                </div>
                            <?php else : ?>
                                <div class="members-product-login-required">
                                    <p><?php _e('Please log in to purchase this membership.', 'members'); ?></p>
                                    <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="button">
                                        <?php _e('Log In', 'members'); ?>
                                    </a>
                                </div>
                            <?php endif;
                        } ?>
                    </div>
                </div>
            </article>

        <?php endwhile; ?>
    </main>
</div>

<style>
    .members-product-pricing {
        margin: 2em 0;
        padding: 1.5em;
        background: #f9f9f9;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
    }
    
    .members-product-price {
        font-size: 1.2em;
        margin-bottom: 1em;
    }
    
    .members-product-purchase {
        margin-top: 1.5em;
    }
    
    .members-product-purchase .button,
    .members-product-login-required .button {
        display: inline-block;
        padding: 0.5em 1em;
        background: #0073aa;
        color: white;
        text-decoration: none;
        border-radius: 3px;
    }
    
    .members-product-purchase .button:hover,
    .members-product-login-required .button:hover {
        background: #005d8c;
    }
</style>

<?php
// Load in the footer
get_footer();