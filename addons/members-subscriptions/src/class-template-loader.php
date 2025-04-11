<?php

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Template loader for Members Subscriptions
 */
class Template_Loader {

    /**
     * Initialize the template loader
     */
    public static function init() {
        // Filter template include to load our templates
        add_filter('template_include', [__CLASS__, 'template_include'], 99);
        
        // Filter single template
        add_filter('single_template', [__CLASS__, 'single_product_template'], 99);
        
        // Filter archive template
        add_filter('archive_template', [__CLASS__, 'archive_product_template'], 99);
        
        // Add body classes
        add_filter('body_class', [__CLASS__, 'add_body_classes']);
        
        // Enhance product queries
        add_action('pre_get_posts', [__CLASS__, 'enhance_product_queries']);
        
        // Force the proper template when directly visiting products
        add_action('wp', [__CLASS__, 'maybe_force_proper_template']);
    }
    
    /**
     * Add body classes for product pages
     * 
     * @param array $classes Array of existing classes
     * @return array Modified array of classes
     */
    public static function add_body_classes($classes) {
        if (is_singular('members_product')) {
            $classes[] = 'members-product-single';
            $classes[] = 'members-product';
        }
        
        if (is_post_type_archive('members_product')) {
            $classes[] = 'members-product-archive';
            $classes[] = 'members-product';
        }
        
        return $classes;
    }
    
    /**
     * Enhance product queries
     *
     * @param WP_Query $query The query object
     */
    public static function enhance_product_queries($query) {
        // Only modify main queries on frontend
        if (!is_admin() && $query->is_main_query()) {
            // For product archive pages
            if (is_post_type_archive('members_product')) {
                error_log('Members Subscriptions: Enhancing product archive query');
                // Set posts per page
                $query->set('posts_per_page', apply_filters('members_product_posts_per_page', 12));
                // Default ordering by title
                if (!$query->get('orderby')) {
                    $query->set('orderby', 'title');
                    $query->set('order', 'ASC');
                }
            }
            
            // For single product pages, ensure we get the exact product
            if (is_singular('members_product')) {
                error_log('Members Subscriptions: Enhancing single product query');
                $query->set('post_type', 'members_product');
            }
        }
    }
    
    /**
     * Force the proper template when directly visiting products
     * This is a last-resort fix for 404 issues
     */
    public static function maybe_force_proper_template() {
        global $wp_query, $post;
        
        // Check if we should be viewing a product but got a 404 instead
        if ($wp_query->is_404() && isset($_SERVER['REQUEST_URI'])) {
            $request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
            $product_slug = 'membership-products';
            
            // Check if the URL starts with our product slug
            if (strpos($request_path, $product_slug) === 0) {
                error_log('Members Subscriptions: Potential 404 for product, attempting to fix: ' . $request_path);
                
                // Get the item slug after the product base
                $item_path = trim(str_replace($product_slug, '', $request_path), '/');
                
                if (!empty($item_path)) {
                    // Try to find the product by slug
                    $product = get_page_by_path($item_path, OBJECT, 'members_product');
                    
                    if ($product) {
                        error_log('Members Subscriptions: Found product by slug, fixing 404: ' . $product->post_title);
                        
                        // Reset the 404 status
                        $wp_query->is_404 = false;
                        
                        // Setup as a single product
                        $wp_query->is_singular = true;
                        $wp_query->is_single = true;
                        
                        // Set the post
                        $wp_query->post = $product;
                        $wp_query->posts = [$product];
                        $wp_query->queried_object = $product;
                        $wp_query->queried_object_id = $product->ID;
                        $wp_query->post_count = 1;
                        $wp_query->current_post = 0;
                        $wp_query->in_the_loop = true;
                        
                        // Set global post
                        $post = $product;
                        setup_postdata($post);
                        
                        // Set the proper status code
                        status_header(200);
                    }
                } elseif ($request_path === $product_slug) {
                    // This is probably the archive page
                    error_log('Members Subscriptions: Potential 404 for product archive, attempting to fix');
                    
                    // Reset the 404 status
                    $wp_query->is_404 = false;
                    
                    // Setup as an archive
                    $wp_query->is_archive = true;
                    $wp_query->is_post_type_archive = true;
                    $wp_query->set('post_type', 'members_product');
                    
                    // Set the proper status code
                    status_header(200);
                    
                    // Get products
                    $posts = get_posts([
                        'post_type' => 'members_product',
                        'posts_per_page' => 12,
                        'orderby' => 'title',
                        'order' => 'ASC'
                    ]);
                    
                    // Set the queried posts
                    $wp_query->posts = $posts;
                    $wp_query->post_count = count($posts);
                    
                    // Set the first post if available
                    if (!empty($posts)) {
                        $wp_query->post = $posts[0];
                        $wp_query->current_post = 0;
                        $post = $posts[0];
                        setup_postdata($post);
                    }
                }
            }
        }
    }
    
