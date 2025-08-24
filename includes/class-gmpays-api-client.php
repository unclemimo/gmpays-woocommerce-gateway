<?php
/**
 * GMPays API Client Class - Refactored for Official API Structure
 *
 * Handles all API communications with GMPays payment processor using correct endpoints
 *
 * @package GMPaysWooCommerceGateway
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GMPays_API_Client Class
 */
class GMPays_API_Client {
    
    /** @var int */
    private $project_id;
    
    /** @var string */
    private $private_key_content;
    
    /** @var string */
    private $hmac_key;
    
    /** @var string */
    private $auth_method;
    
    /** @var bool */
    private $debug;
    
    /** @var string */
    private $api_base_url;
    
    /**
     * Constructor
     * 
     * @param int|string $project_id GMPays Project ID (e.g., 603)
     * @param string $key HMAC key or RSA private key content
     * @param string $api_url API base URL from GMPays settings
     * @param string $auth_method Authentication method: 'hmac' or 'rsa'
     */
    public function __construct($project_id, $key, $api_url = null, $auth_method = 'hmac') {
        $this->project_id = intval($project_id);
        $this->auth_method = $auth_method;
        
        if ($auth_method === 'hmac') {
            $this->hmac_key = $key;
        } else {
            $this->private_key_content = $key;
        }
        
        // Set API base URL (should come from GMPays settings)
        $this->api_base_url = $api_url ?: 'https://pay.gmpays.com';
        
        // Get debug setting from WooCommerce
        $settings = get_option('woocommerce_gmpays_credit_card_settings', array());
        $this->debug = isset($settings['debug']) && $settings['debug'] === 'yes';
        
        if ($this->debug) {
            $this->log('info', 'GMPays API Client initialized with Project ID: ' . $this->project_id);
            $this->log('info', 'Authentication Method: ' . $this->auth_method);
            $this->log('info', 'API Base URL: ' . $this->api_base_url);
        }
    }
    
