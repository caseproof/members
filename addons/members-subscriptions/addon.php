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
        require_once $this->dir . 'src/Activator.php';
        require_once $this->dir . 'src/Plugin.php';
        require_once $this->dir . 'src/functions-db.php';
        require_once $this->dir . 'src/class-admin-notifications.php';
        
        // Check for and include these files if they exist
        $files = array(
            'src/functions-capabilities.php',
            'src/functions-roles.php',
            'src/functions-products.php',
            'src/functions-subscriptions.php',
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
        return members_is_addon_active( 'members-subscriptions' );
    }

    /**
     * Activates the add-on.
     *
     * @since  1.0.0
     * @return void
     */
    public function activate() {
        // Set the addon to active
        members_activate_addon( 'members-subscriptions' );
        
        // Run activator if needed
        if ( class_exists( '\Members\Subscriptions\Activator' ) ) {
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
        // Set the addon to inactive
        members_deactivate_addon( 'members-subscriptions' );
        
        // Run any deactivation tasks
        do_action( 'members_subscriptions_deactivate' );
    }
}

// Initialize the addon
new Addon();