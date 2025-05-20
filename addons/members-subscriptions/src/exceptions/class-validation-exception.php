<?php

namespace Members\Subscriptions\Exceptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Exception thrown when there's a validation error
 */
class Validation_Exception extends Members_Exception {
    
    /**
     * Validation errors
     * 
     * @var array
     */
    protected $errors = [];
    
    /**
     * Constructor
     * 
     * @param string $message Error message
     * @param array $errors Validation errors
     * @param int $code Error code
     * @param \Throwable $previous Previous exception
     * @param array $data Additional error data
     */
    public function __construct($message = '', array $errors = [], $code = 0, \Throwable $previous = null, array $data = []) {
        $this->errors = $errors;
        $data['errors'] = $errors;
        
        parent::__construct($message, $code, $previous, $data);
    }
    
    /**
     * Get validation errors
     * 
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }
}