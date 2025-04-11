<?php

namespace Members\Subscriptions\Exceptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Exception thrown when there's an error with an API request
 */
class API_Exception extends Members_Exception {
    
    /**
     * The API response
     * 
     * @var mixed
     */
    protected $response;
    
    /**
     * Constructor
     * 
     * @param string $message Error message
     * @param mixed $response The API response
     * @param int $code Error code
     * @param \Throwable $previous Previous exception
     * @param array $data Additional error data
     */
    public function __construct($message = '', $response = null, $code = 0, \Throwable $previous = null, array $data = []) {
        $this->response = $response;
        $data['response'] = $response;
        
        parent::__construct($message, $code, $previous, $data);
    }
    
    /**
     * Get the API response
     * 
     * @return mixed
     */
    public function get_response() {
        return $this->response;
    }
}