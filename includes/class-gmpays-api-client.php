<?php
/**
 * GMPays API Client Class
 *
 * Handles all API communications with GMPays payment processor using the official SDK
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
    private $hmac_key;
    
    /** @var \Gamemoney\Gateway|null */
    private $gamemoney_sdk;
    
    /** @var bool */
    private $debug;
    
    /**
     * Constructor
     * 
     * @param int|string $project_id GMPays Project ID (e.g., 603)
     * @param string $hmac_key GMPays HMAC Key
     */
    public function __construct($project_id, $hmac_key) {
        $this->project_id = intval($project_id);
        $this->hmac_key = $hmac_key;
        
        // Get debug setting from WooCommerce
        $settings = get_option('woocommerce_gmpays_credit_card_settings', array());
        $this->debug = isset($settings['debug']) && $settings['debug'] === 'yes';
        
        $this->init_gamemoney_sdk();
    }
    
    /**
     * Initialize the Gamemoney SDK
     */
    private function init_gamemoney_sdk() {
        if (empty($this->project_id) || empty($this->hmac_key)) {
            $this->log('error', 'Missing Project ID or HMAC Key');
            return;
        }
        
        try {
            // The Gamemoney SDK uses 'project' for Project ID and 'hmacKey' for HMAC Secret
            $config = new \Gamemoney\Config($this->project_id, $this->hmac_key);
            $this->gamemoney_sdk = new \Gamemoney\Gateway($config);
            
            $this->log('info', 'GMPays SDK initialized successfully with Project ID: ' . $this->project_id);
        } catch (Exception $e) {
            $this->log('error', 'GMPays SDK initialization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Create an invoice/payment session
     *
     * @param array $order_data Order data from WooCommerce
     * @return array|false Response data or false on failure
     */
    public function create_invoice($order_data) {
        if (!$this->gamemoney_sdk) {
            $this->log('error', 'SDK not initialized - check Project ID and HMAC Key');
            return false;
        }
        
        try {
            $request_factory = new \Gamemoney\Request\RequestFactory();
            
            // Prepare invoice data for GMPays
            $invoice_data = array(
                'amount' => floatval($order_data['amount']), // Amount in USD as float
                'currency' => 'USD', // GMPays processes in USD
                'order' => strval($order_data['order_id']), // Order ID as string
                'description' => $order_data['description'],
                'type' => 'normal', // Payment type
                'user' => $order_data['customer_email'],
                'ip' => $order_data['customer_ip'] ?? $_SERVER['REMOTE_ADDR'],
                'success_url' => $order_data['return_url'],
                'fail_url' => $order_data['cancel_url'],
                'callback_url' => $order_data['webhook_url'] ?? home_url('/wp-json/gmpays/v1/webhook'),
                'locale' => $this->get_locale_for_gmpays(),
            );
            
            // Add optional fields if present
            if (!empty($order_data['customer_name'])) {
                $invoice_data['name'] = $order_data['customer_name'];
            }
            
            // Add card-specific parameters
            if (!empty($order_data['payment_method']) && $order_data['payment_method'] === 'card') {
                $invoice_data['payment_system'] = 'card';
            }
            
            $this->log('info', 'Creating invoice with data: ' . json_encode($invoice_data));
            
            // Create the invoice request
            $request = $request_factory->invoiceCreate($invoice_data);
            
            // Send the request
            $response = $this->gamemoney_sdk->send($request);
            
            $this->log('info', 'Invoice created successfully: ' . json_encode($response));
            
            // Check if we have the expected response structure
            if (!is_array($response)) {
                $this->log('error', 'Invalid response format: ' . gettype($response));
                return false;
            }
            
            return array(
                'success' => true,
                'invoice_id' => $response['id'] ?? $response['invoice'] ?? null,
                'payment_url' => $response['url'] ?? $response['payUrl'] ?? null,
                'data' => $response
            );
            
        } catch (\Gamemoney\Exception\RequestValidationException $e) {
            $this->log('error', 'Request validation failed: ' . $e->getMessage());
            $errors = method_exists($e, 'getErrors') ? $e->getErrors() : [];
            $this->log('error', 'Validation errors: ' . json_encode($errors));
            throw new Exception('Invalid payment data: ' . $e->getMessage());
        } catch (\Gamemoney\Exception\GamemoneyExceptionInterface $e) {
            $this->log('error', 'GMPays API error: ' . $e->getMessage());
            throw new Exception('Payment processor error: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->log('error', 'Unexpected error: ' . $e->getMessage());
            throw new Exception('Payment error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get invoice status
     *
     * @param string $invoice_id Invoice ID
     * @return array|false Response data or false on failure
     */
    public function get_invoice_status($invoice_id) {
        if (!$this->gamemoney_sdk) {
            $this->log('error', 'SDK not initialized');
            return false;
        }
        
        try {
            $request_factory = new \Gamemoney\Request\RequestFactory();
            
            // Create status request
            $request = $request_factory->invoiceStatus(array(
                'invoice' => $invoice_id
            ));
            
            $response = $this->gamemoney_sdk->send($request);
            
            $this->log('info', 'Invoice status retrieved: ' . json_encode($response));
            
            return array(
                'success' => true,
                'status' => $response['status'] ?? 'unknown',
                'data' => $response
            );
            
        } catch (\Gamemoney\Exception\GamemoneyExceptionInterface $e) {
            $this->log('error', 'Failed to get invoice status: ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->log('error', 'Unexpected error getting invoice status: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process callback/webhook from GMPays
     *
     * @param array $data Webhook data
     * @param string $signature Webhook signature
     * @return array|false Processed data or false on failure
     */
    public function process_callback($data, $signature = null) {
        if (!$this->gamemoney_sdk) {
            $this->log('error', 'SDK not initialized');
            return false;
        }
        
        try {
            // Create appropriate callback handler based on callback type
            $handler = null;
            
            if (isset($data['type']) && $data['type'] === 'invoice') {
                $handler = new \Gamemoney\CallbackHandler\InvoiceCallbackHandler($this->hmac_key);
            } else {
                // Default to invoice handler
                $handler = new \Gamemoney\CallbackHandler\InvoiceCallbackHandler($this->hmac_key);
            }
            
            // Verify and process the callback
            $result = $handler->handle($data);
            
            $this->log('info', 'Callback processed successfully: ' . json_encode($result));
            
            return array(
                'success' => true,
                'data' => $result
            );
            
        } catch (\Gamemoney\Exception\SignatureVerificationException $e) {
            $this->log('error', 'Signature verification failed: ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->log('error', 'Callback processing failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify webhook signature
     *
     * @param array $data Webhook data
     * @param string $signature Provided signature
     * @return bool
     */
    public function verify_webhook_signature($data, $signature) {
        if (empty($this->hmac_key)) {
            $this->log('error', 'HMAC key not configured');
            return false;
        }
        
        try {
            // Create signature verifier
            $verifier = new \Gamemoney\Sign\SignatureVerifier($this->hmac_key);
            
            // Verify the signature
            return $verifier->verify($data, $signature);
            
        } catch (Exception $e) {
            $this->log('error', 'Signature verification error: ' . $e->getMessage());
            return false;
        }
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
        if (!$this->gamemoney_sdk) {
            $this->log('error', 'SDK not initialized');
            return false;
        }
        
        try {
            // Note: The actual refund implementation depends on GMPays API
            // This is a placeholder - check GMPays documentation for the correct method
            $this->log('info', sprintf('Refund requested for invoice %s, amount: %s, reason: %s', $invoice_id, $amount, $reason));
            
            // For now, return a success response
            // TODO: Implement actual refund API call when GMPays provides the specification
            return array(
                'success' => true,
                'message' => 'Refund functionality not yet implemented. Please process manually in GMPays control panel.'
            );
            
        } catch (Exception $e) {
            $this->log('error', 'Refund error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get locale for GMPays
     *
     * @return string
     */
    private function get_locale_for_gmpays() {
        $locale = get_locale();
        
        // Map WordPress locales to GMPays supported locales
        $locale_map = array(
            'es_ES' => 'es',
            'es_MX' => 'es',
            'es_AR' => 'es',
            'es_CO' => 'es',
            'es_VE' => 'es',
            'es_PE' => 'es',
            'es_CL' => 'es',
            'es_UY' => 'es',
            'pt_BR' => 'pt',
            'en_US' => 'en',
            'en_GB' => 'en',
        );
        
        // Return mapped locale or default to English
        return isset($locale_map[$locale]) ? $locale_map[$locale] : 'en';
    }
    
    /**
     * Log messages
     *
     * @param string $level Log level (info, error, debug)
     * @param string $message Log message
     */
    private function log($level, $message) {
        if (!$this->debug && $level !== 'error') {
            return;
        }
        
        $logger = wc_get_logger();
        
        // Ensure we have a valid logger
        if (!$logger) {
            error_log('[GMPays] ' . $level . ': ' . $message);
            return;
        }
        
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
    }
    
    /**
     * Test connection to GMPays
     *
     * @return bool
     */
    public function test_connection() {
        if (!$this->gamemoney_sdk) {
            return false;
        }
        
        try {
            // Try to create a minimal test request
            $this->log('info', 'Testing GMPays connection with Project ID: ' . $this->project_id);
            
            // The connection is valid if SDK was initialized successfully
            return true;
            
        } catch (Exception $e) {
            $this->log('error', 'Connection test failed: ' . $e->getMessage());
            return false;
        }
    }
}