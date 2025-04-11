<?php

namespace Members\Subscriptions\Migrations;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Migration Manager
 * 
 * Handles running migrations and tracking schema versions
 */
class Migration_Manager {

    /**
     * Option name for storing the schema version
     */
    const VERSION_OPTION = 'members_subscriptions_db_version';

    /**
     * List of available migrations
     * 
     * @var array
     */
    private $migrations = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->register_migrations();
    }

    /**
     * Register all migrations
     */
    private function register_migrations() {
        $this->migrations = [
            new Migration_1_0_0(),
            new Migration_1_0_1(),
            // Add new migrations here
        ];
        
        // Sort migrations by version
        usort($this->migrations, function($a, $b) {
            return version_compare($a->get_version(), $b->get_version());
        });
    }

    /**
     * Get current database version
     * 
     * @return string
     */
    public function get_current_version() {
        return get_option(self::VERSION_OPTION, '0.0.0');
    }

    /**
     * Set the database version
     * 
     * @param string $version
     * @return bool
     */
    public function set_version($version) {
        return update_option(self::VERSION_OPTION, $version);
    }

    /**
     * Get latest migration version
     * 
     * @return string
     */
    public function get_latest_version() {
        if (empty($this->migrations)) {
            return '0.0.0';
        }
        
        $latest = end($this->migrations);
        return $latest->get_version();
    }

    /**
     * Run all pending migrations
     * 
     * @return array Results of the migrations
     */
    public function migrate() {
        $current_version = $this->get_current_version();
        $results = [];
        
        foreach ($this->migrations as $migration) {
            $migration_version = $migration->get_version();
            
            // Skip if migration is older or equal to current version
            if (version_compare($migration_version, $current_version, '<=')) {
                continue;
            }
            
            try {
                $this->log_migration_start($migration);
                
                // Run the migration
                $success = $migration->up();
                
                if ($success) {
                    $this->set_version($migration_version);
                    $this->log_migration_success($migration);
                    $results[] = [
                        'version' => $migration_version,
                        'success' => true,
                        'message' => sprintf(
                            __('Migration to version %s completed successfully.', 'members'), 
                            $migration_version
                        ),
                    ];
                } else {
                    $this->log_migration_failure($migration);
                    $results[] = [
                        'version' => $migration_version,
                        'success' => false,
                        'message' => sprintf(
                            __('Migration to version %s failed.', 'members'), 
                            $migration_version
                        ),
                    ];
                    
                    // Stop migrating if one fails
                    break;
                }
            } catch (\Exception $e) {
                $this->log_migration_exception($migration, $e);
                $results[] = [
                    'version' => $migration_version,
                    'success' => false,
                    'message' => sprintf(
                        __('Migration to version %s failed: %s', 'members'),
                        $migration_version,
                        $e->getMessage()
                    ),
                ];
                
                // Stop migrating if one fails
                break;
            }
        }
        
        return $results;
    }

    /**
     * Roll back to a specific version
     * 
     * @param string $target_version The version to roll back to
     * @return array Results of the rollback
     */
    public function rollback($target_version) {
        $current_version = $this->get_current_version();
        $results = [];
        
        // Can't roll back to a newer version
        if (version_compare($target_version, $current_version, '>=')) {
            return [
                [
                    'version' => $current_version,
                    'success' => false,
                    'message' => sprintf(
                        __('Cannot roll back from %s to %s. Target version must be older.', 'members'),
                        $current_version,
                        $target_version
                    ),
                ],
            ];
        }
        
        // Reverse migrations list to roll back in the correct order
        $migrations = array_reverse($this->migrations);
        
        foreach ($migrations as $migration) {
            $migration_version = $migration->get_version();
            
            // Skip if migration is older or equal to target version
            if (version_compare($migration_version, $target_version, '<=')) {
                continue;
            }
            
            // Skip if migration is newer than current version
            if (version_compare($migration_version, $current_version, '>')) {
                continue;
            }
            
            try {
                $this->log_rollback_start($migration);
                
                // Run the down migration
                $success = $migration->down();
                
                if ($success) {
                    // Find the previous version
                    $prev_version = $target_version;
                    foreach ($this->migrations as $m) {
                        $v = $m->get_version();
                        if (version_compare($v, $migration_version, '<') && version_compare($v, $prev_version, '>')) {
                            $prev_version = $v;
                        }
                    }
                    
                    $this->set_version($prev_version);
                    $this->log_rollback_success($migration);
                    $results[] = [
                        'version' => $migration_version,
                        'success' => true,
                        'message' => sprintf(
                            __('Rolled back from version %s successfully.', 'members'), 
                            $migration_version
                        ),
                    ];
                } else {
                    $this->log_rollback_failure($migration);
                    $results[] = [
                        'version' => $migration_version,
                        'success' => false,
                        'message' => sprintf(
                            __('Rollback from version %s failed.', 'members'), 
                            $migration_version
                        ),
                    ];
                    
                    // Stop rolling back if one fails
                    break;
                }
            } catch (\Exception $e) {
                $this->log_rollback_exception($migration, $e);
                $results[] = [
                    'version' => $migration_version,
                    'success' => false,
                    'message' => sprintf(
                        __('Rollback from version %s failed: %s', 'members'),
                        $migration_version,
                        $e->getMessage()
                    ),
                ];
                
                // Stop rolling back if one fails
                break;
            }
        }
        
        return $results;
    }

    /**
     * Get pending migrations
     * 
     * @return array
     */
    public function get_pending_migrations() {
        $current_version = $this->get_current_version();
        $pending = [];
        
        foreach ($this->migrations as $migration) {
            $migration_version = $migration->get_version();
            
            if (version_compare($migration_version, $current_version, '>')) {
                $pending[] = [
                    'version' => $migration_version,
                    'description' => $migration->get_description(),
                ];
            }
        }
        
        return $pending;
    }

    /**
     * Log migration start
     * 
     * @param Migration $migration
     */
    private function log_migration_start($migration) {
        if (function_exists('\\Members\\Subscriptions\\log_message')) {
            \Members\Subscriptions\log_message(
                sprintf('Starting migration to version %s: %s', $migration->get_version(), $migration->get_description()),
                'info'
            );
        }
    }

    /**
     * Log migration success
     * 
     * @param Migration $migration
     */
    private function log_migration_success($migration) {
        if (function_exists('\\Members\\Subscriptions\\log_message')) {
            \Members\Subscriptions\log_message(
                sprintf('Successfully migrated to version %s', $migration->get_version()),
                'info'
            );
        }
    }

    /**
     * Log migration failure
     * 
     * @param Migration $migration
     */
    private function log_migration_failure($migration) {
        if (function_exists('\\Members\\Subscriptions\\log_message')) {
            \Members\Subscriptions\log_message(
                sprintf('Failed to migrate to version %s', $migration->get_version()),
                'error'
            );
        }
    }

    /**
     * Log migration exception
     * 
     * @param Migration $migration
     * @param \Exception $exception
     */
    private function log_migration_exception($migration, $exception) {
        if (function_exists('\\Members\\Subscriptions\\log_message')) {
            \Members\Subscriptions\log_message(
                sprintf(
                    'Exception during migration to version %s: %s in %s on line %s',
                    $migration->get_version(),
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                ),
                'error'
            );
        }
    }

    /**
     * Log rollback start
     * 
     * @param Migration $migration
     */
    private function log_rollback_start($migration) {
        if (function_exists('\\Members\\Subscriptions\\log_message')) {
            \Members\Subscriptions\log_message(
                sprintf('Starting rollback from version %s', $migration->get_version()),
                'info'
            );
        }
    }

    /**
     * Log rollback success
     * 
     * @param Migration $migration
     */
    private function log_rollback_success($migration) {
        if (function_exists('\\Members\\Subscriptions\\log_message')) {
            \Members\Subscriptions\log_message(
                sprintf('Successfully rolled back from version %s', $migration->get_version()),
                'info'
            );
        }
    }

    /**
     * Log rollback failure
     * 
     * @param Migration $migration
     */
    private function log_rollback_failure($migration) {
        if (function_exists('\\Members\\Subscriptions\\log_message')) {
            \Members\Subscriptions\log_message(
                sprintf('Failed to roll back from version %s', $migration->get_version()),
                'error'
            );
        }
    }

    /**
     * Log rollback exception
     * 
     * @param Migration $migration
     * @param \Exception $exception
     */
    private function log_rollback_exception($migration, $exception) {
        if (function_exists('\\Members\\Subscriptions\\log_message')) {
            \Members\Subscriptions\log_message(
                sprintf(
                    'Exception during rollback from version %s: %s in %s on line %s',
                    $migration->get_version(),
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                ),
                'error'
            );
        }
    }
}