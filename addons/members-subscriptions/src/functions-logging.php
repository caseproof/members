<?php

namespace Members\Subscriptions;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Log a message with the logger
 * 
 * @param string $message The message to log
 * @param string $level The log level (debug, info, notice, warning, error, critical)
 * @param array $context Additional context data
 */
function log_message($message, $level = 'info', array $context = []) {
    $logger = Logger::get_instance();
    
    switch ($level) {
        case 'debug':
            $logger->debug($message, $context);
            break;
        case 'info':
            $logger->info($message, $context);
            break;
        case 'notice':
            $logger->notice($message, $context);
            break;
        case 'warning':
            $logger->warning($message, $context);
            break;
        case 'error':
            $logger->error($message, $context);
            break;
        case 'critical':
            $logger->critical($message, $context);
            break;
        default:
            $logger->info($message, $context);
            break;
    }
}

/**
 * Get logs from the database
 * 
 * @param array $args Query arguments
 * @return array
 */
function get_logs($args = []) {
    $logger = Logger::get_instance();
    return $logger->get_logs($args);
}

/**
 * Clear logs from the database
 * 
 * @param string $level If provided, only clear logs of this level
 * @return int Number of logs deleted
 */
function clear_logs($level = '') {
    $logger = Logger::get_instance();
    return $logger->clear_logs($level);
}