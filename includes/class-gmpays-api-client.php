<?php
/**
 * GMPays API Client Class
 *
 * Handles all API communications with GMPays payment processor
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
    
    /**
     * API endpoints
     */
    const API_BASE_URL = 'https://api.gmpays.com/api/';
    const API_BASE_URL_TEST = 'https://api.pay.gmpays.com/api/';
    
    /**
     * Project ID
     */
    private $project_id;
    
    /**
     * API Key
     */
    private $api_key;
    
    /**
     * HMAC Key for signature verification
     */
    private $hmac_key;
    
    /**
     * Test mode flag
     */
    private $testmode;
    
    /**
     * Debug mode flag
     */
    private $debug;
    
    /**
     * Constructor
     */
    public function __construct($project_id, $api_key, $hmac_key, $testmode = false) {
        $this->project_id = $project_id;
        $this->api_key = $api_key;
        $this->hmac_key = $hmac_key;
        $this->testmode = $testmode;
        
        // Get debug setting from gateway options
        $gateway_settings = get_option('woocommerce_gmpays_credit_card_settings', array());
        $this->debug = isset($gateway_settings['debug']) && $gateway_settings['debug'] === 'yes';
    }
    
    /**
     * Get API base URL
     */
    private function get_api_url() {
        return $this->testmode ? self::API_BASE_URL_TEST : self::API_BASE_URL;
    }
    
    /**
     * Create an invoice/payment session
     *
     * @param array $invoice_data Invoice data
     * @return array|false Response data or false on failure
     */
    public function create_invoice($invoice_data) {
        $endpoint = 'invoice';
        
        // Prepare the request data according to GMPays API documentation
        $request_data = array(
            'project' => $this->project_id,
            'amount' => round($invoice_data['amount'], 2), // Amount in USD
            'currency' => 'USD', // GMPays primarily works with USD
            'type' => 'payment', // Payment type
            'comment' => $invoice_data['description'],
            'user' => $invoice_data['customer']['email'],
            'ip' => $this->get_customer_ip(),
            'success_url' => $invoice_data['success_url'],
            'fail_url' => $invoice_data['cancel_url'],
            'wallet' => 'card', // Credit card payment method
            'add_fields' => array(
                'order_id' => $invoice_data['order_id'],
                'order_key' => $invoice_data['order_key'],
                'customer_name' => $invoice_data['customer']['name'],
                'customer_email' => $invoice_data['customer']['email'],
                'customer_phone' => $invoice_data['customer']['phone'],
                'customer_address' => json_encode($invoice_data['customer']['address']),
            ),
        );
        
        // Add signature
        $request_data['signature'] = $this->generate_signature($request_data);
        
        // Make API request
        $response = $this->make_request($endpoint, $request_data, 'POST');
        
        if ($response && isset($response['id'])) {
            return array(
                'invoice_id' => $response['id'],
                'payment_url' => $this->build_payment_url($response['id']),
                'status' => $response['status'] ?? 'pending',
            );
        }
        
        return false;
    }
    
    /**
     * Check invoice/payment status
     *
     * @param string $invoice_id Invoice ID
     * @return array|false Response data or false on failure
     */
    public function check_invoice_status($invoice_id) {
        $endpoint = 'invoice/status';
        
        $request_data = array(
            'project' => $this->project_id,
            'invoice' => $invoice_id,
        );
        
        // Add signature
        $request_data['signature'] = $this->generate_signature($request_data);
        
        return $this->make_request($endpoint, $request_data, 'POST');
    }
    
    /**
     * Refund a payment
     *
     * @param string $invoice_id Invoice ID
     * @param float $amount Amount to refund
     * @param string $reason Refund reason
     * @return array|false Response data or false on failure
     */
    public function refund_payment($invoice_id, $amount, $reason = '') {
        $endpoint = 'invoice/refund';
        
        $request_data = array(
            'project' => $this->project_id,
            'invoice' => $invoice_id,
            'amount' => round($amount, 2),
            'comment' => $reason ?: 'Refund requested',
        );
        
        // Add signature
        $request_data['signature'] = $this->generate_signature($request_data);
        
        return $this->make_request($endpoint, $request_data, 'POST');
    }
    
    /**
     * Cancel an invoice
     *
     * @param string $invoice_id Invoice ID
     * @return array|false Response data or false on failure
     */
    public function cancel_invoice($invoice_id) {
        $endpoint = 'invoice/cancel';
        
        $request_data = array(
            'project' => $this->project_id,
            'invoice' => $invoice_id,
        );
        
        // Add signature
        $request_data['signature'] = $this->generate_signature($request_data);
        
        return $this->make_request($endpoint, $request_data, 'POST');
    }
    
    /**
     * Verify webhook signature
     *
     * @param array $data Webhook data
     * @param string $signature Received signature
     * @return boolean True if signature is valid
     */
    public function verify_webhook_signature($data, $signature) {
        // Remove the signature from data before verification
        unset($data['signature']);
        
        // Generate expected signature
        $expected_signature = $this->generate_signature($data);
        
        // Compare signatures
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Generate HMAC signature for API request
     *
     * @param array $data Request data
     * @return string Generated signature
     */
    private function generate_signature($data) {
        // Sort data by keys
        ksort($data);
        
        // Build signature string according to GMPays documentation
        $signature_string = '';
        foreach ($data as $key => $value) {
            if ($key !== 'signature' && !is_array($value) && !is_object($value)) {
                $signature_string .= $value . ':';
            }
        }
        
        // Add HMAC key at the end
        $signature_string .= $this->hmac_key;
        
        // Generate MD5 hash (GMPays uses MD5 for signatures)
        return md5($signature_string);
    }
    
    /**
     * Make HTTP request to GMPays API
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array|false Response data or false on failure
     */
    private function make_request($endpoint, $data, $method = 'POST') {
        $url = $this->get_api_url() . $endpoint;
        
        if ($this->debug) {
            wc_get_logger()->debug('GMPays API Request to ' . $url . ': ' . print_r($data, true), array('source' => 'gmpays-api'));
        }
        
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
            'body' => json_encode($data),
            'data_format' => 'body',
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            if ($this->debug) {
                wc_get_logger()->error('GMPays API Error: ' . $response->get_error_message(), array('source' => 'gmpays-api'));
            }
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($this->debug) {
            wc_get_logger()->debug('GMPays API Response (' . $response_code . '): ' . $response_body, array('source' => 'gmpays-api'));
        }
        
        if ($response_code >= 200 && $response_code < 300) {
            $data = json_decode($response_body, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }
        
        return false;
    }
    
    /**
     * Build payment URL for redirect
     *
     * @param string $invoice_id Invoice ID
     * @return string Payment URL
     */
    private function build_payment_url($invoice_id) {
        $base_url = $this->testmode ? 'https://checkout.pay.gmpays.com' : 'https://checkout.gmpays.com';
        return $base_url . '/invoice/' . $invoice_id;
    }
    
    /**
     * Get customer IP address
     *
     * @return string Customer IP address
     */
    private function get_customer_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ips = explode(',', $_SERVER[$key]);
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }
    
    /**
     * Process webhook notification
     *
     * @param array $webhook_data Webhook data from GMPays
     * @return boolean True if processed successfully
     */
    public function process_webhook($webhook_data) {
        // Verify signature first
        if (!isset($webhook_data['signature'])) {
            if ($this->debug) {
                wc_get_logger()->error('GMPays webhook missing signature', array('source' => 'gmpays-api'));
            }
            return false;
        }
        
        $signature = $webhook_data['signature'];
        if (!$this->verify_webhook_signature($webhook_data, $signature)) {
            if ($this->debug) {
                wc_get_logger()->error('GMPays webhook signature verification failed', array('source' => 'gmpays-api'));
            }
            return false;
        }
        
        // Process based on webhook type
        if (isset($webhook_data['type'])) {
            switch ($webhook_data['type']) {
                case 'payment':
                    return $this->process_payment_webhook($webhook_data);
                case 'refund':
                    return $this->process_refund_webhook($webhook_data);
                default:
                    if ($this->debug) {
                        wc_get_logger()->info('Unknown GMPays webhook type: ' . $webhook_data['type'], array('source' => 'gmpays-api'));
                    }
            }
        }
        
        return true;
    }
    
    /**
     * Process payment webhook
     *
     * @param array $data Webhook data
     * @return boolean
     */
    private function process_payment_webhook($data) {
        if (!isset($data['invoice']) || !isset($data['status'])) {
            return false;
        }
        
        // Find order by invoice ID
        $orders = wc_get_orders(array(
            'meta_key' => '_gmpays_invoice_id',
            'meta_value' => $data['invoice'],
            'limit' => 1,
        ));
        
        if (empty($orders)) {
            if ($this->debug) {
                wc_get_logger()->warning('Order not found for GMPays invoice: ' . $data['invoice'], array('source' => 'gmpays-api'));
            }
            return false;
        }
        
        $order = $orders[0];
        
        // Update order based on payment status
        switch ($data['status']) {
            case 'paid':
            case 'success':
                $order->payment_complete($data['invoice']);
                $order->add_order_note(sprintf(
                    __('Payment completed via GMPays. Transaction ID: %s', 'gmpays-woocommerce-gateway'),
                    $data['invoice']
                ));
                
                // Save transaction details
                $order->update_meta_data('_gmpays_transaction_id', $data['invoice']);
                $order->update_meta_data('_gmpays_payment_status', 'completed');
                $order->update_meta_data('_gmpays_payment_completed_at', current_time('mysql'));
                break;
                
            case 'fail':
            case 'failed':
                $order->update_status('failed', __('Payment failed via GMPays', 'gmpays-woocommerce-gateway'));
                $order->update_meta_data('_gmpays_payment_status', 'failed');
                break;
                
            case 'cancel':
            case 'cancelled':
                $order->update_status('cancelled', __('Payment cancelled via GMPays', 'gmpays-woocommerce-gateway'));
                $order->update_meta_data('_gmpays_payment_status', 'cancelled');
                break;
        }
        
        $order->save();
        return true;
    }
    
    /**
     * Process refund webhook
     *
     * @param array $data Webhook data
     * @return boolean
     */
    private function process_refund_webhook($data) {
        // Handle refund notifications
        if (!isset($data['invoice'])) {
            return false;
        }
        
        // Find order by invoice ID
        $orders = wc_get_orders(array(
            'meta_key' => '_gmpays_invoice_id',
            'meta_value' => $data['invoice'],
            'limit' => 1,
        ));
        
        if (!empty($orders)) {
            $order = $orders[0];
            $order->add_order_note(sprintf(
                __('Refund processed via GMPays. Amount: %s', 'gmpays-woocommerce-gateway'),
                isset($data['amount']) ? wc_price($data['amount']) : 'N/A'
            ));
        }
        
        return true;
    }
}
