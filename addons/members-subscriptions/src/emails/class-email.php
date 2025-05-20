<?php

namespace Members\Subscriptions\Emails;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

use Members\Subscriptions\Exceptions\Members_Exception;

/**
 * Base email class
 */
abstract class Email {

    /**
     * Email ID
     * 
     * @var string
     */
    protected $id = '';
    
    /**
     * Email title
     * 
     * @var string
     */
    protected $title = '';
    
    /**
     * Email description
     * 
     * @var string
     */
    protected $description = '';
    
    /**
     * Email subject
     * 
     * @var string
     */
    protected $subject = '';
    
    /**
     * Email recipients
     * 
     * @var array
     */
    protected $recipients = [];
    
    /**
     * Email headers
     * 
     * @var array
     */
    protected $headers = [];
    
    /**
     * Email attachments
     * 
     * @var array
     */
    protected $attachments = [];
    
    /**
     * Email content type
     * 
     * @var string
     */
    protected $content_type = 'text/html';
    
    /**
     * Email template
     * 
     * @var string
     */
    protected $template = 'default';
    
    /**
     * Email data
     * 
     * @var array
     */
    protected $data = [];
    
    /**
     * Whether the email is enabled
     * 
     * @var bool
     */
    protected $enabled = true;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Set up default headers
        $this->headers = [
            'Content-Type: ' . $this->get_content_type(),
        ];
    }
    
    /**
     * Get email ID
     * 
     * @return string
     */
    public function get_id() {
        return $this->id;
    }
    
    /**
     * Get email title
     * 
     * @return string
     */
    public function get_title() {
        return $this->title;
    }
    
    /**
     * Get email description
     * 
     * @return string
     */
    public function get_description() {
        return $this->description;
    }
    
    /**
     * Get email subject
     * 
     * @return string
     */
    public function get_subject() {
        return $this->subject;
    }
    
    /**
     * Set email subject
     * 
     * @param string $subject
     * @return self
     */
    public function set_subject($subject) {
        $this->subject = $subject;
        return $this;
    }
    
    /**
     * Get email recipients
     * 
     * @return array
     */
    public function get_recipients() {
        return $this->recipients;
    }
    
    /**
     * Set email recipients
     * 
     * @param array|string $recipients
     * @return self
     */
    public function set_recipients($recipients) {
        if (is_string($recipients)) {
            $recipients = explode(',', $recipients);
        }
        
        $this->recipients = array_map('trim', $recipients);
        return $this;
    }
    
    /**
     * Add email recipient
     * 
     * @param string $recipient
     * @return self
     */
    public function add_recipient($recipient) {
        $this->recipients[] = trim($recipient);
        return $this;
    }
    
    /**
     * Get email headers
     * 
     * @return array
     */
    public function get_headers() {
        return $this->headers;
    }
    
    /**
     * Set email headers
     * 
     * @param array $headers
     * @return self
     */
    public function set_headers($headers) {
        $this->headers = $headers;
        return $this;
    }
    
    /**
     * Add email header
     * 
     * @param string $header
     * @return self
     */
    public function add_header($header) {
        $this->headers[] = $header;
        return $this;
    }
    
    /**
     * Get email attachments
     * 
     * @return array
     */
    public function get_attachments() {
        return $this->attachments;
    }
    
    /**
     * Set email attachments
     * 
     * @param array $attachments
     * @return self
     */
    public function set_attachments($attachments) {
        $this->attachments = $attachments;
        return $this;
    }
    
    /**
     * Add email attachment
     * 
     * @param string $attachment
     * @return self
     */
    public function add_attachment($attachment) {
        $this->attachments[] = $attachment;
        return $this;
    }
    
    /**
     * Get email content type
     * 
     * @return string
     */
    public function get_content_type() {
        return $this->content_type;
    }
    
    /**
     * Set email content type
     * 
     * @param string $content_type
     * @return self
     */
    public function set_content_type($content_type) {
        $this->content_type = $content_type;
        
        // Update Content-Type header
        foreach ($this->headers as $key => $header) {
            if (strpos($header, 'Content-Type:') === 0) {
                $this->headers[$key] = 'Content-Type: ' . $content_type;
                return $this;
            }
        }
        
        // Add Content-Type header if it doesn't exist
        $this->add_header('Content-Type: ' . $content_type);
        
        return $this;
    }
    
    /**
     * Get email template
     * 
     * @return string
     */
    public function get_template() {
        return $this->template;
    }
    
    /**
     * Set email template
     * 
     * @param string $template
     * @return self
     */
    public function set_template($template) {
        $this->template = $template;
        return $this;
    }
    
    /**
     * Get email data
     * 
     * @return array
     */
    public function get_data() {
        return $this->data;
    }
    
    /**
     * Set email data
     * 
     * @param array $data
     * @return self
     */
    public function set_data($data) {
        $this->data = $data;
        return $this;
    }
    
    /**
     * Add email data
     * 
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function add_data($key, $value) {
        $this->data[$key] = $value;
        return $this;
    }
    
    /**
     * Check if email is enabled
     * 
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled;
    }
    
    /**
     * Enable/disable email
     * 
     * @param bool $enabled
     * @return self
     */
    public function set_enabled($enabled) {
        $this->enabled = (bool) $enabled;
        return $this;
    }
    
    /**
     * Get email content
     * 
     * @return string
     */
    abstract public function get_content();
    
    /**
     * Get email template path
     * 
     * @return string
     */
    protected function get_template_path() {
        $template_name = $this->template . '.php';
        $template_path = plugin_dir_path(dirname(dirname(__FILE__))) . 'emails/templates/' . $template_name;
        
        // Check if template exists
        if (!file_exists($template_path)) {
            $template_path = plugin_dir_path(dirname(dirname(__FILE__))) . 'emails/templates/default.php';
        }
        
        return $template_path;
    }
    
    /**
     * Format email content with template
     * 
     * @param string $content
     * @return string
     */
    protected function format_email($content) {
        // Get template
        $template_path = $this->get_template_path();
        
        // Check if template exists
        if (!file_exists($template_path)) {
            return $content;
        }
        
        // Extract data variables to make them available in the template
        extract($this->data);
        
        // Start output buffering
        ob_start();
        
        // Include template
        include $template_path;
        
        // Get output
        $formatted_email = ob_get_clean();
        
        // Replace {content} placeholder with actual content
        $formatted_email = str_replace('{content}', $content, $formatted_email);
        
        return $formatted_email;
    }
    
    /**
     * Replace placeholders in a string
     * 
     * @param string $string
     * @return string
     */
    protected function replace_placeholders($string) {
        $placeholders = $this->get_placeholders();
        
        foreach ($placeholders as $key => $value) {
            $string = str_replace('{' . $key . '}', $value, $string);
        }
        
        return $string;
    }
    
    /**
     * Get placeholders for email content
     * 
     * @return array
     */
    protected function get_placeholders() {
        $default_placeholders = [
            'site_name' => get_bloginfo('name'),
            'site_url' => get_bloginfo('url'),
            'admin_email' => get_option('admin_email'),
        ];
        
        return array_merge($default_placeholders, $this->data);
    }
    
    /**
     * Send email
     * 
     * @return bool
     * @throws Members_Exception
     */
    public function send() {
        // Check if email is enabled
        if (!$this->is_enabled()) {
            return false;
        }
        
        // Check if we have recipients
        if (empty($this->recipients)) {
            throw new Members_Exception(
                sprintf('No recipients set for email: %s', $this->id),
                400,
                null,
                ['email_id' => $this->id]
            );
        }
        
        // Get email content
        $content = $this->get_content();
        
        // Format email with template
        $content = $this->format_email($content);
        
        // Replace placeholders in subject
        $subject = $this->replace_placeholders($this->subject);
        
        // Set content type filter
        add_filter('wp_mail_content_type', [$this, 'get_content_type']);
        
        // Send email
        $sent = wp_mail(
            $this->recipients,
            $subject,
            $content,
            $this->headers,
            $this->attachments
        );
        
        // Remove content type filter
        remove_filter('wp_mail_content_type', [$this, 'get_content_type']);
        
        // Log email
        if (function_exists('\\Members\\Subscriptions\\log_message')) {
            $log_level = $sent ? 'info' : 'error';
            $log_message = $sent
                ? sprintf('Email sent: %s', $this->id)
                : sprintf('Failed to send email: %s', $this->id);
            
            \Members\Subscriptions\log_message(
                $log_message,
                $log_level,
                [
                    'email_id' => $this->id,
                    'recipients' => $this->recipients,
                    'subject' => $subject,
                ]
            );
        }
        
        return $sent;
    }
}