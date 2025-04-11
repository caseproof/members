<?php
/**
 * The Template for displaying members_product archives
 * WordPress will use this file automatically based on template hierarchy
 */

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined('ABSPATH') || exit;

// Load in the header
get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">
        <header class="page-header">
            <h1 class="page-title"><?php _e('Membership Products', 'members'); ?></h1>
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
                    <div id="post-<?php the_ID(); ?>" <?php post_class('members-product-item'); ?>>
                        <div class="members-product-content">
                            <h2 class="members-product-title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h2>
                            
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="members-product-thumbnail">
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_post_thumbnail('medium'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="members-product-excerpt">
                                <?php the_excerpt(); ?>
                            </div>
                            
                            <div class="members-product-price">
                                <strong><?php _e('Price:', 'members'); ?></strong> 
                                $<?php echo number_format($price, 2); ?>
                                
                                <?php if ($recurring) : ?>
                                    <?php printf(__(' every %d %s', 'members'), $period, $period_label); ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="members-product-actions">
                                <a href="<?php the_permalink(); ?>" class="button members-view-details">
                                    <?php _e('View Details', 'members'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <?php the_posts_navigation(); ?>
            
        <?php else : ?>
            <p><?php _e('No membership products found.', 'members'); ?></p>
        <?php endif; ?>
    </main>
</div>

<style>
    .members-products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 2em;
        margin: 2em 0;
    }
    
    .members-product-item {
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        overflow: hidden;
        transition: box-shadow 0.3s ease;
    }
    
    .members-product-item:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .members-product-content {
        padding: 1.5em;
    }
    
    .members-product-title {
        margin-top: 0;
        font-size: 1.5em;
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
    
    .members-product-price {
        font-size: 1.1em;
        margin: 1em 0;
    }
    
    .members-product-actions {
        margin-top: 1.5em;
    }
    
    .members-view-details {
        display: inline-block;
        padding: 0.5em 1em;
        background: #0073aa;
        color: white;
        text-decoration: none;
        border-radius: 3px;
    }
    
    .members-view-details:hover {
        background: #005d8c;
        color: white;
        text-decoration: none;
    }
</style>

<?php
// Load in the footer
get_footer();