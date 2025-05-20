<?php
/**
 * The Template for displaying the subscription products archive page
 */

namespace Members\Subscriptions\templates;

# Don't execute code if file is accessed directly.
defined('ABSPATH') || exit;

// Load in the header
get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">
        
        <header class="page-header">
            <h1 class="page-title"><?php _e('Membership Plans', 'members'); ?></h1>
        </header>
        
        <?php if (have_posts()) : ?>
            
            <div class="members-products-grid">
                <?php while (have_posts()) : the_post(); 
                    // Get product meta
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
                
                    <div class="members-product-card">
                        <div class="members-product-card-header">
                            <h2 class="members-product-title">
                                <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>
                            </h2>
                        </div>
                        
                        <div class="members-product-card-content">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="members-product-thumbnail">
                                    <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
                                        <?php the_post_thumbnail('medium'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="members-product-excerpt">
                                <?php the_excerpt(); ?>
                            </div>
                            
                            <div class="members-product-price">
                                <strong><?php _e('Price:', 'members'); ?></strong> 
                                <?php echo number_format_i18n($price, 2); ?>
                                
                                <?php if ($recurring) : ?>
                                    <?php printf(__(' every %d %s', 'members'), $period, $period_label); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="members-product-card-footer">
                            <a href="<?php the_permalink(); ?>" class="button"><?php _e('Learn More', 'members'); ?></a>
                            
                            <?php if (is_user_logged_in()) : ?>
                                <a href="<?php echo esc_url(add_query_arg('product_id', get_the_ID(), site_url('/checkout/'))); ?>" class="button button-primary">
                                    <?php _e('Subscribe', 'members'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                
                <?php endwhile; ?>
            </div>
            
            <?php the_posts_pagination(); ?>
            
        <?php else : ?>
            
            <p><?php _e('No membership plans available at this time.', 'members'); ?></p>
            
        <?php endif; ?>
        
    </main>
</div>

<style>
    .members-products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        grid-gap: 2em;
        margin: 2em 0;
    }
    
    .members-product-card {
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .members-product-card-header {
        padding: 1em;
        background: #f5f5f5;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .members-product-card-content {
        padding: 1em;
        flex-grow: 1;
    }
    
    .members-product-card-footer {
        padding: 1em;
        background: #f9f9f9;
        border-top: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
    }
    
    .members-product-title {
        margin: 0;
        font-size: 1.2em;
    }
    
    .members-product-title a {
        text-decoration: none;
        color: #333;
    }
    
    .members-product-thumbnail {
        margin-bottom: 1em;
    }
    
    .members-product-thumbnail img {
        width: 100%;
        height: auto;
        display: block;
    }
    
    .members-product-excerpt {
        margin-bottom: 1em;
    }
    
    .members-product-price {
        font-size: 1.1em;
        margin-bottom: 1em;
    }
    
    .button {
        display: inline-block;
        padding: 0.5em 1em;
        background: #f0f0f0;
        color: #333;
        text-decoration: none;
        border-radius: 3px;
    }
    
    .button:hover {
        background: #e0e0e0;
    }
    
    .button-primary {
        background: #0073aa;
        color: white;
    }
    
    .button-primary:hover {
        background: #005d8c;
    }
</style>

<?php
// Load in the footer
get_footer();