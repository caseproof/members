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
        add_filter('template_include', [__CLASS__, 'template_include']);
        
        // Filter single template
        add_filter('single_template', [__CLASS__, 'single_product_template']);
        
        // Filter archive template
        add_filter('archive_template', [__CLASS__, 'archive_product_template']);
    }
    
    /**
     * Template include filter
     *
     * @param string $template Template path
     * @return string Modified template path
     */
    public static function template_include($template) {
        if (is_post_type_archive('members_product')) {
            return self::get_template_part('archive-product.php', $template);
        }
        
        return $template;
    }
    
    /**
     * Single product template filter
     *
     * @param string $template Template path
     * @return string Modified template path
     */
    public static function single_product_template($template) {
        if (is_singular('members_product')) {
            return self::get_template_part('single-product.php', $template);
        }
        
        return $template;
    }
    
    /**
     * Archive product template filter
     *
     * @param string $template Template path
     * @return string Modified template path
     */
    public static function archive_product_template($template) {
        if (is_post_type_archive('members_product')) {
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
        $template = locate_template(['members/subscriptions/' . $template_name]);
        
        // If not found in theme, use our template
        if (!$template) {
            $plugin_path = plugin_dir_path(dirname(__DIR__));
            $template_path = $plugin_path . 'templates/' . $template_name;
            
            if (file_exists($template_path)) {
                $template = $template_path;
            }
        }
        
        // If still not found, use default
        if (!$template) {
            $template = $default_path;
        }
        
        return $template;
    }
}

// Initialize template loader
add_action('init', [__NAMESPACE__ . '\Template_Loader', 'init']);