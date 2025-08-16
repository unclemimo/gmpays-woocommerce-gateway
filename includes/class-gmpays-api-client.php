<?php
/**
 * GMPays API Client Class - Corrected Version with proper RSA Implementation
 *
 * Handles all API communications with GMPays payment processor using RSA signatures
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
    private $gmpays_certificate;
    
    /** @var bool */
    private $debug;
    
    /** @var string */
    private $api_base_url;
    
    /**
     * Constructor
     * 
     * @param int|string $project_id GMPays Project ID (e.g., 603)
     * @param string $private_key Private key content
     * @param string $api_url API base URL from GMPays settings
     */
    public function __construct($project_id, $private_key, $api_url = null) {
        $this->project_id = intval($project_id);
        $this->private_key_content = $private_key;
        
        // Set API base URL (should come from GMPays settings)
        $this->api_base_url = $api_url ?: 'https://paygate.gamemoney.com';
        
        // GMPays certificate for verifying their signatures
        $this->gmpays_certificate = '-----BEGIN CERTIFICATE-----
MIID3TCCAsWgAwIBAgIJANtAJ3UMiGLZMA0GCSqGSIb3DQEBCwUAMIGEMQswCQYD
VQQGEwJDUjERMA8GA1UECAwIU2FuIEpvc2UxEjAQBgNVBAcMCVNhbnRhIEFuYTET
MBEGA1UECgwKSUJTIFMuUi5MLjEeMBwGA1UEAwwVY2hlY2tpbi5nYW1lbW9uZXku
Y29tMRkwFwYJKoZIhvcNAQkBFgpjZW9AaWJzLmNyMB4XDTE2MDkwNDA3MjY0OVoX
DTI2MDkwNjA3MjY0OVowgYQxCzAJBgNVBAYTAkNSMREwDwYDVQQIDAhTYW4gSm9z
ZTESMBAGA1UEBwwJU2FudGEgQW5hMRMwEQYDVQQKDApJQlMgUy5SLkwuMR4wHAYD
VQQDDBVjaGVja2luLmdhbWVtb25leS5jb20xGTAXBgkqhkiG9w0BCQEWCmNlb0Bp
YnMuY3IwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDPK9ODW+BcqZ5P
YlQWziyLzKImuE8EDn7XuE9ZDmpKiJxwDUKZHSQYH4QtHyx0qYAbIqIGrKemfTu1
nvW9+O8yKLFLLcaXVaSLU7mpp8uSWasGbkLqE7xVLqxQZq1zrBSEPnKR1/dNxD83
5pNzMLx7ki02t1J01MAj0FvQ3GemIAQU15m+w+9YKX8cUYEBm+h2KuY6uziLhJTM
BAQRIXO3z4fkZhUi/0wpiy5Zxi2jbh07gab7me5dpxxwOs/Dt10S6J8qu+AAH0DE
3diqQS4OcaCYJuIo/kJVxrn8TO2WKzSf2CBMWMzKtA2lOIMokC79gsrTFAasRM8j
BQkvD5/TAgMBAAGjUDBOMB0GA1UdDgQWBBTqyL+faltohjM5faVuKxRUBCG25DAf
BgNVHSMEGDAWgBTqyL+faltohjM5faVuKxRUBCG25DAMBgNVHRMEBTADAQH/MA0G
CSqGSIb3DQEBCwUAA4IBAQBpnItyLrXE1RWdGE+xoJ2YEbjNtgEfzaypFPpVuMO0
hvCV1rJJDS3zs/P1uSF0akqN4weSGOeFuFyGf3v2j40M1T1XdllOA8ucBv7Rfy2W
l1rR2aU+Hd6rl0E/7lhtx3VUobgPVKZn8k7NMKkSMtF/f2cS9jI4i6gsJfFImMr9
gWjEZAlmwQcnilZ6ZUhhn+0yHFbCoX8fETH/sZlofZOnN8EkETyqyfThIpTX41XH
geivrSfScNQH1mWJuE0DXMobNrhyJpxHRJwXXQyck6TiDM8OETki4PBzjKk/Gsyc
61gqP9VPE2AVVwKypWOJidMxMZ03n4+MlyTbz3mnBXL9
-----END CERTIFICATE-----';
        
        // Get debug setting from WooCommerce
        $settings = get_option('woocommerce_gmpays_credit_card_settings', array());
        $this->debug = isset($settings['debug']) && $settings['debug'] === 'yes';
        
        if ($this->debug) {
            $this->log('info', 'GMPays API Client initialized with Project ID: ' . $this->project_id);
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
     * Generate RSA signature for request data
     *
     * @param array $data Request data
     * @return string Base64 encoded signature
     */
    private function generate_signature($data) {
        if (empty($this->private_key_content)) {
            throw new Exception('Private key not configured');
        }
        
        // Convert data to string according to GMPays specification
        $string_to_sign = $this->array_to_string($data);
        
        $this->log('debug', 'String to sign: ' . $string_to_sign);
        
        // Get private key resource
        $private_key = openssl_pkey_get_private($this->private_key_content);
        if (!$private_key) {
            throw new Exception('Invalid private key: ' . openssl_error_string());
        }
        
        // Sign with SHA256
        $signature = '';
        $success = openssl_sign($string_to_sign, $signature, $private_key, OPENSSL_ALGO_SHA256);
        
        if (!$success) {
            throw new Exception('Failed to generate signature: ' . openssl_error_string());
        }
        
        // Encode signature in base64
        $encoded_signature = base64_encode($signature);
        
        $this->log('debug', 'Generated signature: ' . substr($encoded_signature, 0, 50) . '...');
        
        return $encoded_signature;
    }
    
    /**
     * Create an invoice/payment session
     *
     * @param array $order_data Order data from WooCommerce
     * @return array|false Response data or false on failure
     */
    public function create_invoice($order_data) {
        if (empty($this->private_key_content)) {
            $this->log('error', 'Private key not configured');
            throw new Exception('Payment gateway configuration error: Private key missing');
        }
        
        try {
            // Prepare invoice data according to GMPays API specification
            $request_data = array(
                'project' => strval($this->project_id),
                'user' => strval($order_data['order_id']),
                'ip' => $order_data['customer_ip'] ?? $_SERVER['REMOTE_ADDR'],
                'amount' => number_format($order_data['amount'], 2, '.', ''),
                'currency' => 'USD',
                'type' => 'card', // For credit card payment
                'description' => substr($order_data['description'], 0, 255),
                'project_invoice' => strval($order_data['order_id']),
                'success_url' => $order_data['return_url'],
                'fail_url' => $order_data['cancel_url'],
            );
            
            // Add optional fields
            if (!empty($order_data['webhook_url'])) {
                $request_data['callback_url'] = $order_data['webhook_url'];
            }
            
            if (!empty($order_data['customer_email'])) {
                $request_data['email'] = $order_data['customer_email'];
            }
            
            // Generate signature
            $request_data['signature'] = $this->generate_signature($request_data);
            
            $this->log('info', 'Creating invoice with project ID: ' . $this->project_id);
            $this->log('debug', 'Request data (without signature): ' . json_encode(array_diff_key($request_data, ['signature' => ''])));
            
            // Send request to GMPays
            $response = $this->send_request('/invoice/create', $request_data);
            
            if (!$response || !isset($response['success']) || !$response['success']) {
                $error_msg = isset($response['error']) ? $response['error'] : 'Unknown error';
                throw new Exception('Failed to create payment session: ' . $error_msg);
            }
            
            $this->log('info', 'Invoice created successfully');
            
            // Extract invoice ID and payment URL from response
            $invoice_id = isset($response['data']['invoice']) ? $response['data']['invoice'] : null;
            $payment_url = isset($response['data']['url']) ? $response['data']['url'] : null;
            
            if (!$payment_url && $invoice_id) {
                // Construct payment URL if not provided
                $payment_url = $this->api_base_url . '/invoice/' . $invoice_id;
            }
            
            return array(
                'success' => true,
                'invoice_id' => $invoice_id,
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
     * Verify webhook signature from GMPays
     *
     * @param array $data Webhook data including signature
     * @return bool
     */
    public function verify_webhook_signature($data) {
        if (!isset($data['signature'])) {
            $this->log('error', 'No signature in webhook data');
            return false;
        }
        
        $received_signature = $data['signature'];
        
        // Create a copy of data without signature for verification
        $verify_data = $data;
        unset($verify_data['signature']);
        
        // Convert data to string
        $string_to_verify = $this->array_to_string($verify_data);
        
        $this->log('debug', 'String to verify: ' . $string_to_verify);
        
        // Get public key from GMPays certificate
        $public_key = openssl_pkey_get_public($this->gmpays_certificate);
        if (!$public_key) {
            $this->log('error', 'Failed to extract public key from certificate');
            return false;
        }
        
        // Decode the signature from base64
        $signature_binary = base64_decode($received_signature);
        
        // Verify signature
        $result = openssl_verify($string_to_verify, $signature_binary, $public_key, OPENSSL_ALGO_SHA256);
        
        if ($result === 1) {
            $this->log('info', 'Webhook signature verified successfully');
            return true;
        } elseif ($result === 0) {
            $this->log('error', 'Webhook signature verification failed');
            return false;
        } else {
            $this->log('error', 'Error during signature verification: ' . openssl_error_string());
            return false;
        }
    }
    
    /**
     * Process callback/webhook from GMPays
     *
     * @param array $data Webhook data
     * @return array|false Processed data or false on failure
     */
    public function process_callback($data) {
        try {
            // Verify signature
            if (!$this->verify_webhook_signature($data)) {
                $this->log('error', 'Webhook signature verification failed');
                return false;
            }
            
            $this->log('info', 'Processing verified callback: ' . json_encode($data));
            
            return array(
                'success' => true,
                'data' => $data
            );
            
        } catch (Exception $e) {
            $this->log('error', 'Callback processing failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get invoice status
     *
     * @param string $invoice_id Invoice ID
     * @return array|false Response data or false on failure
     */
    public function get_invoice_status($invoice_id) {
        try {
            $request_data = array(
                'project' => strval($this->project_id),
                'invoice' => $invoice_id,
            );
            
            // Generate signature
            $request_data['signature'] = $this->generate_signature($request_data);
            
            $response = $this->send_request('/invoice/status', $request_data);
            
            if (!$response || !isset($response['success'])) {
                return false;
            }
            
            $this->log('info', 'Invoice status retrieved');
            
            return array(
                'success' => true,
                'status' => $response['data']['status'] ?? 'unknown',
                'data' => $response
            );
            
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
        if (empty($this->private_key_content)) {
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