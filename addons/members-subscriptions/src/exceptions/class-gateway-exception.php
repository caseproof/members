<?php

namespace Members\Subscriptions\Exceptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Exception thrown when there's an error with a payment gateway
 */
class Gateway_Exception extends Members_Exception {
    
    /**
     * The gateway ID
     * 
     * @var string
     */
    protected $gateway_id;
    
    /**
     * Constructor
     * 
     * @param string $message Error message
     * @param string $gateway_id The gateway ID
     * @param int $code Error code
     * @param \Throwable $previous Previous exception
     * @param array $data Additional error data
     */
    public function __construct($message = '', $gateway_id = '', $code = 0, \Throwable $previous = null, array $data = []) {
        $this->gateway_id = $gateway_id;
        $data['gateway_id'] = $gateway_id;
        
        parent::__construct($message, $code, $previous, $data);
    }
    
    /**
     * Get the gateway ID
     * 
     * @return string
     */
    public function get_gateway_id() {
        return $this->gateway_id;
    }
}