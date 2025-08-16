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
        
        // Get debug setting from gateway options
        $gateway_settings = get_option('woocommerce_gmpays_credit_card_settings', array());
        $this->debug = isset($gateway_settings['debug']) && $gateway_settings['debug'] === 'yes';
        
        $this->init_gamemoney_sdk();
    }
    
    /**
     * Initialize GameMoney SDK
     */
    private function init_gamemoney_sdk() {
        if (empty($this->project_id) || empty($this->hmac_key)) {
            $this->log('error', 'Missing required credentials: Project ID or HMAC Key');
            return;
        }
        
        try {
            // The Gamemoney SDK uses 'project' for Project ID and 'hmac' for HMAC Key
            // No private key needed for basic operations (credit card processing)
            $config = new \Gamemoney\Config($this->project_id, $this->hmac_key);
            $this->gamemoney_sdk = new \Gamemoney\Gateway($config);
            $this->log('info', 'GMPays SDK initialized successfully');
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
            $this->log('error', 'SDK not initialized');
            return false;
        }
        
        try {
            $request_factory = new \Gamemoney\Request\RequestFactory();
            
            // Prepare invoice data for GMPays
            $invoice_data = array(
                'amount' => $order_data['amount'], // Amount in USD
                'currency' => 'USD', // GMPays processes in USD
                'order' => $order_data['order_id'],
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
            
            // Add card-specific parameters if needed
            if (!empty($order_data['payment_method']) && $order_data['payment_method'] === 'card') {
                $invoice_data['payment_system'] = 'card';
            }
            
            $this->log('info', 'Creating invoice with data: ' . json_encode($invoice_data));
            
            // Create the invoice request
            $request = $request_factory->invoiceCreate($invoice_data);
            
            // Send the request
            $response = $this->gamemoney_sdk->send($request);
            
            $this->log('info', 'Invoice created successfully: ' . json_encode($response));
            
            return array(
                'success' => true,
                'invoice_id' => $response['id'] ?? null,
                'payment_url' => $response['url'] ?? null,
                'data' => $response
            );
            
        } catch (\Gamemoney\Exception\RequestValidationException $e) {
            $this->log('error', 'Request validation failed: ' . $e->getMessage());
            $this->log('error', 'Validation errors: ' . json_encode($e->getErrors()));
            return false;
        } catch (\Gamemoney\Exception\GamemoneyExceptionInterface $e) {
            $this->log('error', 'GMPays API error: ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->log('error', 'Unexpected error: ' . $e->getMessage());
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
        if (!$this->gamemoney_sdk) {
            $this->log('error', 'SDK not initialized');
            return false;
        }
        
        try {
            $request_factory = new \Gamemoney\Request\RequestFactory();
            $request = $request_factory->getInvoiceStatus($invoice_id);
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
    public function verify_signature($data, $signature) {
        try {
            $verifier = new \Gamemoney\Sign\SignatureVerifier($this->hmac_key);
            return $verifier->verify($data, $signature);
        } catch (Exception $e) {
            $this->log('error', 'Signature verification error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get appropriate locale for GMPays
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
            'en_US' => 'en',
            'en_GB' => 'en',
            'pt_BR' => 'pt',
            'ru_RU' => 'ru',
        );
        
        // Return mapped locale or default to Spanish for Latin America
        return isset($locale_map[$locale]) ? $locale_map[$locale] : 'es';
    }
    
    /**
     * Process refund
     *
     * @param string $transaction_id Original transaction ID
     * @param float $amount Amount to refund
     * @param string $reason Refund reason
     * @return array|false Response data or false on failure
     */
    public function process_refund($transaction_id, $amount, $reason = '') {
        if (!$this->gamemoney_sdk) {
            $this->log('error', 'SDK not initialized');
            return false;
        }
        
        try {
            // Note: GMPays refund implementation would go here
            // The exact method depends on GMPays API documentation
            // This is a placeholder for when refund functionality is available
            
            $this->log('warning', 'Refund functionality not yet implemented in GMPays SDK');
            
            return array(
                'success' => false,
                'message' => __('Refunds are not yet supported by GMPays gateway', 'gmpays-woocommerce-gateway')
            );
            
        } catch (Exception $e) {
            $this->log('error', 'Refund failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log messages
     *
     * @param string $level Log level (info, error, warning)
     * @param string $message Message to log
     */
    private function log($level, $message) {
        if (!$this->debug && $level === 'info') {
            return;
        }
        
        $logger = wc_get_logger();
        $context = array('source' => 'gmpays');
        
        switch ($level) {
            case 'error':
                $logger->error($message, $context);
                break;
            case 'warning':
                $logger->warning($message, $context);
                break;
            case 'info':
            default:
                $logger->info($message, $context);
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
            // Try to get a non-existent invoice status
            // This should fail with a specific error if credentials are wrong
            $request_factory = new \Gamemoney\Request\RequestFactory();
            $request = $request_factory->getInvoiceStatus('test_' . time());
            $this->gamemoney_sdk->send($request);
            
            // If we get here, credentials are likely valid
            return true;
            
        } catch (\Gamemoney\Exception\RequestValidationException $e) {
            // This is expected for a non-existent invoice
            return true;
        } catch (\Gamemoney\Exception\GamemoneyExceptionInterface $e) {
            // Check if it's an authentication error
            if (strpos($e->getMessage(), 'auth') !== false || strpos($e->getMessage(), '401') !== false) {
                return false;
            }
            // Other errors might still mean connection is OK
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}