<?php

namespace Members\Subscriptions\Exceptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Base exception class for Members Subscriptions
 */
class Members_Exception extends \Exception {
    
    /**
     * Additional error data
     * 
     * @var array
     */
    protected $data = [];
    
    /**
     * Constructor
     * 
     * @param string $message Error message
     * @param int $code Error code
     * @param \Throwable $previous Previous exception
     * @param array $data Additional error data
     */
    public function __construct($message = '', $code = 0, \Throwable $previous = null, array $data = []) {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
        
        // Log the exception
        $this->log_exception();
    }
    
    /**
     * Get additional error data
     * 
     * @return array
     */
    public function get_data() {
        return $this->data;
    }
    
    /**
     * Log the exception
     */
    protected function log_exception() {
        if (function_exists('\\Members\\Subscriptions\\log_message')) {
            $context = [
                'code' => $this->getCode(),
                'file' => $this->getFile(),
                'line' => $this->getLine(),
                'trace' => $this->getTraceAsString(),
                'data' => $this->get_data(),
            ];
            
            \Members\Subscriptions\log_message(
                sprintf('Exception: %s', $this->getMessage()),
                'error',
                $context
            );
        }
    }
}