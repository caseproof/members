<?php

namespace Members\Subscriptions\Exceptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Exception thrown when there's a database error
 */
class DB_Exception extends Members_Exception {
    
    /**
     * The SQL query
     * 
     * @var string
     */
    protected $query;
    
    /**
     * Constructor
     * 
     * @param string $message Error message
     * @param string $query The SQL query
     * @param int $code Error code
     * @param \Throwable $previous Previous exception
     * @param array $data Additional error data
     */
    public function __construct($message = '', $query = '', $code = 0, \Throwable $previous = null, array $data = []) {
        $this->query = $query;
        $data['query'] = $query;
        
        parent::__construct($message, $code, $previous, $data);
    }
    
    /**
     * Get the SQL query
     * 
     * @return string
     */
    public function get_query() {
        return $this->query;
    }
}