    /**
     * Template include filter - main entry point for all templates
     *
     * @param string $template Template path
     * @return string Modified template path
     */
    public static function template_include($template) {
        global $post;
        
        // For single product pages
        if (is_singular('members_product')) {
            error_log('Members Subscriptions: Loading single product template');
            
            // Try WordPress default template hierarchy first (root level files)
            $plugin_dir = plugin_dir_path(dirname(__DIR__));
            $wptemplate = $plugin_dir . 'single-members_product.php';
            
            if (file_exists($wptemplate)) {
                error_log('Members Subscriptions: Found root template file: ' . $wptemplate);
                return $wptemplate;
            }
            
            // Fallback to our template part system
            return self::get_template_part('single-product.php', $template);
        }
        
        // For product archive pages
        if (is_post_type_archive('members_product')) {
            error_log('Members Subscriptions: Loading archive product template');
            
            // Try WordPress default template hierarchy first (root level files)
            $plugin_dir = plugin_dir_path(dirname(__DIR__));
            $wptemplate = $plugin_dir . 'archive-members_product.php';
            
            if (file_exists($wptemplate)) {
                error_log('Members Subscriptions: Found root archive template file: ' . $wptemplate);
                return $wptemplate;
            }
            
            // Fallback to our template part system
            return self::get_template_part('archive-product.php', $template);
        }
        
        // Also check by direct post comparison for better reliability
        if ($post && $post->post_type === 'members_product') {
            error_log('Members Subscriptions: Loading product template via post type check');
            
            // Try WordPress default template hierarchy first (root level files)
            $plugin_dir = plugin_dir_path(dirname(__DIR__));
            $wptemplate = $plugin_dir . 'single-members_product.php';
            
            if (file_exists($wptemplate)) {
                error_log('Members Subscriptions: Found root template file via post check: ' . $wptemplate);
                return $wptemplate;
            }
            
            // Fallback to our template part system
            return self::get_template_part('single-product.php', $template);
        }
        
        return $template;
    }
    
    /**
     * Single product template filter
     * This is mainly a backup for the template_include filter
     *
     * @param string $template Template path
     * @return string Modified template path
     */
    public static function single_product_template($template) {
        if (is_singular('members_product')) {
            error_log('Members Subscriptions: Loading single product template via single_template filter');
            
            // Try WordPress default template hierarchy first (root level files)
            $plugin_dir = plugin_dir_path(dirname(__DIR__));
            $wptemplate = $plugin_dir . 'single-members_product.php';
            
            if (file_exists($wptemplate)) {
                error_log('Members Subscriptions: Found root template file in single_template: ' . $wptemplate);
                return $wptemplate;
            }
            
            // Fallback to our template part system
            return self::get_template_part('single-product.php', $template);
        }
        
        global $post;
        if ($post && $post->post_type === 'members_product') {
            error_log('Members Subscriptions: Loading single product template via post check in single_template');
            
            // Try WordPress default template hierarchy first (root level files)
            $plugin_dir = plugin_dir_path(dirname(__DIR__));
            $wptemplate = $plugin_dir . 'single-members_product.php';
            
            if (file_exists($wptemplate)) {
                error_log('Members Subscriptions: Found root template file via post check in single_template: ' . $wptemplate);
                return $wptemplate;
            }
            
            // Fallback to our template part system
            return self::get_template_part('single-product.php', $template);
        }
        
        return $template;
    }
    
