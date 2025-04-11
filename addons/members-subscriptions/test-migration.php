<?php
/**
 * Test script for running the database migration
 * 
 * To use this script, visit: /wp-admin/admin.php?page=members-debug&run_migration=1
 * Make sure to delete this file after testing!
 */

// Don't allow direct access
defined('ABSPATH') || exit;

// Add debug action to test migrations
add_action('admin_init', function() {
    // Only run if explicitly requested and user has proper permissions
    if (isset($_GET['page']) && $_GET['page'] === 'members-debug' && 
        isset($_GET['run_migration']) && $_GET['run_migration'] === '1') {
        
        // Verify user can manage options
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to perform this action.');
        }
        
        // Force the migration to run
        require_once __DIR__ . '/src/migrations/class-migration.php';
        require_once __DIR__ . '/src/migrations/class-migration-manager.php';
        require_once __DIR__ . '/src/migrations/class-migration-1-0-0.php';
        require_once __DIR__ . '/src/migrations/class-migration-1-0-1.php';
        require_once __DIR__ . '/src/migrations/class-migration-1-0-2.php';
        
        $migration_manager = new \Members\Subscriptions\Migrations\Migration_Manager();
        
        // Reset DB version to force migration
        update_option('members_subscriptions_db_version', '0.0.0');
        
        // Run migrations
        $results = $migration_manager->migrate();
        
        // Output results
        echo '<h1>Migration Results</h1>';
        echo '<pre>';
        print_r($results);
        echo '</pre>';
        
        // Exit to prevent further processing
        exit;
    }
});