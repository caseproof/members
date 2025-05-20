<?php
/**
 * Add-on Name: Members - Subscriptions
 * Description: Adds subscription and payment functionality to Members. Create membership products with associated roles, process payments via Stripe, and manage user subscriptions. Includes enhanced security features.
 * Version:     1.0.2
 * Author:      MemberPress Team
 * Author URI:  https://members-plugin.com
 */

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class to handle the addon integration.
 */
class Addon {

    /**
     * Plugin's directory path.
     *
     * @var string
     */
    private $dir = '';

    /**
     * Plugin's directory URI.
     *
     * @var string
     */
    private $uri = '';

    /**
     * Main plugin instance.
     *
     * @var Plugin
     */
    private $plugin = null;

    /**
     * Creates the addon object.
     *
     * @since  1.0.0
     * @return void
     */
    public function __construct() {
        $this->dir = trailingslashit( plugin_dir_path( __FILE__ ) );
        $this->uri = trailingslashit( plugin_dir_url( __FILE__ ) );

        // Initialize plugin when WordPress loads
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    /**
     * Initializes the plugin if the Members parent plugin is active.
     *
     * @since  1.0.0
     * @return void
     */
    public function init() {
        // Bootstrap plugin if it's enabled
        if ( $this->is_enabled() ) {
            $this->load_files();
            $this->plugin = Plugin::get_instance();
        }
    }

    /**
     * Loads the required files.
     *
     * @since  1.0.0
     * @return void
     */
    private function load_files() {
        // Load core classes first
        require_once $this->dir . 'src/Activator.php';
        require_once $this->dir . 'src/Plugin.php';
        
        // Load logger-related files early
        require_once $this->dir . 'src/class-logger.php';
        require_once $this->dir . 'src/functions-logging.php';
        
        // Load other required files
        require_once $this->dir . 'src/functions-db.php';
        require_once $this->dir . 'src/class-admin-notifications.php';
        
        // Check for and include these files if they exist
        $files = array(
            'src/functions-capabilities.php',
            'src/functions-roles.php',
            'src/functions-subscriptions.php', // Load this before products.php since it has dependencies
            'src/functions-products.php',
            'src/functions-transactions.php',
            'src/functions-users.php',
            'src/functions-gateways.php',
            'src/gateways/class-gateway.php',
            'src/gateways/class-stripe-gateway.php'
        );
        
        foreach ( $files as $file ) {
            if ( file_exists( $this->dir . $file ) ) {
                require_once $this->dir . $file;
            }
        }
    }

    /**
     * Checks if the addon is enabled.
     *
     * @since  1.0.0
     * @return bool
     */
    public function is_enabled() {
        // Check if the members_is_addon_active function exists
        if (function_exists('members_is_addon_active')) {
            return members_is_addon_active('members-subscriptions');
        }
        
        // Fallback: Check if the main Members plugin is active
        return $this->is_members_active();
    }
    
    /**
     * Check if the main Members plugin is active.
     *
     * @return bool
     */
    private function is_members_active() {
        // First check if get_plugins function exists
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Check for the Members plugin
        $activated = false;
        
        // First try is_plugin_active, which is more reliable but only works after plugins_loaded hook at prio 0
        if (function_exists('is_plugin_active')) {
            $activated = is_plugin_active('members/members.php');
        }
        
        // If that doesn't work, check if the class exists
        if (!$activated && class_exists('\\Members\\Plugin')) {
            $activated = true;
        }
        
        return $activated;
    }

    /**
     * Activates the add-on.
     *
     * @since  1.0.0
     * @return void
     */
    public function activate() {
        // Try to mark addon as active in the main Members plugin
        if (function_exists('members_activate_addon')) {
            members_activate_addon('members-subscriptions');
        } else {
            // Fallback: Store our own activation status
            update_option('members_subscriptions_active', true);
        }
        
        // Run activator 
        if (class_exists('\Members\Subscriptions\Activator')) {
            Activator::activate();
        }
    }

    /**
     * Deactivates the add-on.
     *
     * @since  1.0.0
     * @return void
     */
    public function deactivate() {
        // Try to mark addon as inactive in the main Members plugin
        if (function_exists('members_deactivate_addon')) {
            members_deactivate_addon('members-subscriptions');
        } else {
            // Fallback: Store our own activation status
            update_option('members_subscriptions_active', false);
        }
        
        // Run any deactivation tasks
        do_action('members_subscriptions_deactivate');
    }
}

// Initialize the addon
$members_subscriptions_addon = new Addon();

// Make sure the activation hook is properly registered
register_activation_hook(__FILE__, [$members_subscriptions_addon, 'activate']);