    /**
     * Archive product template filter
     * This is mainly a backup for the template_include filter
     *
     * @param string $template Template path
     * @return string Modified template path
     */
    public static function archive_product_template($template) {
        if (is_post_type_archive('members_product')) {
            error_log('Members Subscriptions: Loading archive product template via archive_template filter');
            
            // Try WordPress default template hierarchy first (root level files)
            $plugin_dir = plugin_dir_path(dirname(__DIR__));
            $wptemplate = $plugin_dir . 'archive-members_product.php';
            
            if (file_exists($wptemplate)) {
                error_log('Members Subscriptions: Found root archive template file in archive_template: ' . $wptemplate);
                return $wptemplate;
            }
            
            // Fallback to our template part system
            return self::get_template_part('archive-product.php', $template);
        }
        
        return $template;
    }
    
    /**
     * Get template part from our plugin
     *
     * @param string $template_name Template name
     * @param string $default_path Default template path as fallback
     * @return string Template path
     */
    public static function get_template_part($template_name, $default_path = '') {
        // Look for the template in the theme first
        $theme_template = locate_template([
            'members/subscriptions/' . $template_name,
            'members/' . $template_name,
            'members-subscriptions/' . $template_name
        ]);
        
        if ($theme_template) {
            error_log('Members Subscriptions: Found template in theme: ' . $theme_template);
            return $theme_template;
        }
        
        // If not found in theme, use our template
        $plugin_path = plugin_dir_path(dirname(__DIR__));
        $plugin_template_path = $plugin_path . 'templates/' . $template_name;
        
        if (file_exists($plugin_template_path)) {
            error_log('Members Subscriptions: Using plugin template: ' . $plugin_template_path);
            return $plugin_template_path;
        }
        
        // Try fallback template
        if (!empty($default_path) && file_exists($default_path)) {
            error_log('Members Subscriptions: Using fallback template: ' . $default_path);
            return $default_path;
        }
        
        // Last resort - try to create a basic template
        if ($template_name === 'single-product.php' || $template_name === 'single-members_product.php') {
            error_log('Members Subscriptions: No template found, attempting direct template rendering for single product');
            self::render_single_product_directly();
            exit; // Stop further processing
        } else if ($template_name === 'archive-product.php' || $template_name === 'archive-members_product.php') {
            error_log('Members Subscriptions: No template found, attempting direct template rendering for archive');
            self::render_archive_products_directly();
            exit; // Stop further processing
        }
        
        error_log('Members Subscriptions: No suitable template found for ' . $template_name);
        return $default_path; // Return whatever was passed in
    }
    
    /**
     * Direct rendering of a product template as a last resort
     * This bypasses the template system entirely
     */
    private static function render_single_product_directly() {
        global $post;
        
        if (!$post || $post->post_type !== 'members_product') {
            return;
        }
        
        get_header();
        
        echo '<div class="container" style="padding: 40px 20px; max-width: 1200px; margin: 0 auto;">';
        echo '<h1>' . get_the_title() . '</h1>';
        
        echo '<div class="content">';
        echo apply_filters('the_content', $post->post_content);
        echo '</div>';
        
        // Get product meta
        $price = get_post_meta($post->ID, '_price', true);
        if (is_array($price)) {
            $price = reset($price);
        }
        $price = !empty($price) ? floatval($price) : 0;
        
        $recurring = get_post_meta($post->ID, '_recurring', true);
        $recurring = !empty($recurring) && $recurring !== '0';
        
        $period = get_post_meta($post->ID, '_period', true);
        $period = !empty($period) ? intval($period) : 1;
        
        $period_type = get_post_meta($post->ID, '_period_type', true);
        $period_type = !empty($period_type) ? $period_type : 'month';
        
        $period_options = array(
            'day' => __('day(s)', 'members'),
            'week' => __('week(s)', 'members'),
            'month' => __('month(s)', 'members'),
            'year' => __('year(s)', 'members'),
        );
        
        $period_label = isset($period_options[$period_type]) ? $period_options[$period_type] : $period_type;
        
        echo '<div style="margin-top: 30px; padding: 20px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<h3>Membership Details</h3>';
        echo '<p><strong>Price:</strong> $' . number_format($price, 2);
        
        if ($recurring) {
            echo ' every ' . $period . ' ' . $period_label;
        }
        
        echo '</p>';
        echo '</div>';
        
        echo '<div style="margin-top: 30px;">';
        echo '<h3>Subscribe Now</h3>';
        
        // Include the subscription form
        if (class_exists('\\Members\\Subscriptions\\Plugin')) {
            $plugin = Plugin::get_instance();
            echo $plugin->subscription_form_shortcode(['product_id' => $post->ID]);
        } else {
            // Fallback in case the plugin instance isn't available
            if (is_user_logged_in()) {
                echo '<p><a href="' . esc_url(add_query_arg('product_id', $post->ID, site_url('/checkout/'))) . '" class="button" style="display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">Subscribe Now</a></p>';
            } else {
                echo '<p>Please <a href="' . wp_login_url(get_permalink()) . '" style="color: #0073aa;">login</a> to purchase this membership.</p>';
            }
        }
        
        echo '</div>';
        echo '</div>';
        
        get_footer();
    }
    