    /**
     * Convert array to string for signature according to GMPays specification
     *
     * @param array $data Data to convert
     * @return string Formatted string for signature
     */
    private function array_to_string($data) {
        // Remove signature field if present
        unset($data['signature']);
        
        // Sort by keys alphabetically
        ksort($data);
        
        $result = '';
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // For arrays, process recursively
                if ($this->is_assoc($value)) {
                    // Associative array
                    $result .= $key . ':' . $this->array_to_string_recursive($value) . ';';
                } else {
                    // Indexed array
                    $result .= $key . ':';
                    foreach ($value as $index => $item) {
                        $result .= $index . ':' . $item . ';';
                    }
                    $result .= ';';
                }
            } else {
                // For scalar values
                $result .= $key . ':' . $value . ';';
            }
        }
        
        return $result;
    }
    
    /**
     * Recursive array to string conversion
     */
    private function array_to_string_recursive($data) {
        ksort($data);
        $result = '';
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result .= $key . ':' . $this->array_to_string_recursive($value) . ';';
            } else {
                $result .= $key . ':' . $value . ';';
            }
        }
        
        return $result;
    }
    
    /**
     * Check if array is associative
     */
    private function is_assoc($arr) {
        if (!is_array($arr)) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
    
    /**
     * Generate signature for request data
     *
     * @param array $data Request data
     * @return string Generated signature
     */
    private function generate_signature($data) {
        try {
            // Convert data to string according to GMPays specification
            $string_to_sign = $this->array_to_string($data);
            
            if ($this->debug) {
                $this->log('debug', 'String to sign: ' . $string_to_sign);
            }
            
            if ($this->auth_method === 'hmac') {
                // Generate HMAC signature
                if (empty($this->hmac_key)) {
                    throw new Exception('HMAC key is not configured');
                }
                
                $signature = hash_hmac('sha256', $string_to_sign, $this->hmac_key);
                
                if ($this->debug) {
                    $this->log('debug', 'Generated HMAC signature: ' . $signature);
                }
                
                return $signature;
                
            } else {
                // Generate RSA signature
                if (empty($this->private_key_content)) {
                    throw new Exception('RSA private key is not configured');
                }
                
                $private_key = openssl_pkey_get_private($this->private_key_content);
                if (!$private_key) {
                    throw new Exception('Failed to load RSA private key: ' . openssl_error_string());
                }
                
                $signature_binary = '';
                $result = openssl_sign($string_to_sign, $signature_binary, $private_key, OPENSSL_ALGO_SHA256);
                openssl_free_key($private_key);
                
                if (!$result) {
                    throw new Exception('Failed to generate RSA signature: ' . openssl_error_string());
                }
                
                $signature = base64_encode($signature_binary);
                
                if ($this->debug) {
                    $this->log('debug', 'Generated RSA signature: ' . $signature);
                }
                
                return $signature;
            }
            
        } catch (Exception $e) {
            $this->log('error', 'Signature generation failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create an invoice/payment session via Terminal (recommended method)
     *
     * @param array $order_data Order data from WooCommerce
     * @return array|false Response data or false on failure
     */
    public function create_invoice($order_data) {
        if ($this->auth_method === 'hmac' && empty($this->hmac_key)) {
            $this->log('error', 'HMAC key not configured');
            throw new Exception('Payment gateway configuration error: HMAC key missing');
        } elseif ($this->auth_method === 'rsa' && empty($this->private_key_content)) {
            $this->log('error', 'Private key not configured');
            throw new Exception('Payment gateway configuration error: Private key missing');
        }
        
        try {
            // Prepare invoice data according to GMPays Terminal API specification
            $request_data = array(
                'project' => strval($this->project_id),
                'user' => strval($order_data['order_id']),
                'ip' => $order_data['customer_ip'] ?? $_SERVER['REMOTE_ADDR'],
                'amount' => number_format($order_data['amount'], 2, '.', ''),
                'comment' => substr($order_data['description'], 0, 255),
                'project_invoice' => strval($order_data['order_id']),
                'success_url' => $order_data['return_url'],
                'fail_url' => $order_data['cancel_url'],
                'currency' => 'USD', // GMPays processes in USD
            );
            
            // Add optional fields
            if (!empty($order_data['webhook_url'])) {
                $request_data['callback_url'] = $order_data['webhook_url'];
            }
            
            if (!empty($order_data['customer_email'])) {
                $request_data['add_email'] = $order_data['customer_email'];
            }
            
            if (!empty($order_data['customer_name'])) {
                $name_parts = explode(' ', $order_data['customer_name'], 2);
                if (count($name_parts) >= 2) {
                    $request_data['add_first_name'] = $name_parts[0];
                    $request_data['add_last_name'] = $name_parts[1];
                } else {
                    $request_data['add_first_name'] = $order_data['customer_name'];
                }
            }
            
            // Generate signature
            $request_data['signature'] = $this->generate_signature($request_data);
            
            $this->log('info', 'Creating terminal invoice with project ID: ' . $this->project_id);
            $this->log('debug', 'Request data (without signature): ' . json_encode(array_diff_key($request_data, ['signature' => ''])));
            
            // Use the terminal/create endpoint as per GMPays documentation
            $response = $this->send_request('/api/terminal/create', $request_data);
            
            if (!$response || !isset($response['state']) || $response['state'] !== 'success') {
                $error_msg = isset($response['error']) ? $response['error'] : 'Unknown error';
                throw new Exception('Failed to create payment session: ' . $error_msg);
            }
            
            $this->log('info', 'Terminal payment session created successfully');
            
            // Extract payment URL from response
            $payment_url = isset($response['url']) ? $response['url'] : null;
            
            // If no payment URL, construct it
            if (!$payment_url) {
                $payment_url = $this->api_base_url . '/api/terminal/create';
            }
            
            return array(
                'success' => true,
                'invoice_id' => $order_data['order_id'], // Use order ID as invoice ID for terminal approach
                'payment_url' => $payment_url,
                'data' => $response
            );
            
        } catch (Exception $e) {
            $this->log('error', 'Create invoice error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send HTTP request to GMPays API
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|false Response data or false on failure
     */
    private function send_request($endpoint, $data) {
        $url = $this->api_base_url . $endpoint;
        
        // Convert array to URL-encoded string
        $body = http_build_query($data);
        
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => $body,
        );
        
        $this->log('debug', 'Sending request to: ' . $url);
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            $this->log('error', 'Request failed: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $this->log('debug', 'Response code: ' . $response_code);
        $this->log('debug', 'Response body: ' . $response_body);
        
        // Try to decode JSON response
        $decoded = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If not JSON, might be HTML error page
            $this->log('error', 'Failed to decode response as JSON');
            return array('success' => false, 'error' => 'Invalid response format');
        }
        
        return $decoded;
    }
    
    /**
     * Get invoice status from GMPays using the correct endpoint
     *
     * @param string $invoice_id GMPays invoice ID or project_invoice
     * @return array|false Response data or false on failure
     */
    public function get_invoice_status($invoice_id) {
        if (empty($invoice_id)) {
            $this->log('error', 'Invoice ID is required to get status');
            return false;
        }
        
        try {
            // Prepare request data for status check using project_invoice
            $request_data = array(
                'project' => strval($this->project_id),
                'project_invoice' => strval($invoice_id),
            );
            
            // Generate signature
            $request_data['signature'] = $this->generate_signature($request_data);
            
            $this->log('info', 'Getting invoice status for project_invoice: ' . $invoice_id);
            $this->log('debug', 'Status request data (without signature): ' . json_encode(array_diff_key($request_data, ['signature' => ''])));
            
            // Send status request to GMPays API using the correct endpoint
            $response = $this->send_request('/api/invoice/status', $request_data);
            
            if (!$response) {
                $this->log('error', 'Failed to get invoice status for project_invoice: ' . $invoice_id);
                return false;
            }
            
            if (isset($response['state']) && $response['state'] === 'success') {
                $this->log('info', 'Invoice status retrieved successfully for project_invoice: ' . $invoice_id);
                $this->log('debug', 'Status response: ' . json_encode($response));
                return $response;
            } else {
                $error_msg = isset($response['error']) ? $response['error'] : 'Unknown error';
                $this->log('error', 'Failed to get invoice status: ' . $error_msg);
                return false;
            }
            
        } catch (Exception $e) {
            $this->log('error', 'Get invoice status error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log messages
     *
     * @param string $level Log level (info, error, debug)
     * @param string $message Log message
     */
    private function log($level, $message) {
        if (!$this->debug && $level === 'debug') {
            return;
        }
        
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $context = array('source' => 'gmpays-gateway');
            
            switch ($level) {
                case 'error':
                    $logger->error($message, $context);
                    break;
                case 'info':
                    $logger->info($message, $context);
                    break;
                case 'debug':
                default:
                    $logger->debug($message, $context);
                    break;
            }
        } else {
            error_log('[GMPays ' . $level . '] ' . $message);
        }
    }
    
    /**
     * Test connection to GMPays
     *
     * @return bool
     */
    public function test_connection() {
        if ($this->auth_method === 'hmac' && empty($this->hmac_key)) {
            $this->log('error', 'Cannot test connection - HMAC key not configured');
            return false;
        } elseif ($this->auth_method === 'rsa' && empty($this->private_key_content)) {
            $this->log('error', 'Cannot test connection - private key not configured');
            return false;
        }
        
        try {
            // Test if we can generate a signature
            $test_data = array(
                'project' => strval($this->project_id),
                'test' => 'connection'
            );
            
            $signature = $this->generate_signature($test_data);
            
            if ($signature) {
                $this->log('info', 'Connection test successful - signature generated');
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log('error', 'Connection test failed: ' . $e->getMessage());
            return false;
        }
    }
}