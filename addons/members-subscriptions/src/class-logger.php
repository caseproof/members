<?php

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Logger class for handling all logging
 */
class Logger {

    /**
     * Log levels
     */
    const DEBUG = 'debug';
    const INFO = 'info';
    const NOTICE = 'notice';
    const WARNING = 'warning';
    const ERROR = 'error';
    const CRITICAL = 'critical';
    
    /**
     * Instance of the logger
     * 
     * @var self
     */
    private static $instance;
    
    /**
     * Whether to log to database
     * 
     * @var bool
     */
    private $db_logging = true;
    
    /**
     * Whether to log to file
     * 
     * @var bool
     */
    private $file_logging = true;
    
    /**
     * Log file path
     * 
     * @var string
     */
    private $log_file;
    
    /**
     * Get the logger instance (singleton)
     * 
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $uploads_dir = wp_upload_dir();
        $this->log_file = trailingslashit($uploads_dir['basedir']) . 'members-subscriptions/logs/debug.log';
        
        // Create logs directory if it doesn't exist
        $logs_dir = trailingslashit($uploads_dir['basedir']) . 'members-subscriptions/logs';
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
            
            // Add .htaccess file to protect logs
            $htaccess_file = $logs_dir . '/.htaccess';
            if (!file_exists($htaccess_file)) {
                $htaccess_content = "# Prevent directory listing\nOptions -Indexes\n\n# Prevent direct access to files\n<FilesMatch \".*\">\nOrder Allow,Deny\nDeny from all\n</FilesMatch>";
                @file_put_contents($htaccess_file, $htaccess_content);
            }
        }
    }
    
    /**
     * Log a debug message
     * 
     * @param string $message
     * @param array $context
     */
    public function debug($message, array $context = []) {
        $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * Log an info message
     * 
     * @param string $message
     * @param array $context
     */
    public function info($message, array $context = []) {
        $this->log(self::INFO, $message, $context);
    }
    
    /**
     * Log a notice message
     * 
     * @param string $message
     * @param array $context
     */
    public function notice($message, array $context = []) {
        $this->log(self::NOTICE, $message, $context);
    }
    
    /**
     * Log a warning message
     * 
     * @param string $message
     * @param array $context
     */
    public function warning($message, array $context = []) {
        $this->log(self::WARNING, $message, $context);
    }
    
    /**
     * Log an error message
     * 
     * @param string $message
     * @param array $context
     */
    public function error($message, array $context = []) {
        $this->log(self::ERROR, $message, $context);
    }
    
    /**
     * Log a critical message
     * 
     * @param string $message
     * @param array $context
     */
    public function critical($message, array $context = []) {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    /**
     * Log a message
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log($level, $message, array $context = []) {
        // Log to database
        if ($this->db_logging) {
            $this->log_to_db($level, $message, $context);
        }
        
        // Log to file
        if ($this->file_logging) {
            $this->log_to_file($level, $message, $context);
        }
        
        // Log to WordPress debug.log
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $formatted_message = $this->format_message($level, $message, $context);
            error_log($formatted_message);
        }
    }
    
    /**
     * Log to database
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log_to_db($level, $message, array $context = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'members_logs';
        
        // Check if the table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if ($table_exists) {
            $wpdb->insert(
                $table_name,
                [
                    'level'      => $level,
                    'message'    => $message,
                    'context'    => !empty($context) ? json_encode($context) : null,
                    'created_at' => current_time('mysql'),
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );
        }
    }
    
    /**
     * Log to file
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log_to_file($level, $message, array $context = []) {
        $formatted_message = $this->format_message($level, $message, $context);
        @file_put_contents($this->log_file, $formatted_message . PHP_EOL, FILE_APPEND);
    }
    
    /**
     * Format message for logging
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     * @return string
     */
    private function format_message($level, $message, array $context = []) {
        $timestamp = current_time('mysql');
        $context_string = !empty($context) ? ' ' . json_encode($context) : '';
        
        return "[{$timestamp}] [{$level}] {$message}{$context_string}";
    }
    
    /**
     * Get logs from database
     * 
     * @param array $args
     * @return array
     */
    public function get_logs($args = []) {
        global $wpdb;
        
        $defaults = [
            'level'     => '',
            'limit'     => 100,
            'offset'    => 0,
            'order'     => 'DESC',
            'orderby'   => 'created_at',
            'date_from' => '',
            'date_to'   => '',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'members_logs';
        
        // Validate table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            return [];
        }
        
        $where = [];
        $where_values = [];
        
        if (!empty($args['level'])) {
            $where[] = 'level = %s';
            $where_values[] = $args['level'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Sanitize order and orderby
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'created_at DESC';
        
        $limit = absint($args['limit']);
        $offset = absint($args['offset']);
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name $where_clause ORDER BY $orderby LIMIT %d OFFSET %d",
            array_merge($where_values, [$limit, $offset])
        );
        
        $logs = $wpdb->get_results($query);
        
        return $logs;
    }
    
    /**
     * Clear logs
     * 
     * @param string $level If provided, only clear logs of this level
     * @return int Number of logs deleted
     */
    public function clear_logs($level = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'members_logs';
        
        // Validate table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            return 0;
        }
        
        if (!empty($level)) {
            $result = $wpdb->delete(
                $table_name,
                ['level' => $level],
                ['%s']
            );
        } else {
            $result = $wpdb->query("TRUNCATE TABLE $table_name");
            $result = $result !== false ? true : false;
        }
        
        return $result;
    }
}