    /**
     * Direct rendering of products archive as a last resort
     * This bypasses the template system entirely
     */
    private static function render_archive_products_directly() {
        get_header();
        
        // Get products
        $products = get_posts([
            'post_type' => 'members_product',
            'posts_per_page' => 12,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        echo '<div class="container" style="padding: 40px 20px; max-width: 1200px; margin: 0 auto;">';
        echo '<h1>Membership Products</h1>';
        
        if (!empty($products)) {
            echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 30px; margin-top: 30px;">';
            
            foreach ($products as $product) {
                // Get product meta
                $price = get_post_meta($product->ID, '_price', true);
                if (is_array($price)) {
                    $price = reset($price);
                }
                $price = !empty($price) ? floatval($price) : 0;
                
                $recurring = get_post_meta($product->ID, '_recurring', true);
                $recurring = !empty($recurring) && $recurring !== '0';
                
                $period = get_post_meta($product->ID, '_period', true);
                $period = !empty($period) ? intval($period) : 1;
                
                $period_type = get_post_meta($product->ID, '_period_type', true);
                $period_type = !empty($period_type) ? $period_type : 'month';
                
                $period_options = array(
                    'day' => __('day(s)', 'members'),
                    'week' => __('week(s)', 'members'),
                    'month' => __('month(s)', 'members'),
                    'year' => __('year(s)', 'members'),
                );
                
                $period_label = isset($period_options[$period_type]) ? $period_options[$period_type] : $period_type;
                
                echo '<div style="border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">';
                echo '<div style="padding: 20px;">';
                echo '<h2><a href="' . get_permalink($product) . '" style="text-decoration: none; color: #333;">' . get_the_title($product) . '</a></h2>';
                
                if (has_post_thumbnail($product)) {
                    echo '<div style="margin-bottom: 15px;">';
                    echo '<a href="' . get_permalink($product) . '">';
                    echo get_the_post_thumbnail($product, 'medium', ['style' => 'width: 100%; height: auto; display: block;']);
                    echo '</a>';
                    echo '</div>';
                }
                
                echo '<div style="margin-bottom: 15px;">' . get_the_excerpt($product) . '</div>';
                
                echo '<div style="margin-bottom: 15px;"><strong>Price:</strong> $' . number_format($price, 2);
                
                if ($recurring) {
                    echo ' every ' . $period . ' ' . $period_label;
                }
                
                echo '</div>';
                
                echo '<a href="' . get_permalink($product) . '" style="display: inline-block; padding: 8px 16px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">View Details</a>';
                echo '</div>';
                echo '</div>';
            }
            
            echo '</div>';
        } else {
            echo '<p>No membership products found.</p>';
        }
        
        echo '</div>';
        
        get_footer();
    }
}

// Initialize template loader
add_action('init', [__NAMESPACE__ . '\Template_Loader', 'init']);