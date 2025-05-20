<?php

namespace Members\Subscriptions\Migrations;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Base Migration class that all migrations should extend
 */
abstract class Migration {

    /**
     * The version this migration represents
     * 
     * @var string
     */
    protected $version;
    
    /**
     * Description of what this migration does
     * 
     * @var string
     */
    protected $description;
    
    /**
     * Get migration version
     * 
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * Get migration description
     * 
     * @return string
     */
    public function get_description() {
        return $this->description;
    }
    
    /**
     * Run the up migration
     * 
     * @return bool True if successful, false otherwise
     */
    abstract public function up();
    
    /**
     * Run the down migration
     * 
     * @return bool True if successful, false otherwise
     */
    abstract public function down();
    
    /**
     * Log migration information
     * 
     * @param string $message The message to log
     * @param string $type The type of log message (info, error, warning)
     * @return void
     */
    protected function log($message, $type = 'info') {
        if (function_exists('\\Members\\Subscriptions\\log_message')) {
            \Members\Subscriptions\log_message("Migration {$this->version}: $message", $type);
        }
    }
}