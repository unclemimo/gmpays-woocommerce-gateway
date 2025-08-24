<?php
/**
 * GMPays Webhook Handler Class - Clean Implementation
 *
 * Handles webhook notifications from GMPays payment processor using proper API structure
 *
 * @package GMPaysWooCommerceGateway
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GMPays_Webhook_Handler Class
 */
class GMPays_Webhook_Handler {
    
    /** @var GMPays_API_Client */
    private $api_client;
    
    /** @var GMPays_Currency_Manager */
    private $currency_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new GMPays_API_Client();
        $this->currency_manager = new GMPays_Currency_Manager();
        
        // Register webhook endpoint
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
        
        // Register legacy webhook endpoint for backward compatibility
        add_action('init', array($this, 'register_legacy_webhook_endpoint'));
        
        // Register legacy return URL handlers
        add_action('init', array($this, 'handle_legacy_return_urls'));
        
        if (defined('GMPAYS_DEBUG') && GMPAYS_DEBUG) {
            error_log('GMPays: Webhook handler initialized');
        }
    }
    
    /**
     * Register REST API webhook endpoint
     */
    public function register_webhook_endpoint() {
        register_rest_route('gmpays/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Register legacy webhook endpoint
     */
    public function register_legacy_webhook_endpoint() {
        add_action('wp_loaded', array($this, 'process_webhook'));
    }
    
    /**
     * Handle webhook notification from GMPays
     */
    public function handle_webhook($request) {
        $logger = wc_get_logger();
        $logger->info('GMPays webhook received', array('source' => 'gmpays-gateway'));
        
        // Get webhook data
        $webhook_data = $request->get_json_params();
        
        if (empty($webhook_data)) {
            $logger->error('GMPays webhook: Empty data received', array('source' => 'gmpays-gateway'));
            return new WP_Error('empty_data', 'Empty webhook data', array('status' => 400));
        }
        
        $logger->info('GMPays webhook data: ' . json_encode($webhook_data), array('source' => 'gmpays-gateway'));
        
        // Process webhook data
        $result = $this->process_webhook_data($webhook_data);
        
        if (is_wp_error($result)) {
            $logger->error('GMPays webhook error: ' . $result->get_error_message(), array('source' => 'gmpays-gateway'));
            return $result;
        }
        
        $logger->info('GMPays webhook processed successfully', array('source' => 'gmpays-gateway'));
        
        return new WP_REST_Response(array('status' => 'success'), 200);
    }
    
    /**
     * Process webhook data
     */
    private function process_webhook_data($webhook_data) {
        // Extract order ID from webhook data
        $order_id = $this->extract_order_id_from_webhook($webhook_data);
        
        if (!$order_id) {
            return new WP_Error('no_order_id', 'No order ID found in webhook data');
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found: ' . $order_id);
        }
        
        // Check if order is already processed
        if ($order->is_paid() || in_array($order->get_status(), array('processing', 'completed', 'on-hold'))) {
            return true; // Already processed
        }
        
        // Extract payment status
        $payment_status = $this->extract_payment_status_from_webhook($webhook_data);
        
        if (!$payment_status) {
            return new WP_Error('no_payment_status', 'No payment status found in webhook data');
        }
        
        // Process payment status
        $this->process_payment_status($order, $payment_status, $webhook_data);
        
        return true;
    }
    
    /**
     * Extract order ID from webhook data
     */
    private function extract_order_id_from_webhook($webhook_data) {
        // Try different possible fields for order ID
        $possible_fields = array('order_id', 'orderId', 'order', 'id', 'invoice_id', 'invoiceId');
        
        foreach ($possible_fields as $field) {
            if (isset($webhook_data[$field])) {
                return $webhook_data[$field];
            }
        }
        
        return null;
    }
    
    /**
     * Extract payment status from webhook data
     */
    private function extract_payment_status_from_webhook($webhook_data) {
        // Try different possible fields for payment status
        $possible_fields = array('status', 'payment_status', 'state', 'paymentState', 'result');
        
        foreach ($possible_fields as $field) {
            if (isset($webhook_data[$field])) {
                return $webhook_data[$field];
            }
        }
        
        return null;
    }
    
    /**
     * Process payment status
     */
    private function process_payment_status($order, $payment_status, $webhook_data) {
        $logger = wc_get_logger();
        $order_id = $order->get_id();
        
        $logger->info('Processing payment status for order ' . $order_id . ': ' . $payment_status, array('source' => 'gmpays-gateway'));
        
        switch (strtolower($payment_status)) {
            case 'paid':
            case 'success':
            case 'completed':
                $this->process_successful_payment($order, $webhook_data);
                break;
                
            case 'failed':
            case 'refused':
            case 'error':
                $this->process_failed_payment($order, $webhook_data);
                break;
                
            case 'cancelled':
            case 'canceled':
                $this->process_cancelled_payment($order, $webhook_data);
                break;
                
            default:
                $logger->warning('Unknown payment status: ' . $payment_status, array('source' => 'gmpays-gateway'));
                break;
        }
    }
    
    /**
     * Process successful payment
     */
    private function process_successful_payment($order, $webhook_data) {
        $order_id = $order->get_id();
        $logger = wc_get_logger();
        
        $logger->info('Processing successful payment for order ' . $order_id, array('source' => 'gmpays-gateway'));
        
        // Get transaction details
        $transaction_id = $webhook_data['transaction_id'] ?? $webhook_data['invoice_id'] ?? $order_id;
        $amount = $webhook_data['amount'] ?? $order->get_total();
        $currency = $webhook_data['currency'] ?? 'USD';
        
        // Complete payment
        $order->payment_complete($transaction_id);
        $order->update_status('on-hold', __('Payment received via GMPays - Order placed on hold for confirmation', 'gmpays-woocommerce-gateway'));
        
        // Add order notes
        $public_note = sprintf(
            __('Payment completed successfully via GMPays.\nTransaction ID: %s\nAmount: %s %s\nPayment Method: Credit Card', 'gmpays-woocommerce-gateway'),
            $transaction_id,
            $amount,
            strtoupper($currency)
        );
        $order->add_order_note($public_note, false, false);
        
        $private_note = sprintf(
            __('GMPays Payment Success - Order #%s payment completed successfully via GMPays. Transaction ID: %s. Amount: %s %s.', 'gmpays-woocommerce-gateway'),
            $order_id,
            $transaction_id,
            $amount,
            strtoupper($currency)
        );
        $order->add_order_note($private_note, false, true);
        
        // Update metadata
        $order->update_meta_data('_gmpays_payment_status', 'success');
        $order->update_meta_data('_gmpays_transaction_id', $transaction_id);
        $order->update_meta_data('_gmpays_payment_completed_at', current_time('mysql'));
        
        $order->save();
        
        $logger->info('Order ' . $order_id . ' marked as successful', array('source' => 'gmpays-gateway'));
    }
    
    /**
     * Process failed payment
     */
    private function process_failed_payment($order, $webhook_data) {
        $order_id = $order->get_id();
        $logger = wc_get_logger();
        
        $logger->info('Processing failed payment for order ' . $order_id, array('source' => 'gmpays-gateway'));
        
        // Get failure details
        $reason = $webhook_data['reason'] ?? $webhook_data['comment'] ?? __('Payment was refused by payment processor', 'gmpays-woocommerce-gateway');
        
        // Update order status
        $order->update_status('failed', sprintf(__('Payment failed via GMPays: %s', 'gmpays-woocommerce-gateway'), $reason));
        
        // Add order notes
        $public_note = sprintf(
            __('Payment failed via GMPays.\nReason: %s\nPlease try again or contact support.', 'gmpays-woocommerce-gateway'),
            $reason
        );
        $order->add_order_note($public_note, false, false);
        
        $private_note = sprintf(
            __('GMPays Payment Failed - Order #%s payment failed via GMPays. Reason: %s', 'gmpays-woocommerce-gateway'),
            $order_id,
            $reason
        );
        $order->add_order_note($private_note, false, true);
        
        // Update metadata
        $order->update_meta_data('_gmpays_payment_status', 'failed');
        $order->update_meta_data('_gmpays_payment_failed_at', current_time('mysql'));
        $order->update_meta_data('_gmpays_payment_failure_reason', $reason);
        
        $order->save();
        
        $logger->info('Order ' . $order_id . ' marked as failed', array('source' => 'gmpays-gateway'));
    }
    
    /**
     * Process cancelled payment
     */
    private function process_cancelled_payment($order, $webhook_data) {
        $order_id = $order->get_id();
        $logger = wc_get_logger();
        
        $logger->info('Processing cancelled payment for order ' . $order_id, array('source' => 'gmpays-gateway'));
        
        // Update order status
        $order->update_status('cancelled', __('Payment cancelled via GMPays', 'gmpays-woocommerce-gateway'));
        
        // Add order notes
        $public_note = __('Payment was cancelled via GMPays. You can try again or choose a different payment method.', 'gmpays-woocommerce-gateway');
        $order->add_order_note($public_note, false, false);
        
        $private_note = sprintf(
            __('GMPays Payment Cancelled - Order #%s payment was cancelled via GMPays. Customer did not complete payment.', 'gmpays-woocommerce-gateway'),
            $order_id
        );
        $order->add_order_note($private_note, false, true);
        
        // Update metadata
        $order->update_meta_data('_gmpays_payment_status', 'cancelled');
        $order->update_meta_data('_gmpays_payment_cancelled_at', current_time('mysql'));
        
        $order->save();
        
        $logger->info('Order ' . $order_id . ' marked as cancelled', array('source' => 'gmpays-gateway'));
    }
    
    /**
     * Legacy webhook processing for backward compatibility
     */
    public function process_webhook() {
        // Check if this is a GMPays webhook
        if (!isset($_GET['gmpays_webhook']) && !isset($_POST['gmpays_webhook'])) {
            return;
        }
        
        $logger = wc_get_logger();
        $logger->info('GMPays legacy webhook received', array('source' => 'gmpays-gateway'));
        
        // Get webhook data from POST or GET
        $webhook_data = !empty($_POST) ? $_POST : $_GET;
        
        $logger->info('GMPays legacy webhook data: ' . json_encode($webhook_data), array('source' => 'gmpays-gateway'));
        
        // Process webhook data
        $this->process_webhook_data($webhook_data);
    }
    
    /**
     * Handle legacy return URLs for backward compatibility
     */
    public function handle_legacy_return_urls() {
        // Handle success return
        if (isset($_GET['gmpays_success']) && isset($_GET['order_id'])) {
            $this->handle_success_return($_GET['order_id']);
        }
        
        // Handle failure return
        if (isset($_GET['gmpays_failure']) && isset($_GET['order_id'])) {
            $this->handle_failure_return($_GET['order_id']);
        }
        
        // Handle cancelled return
        if (isset($_GET['gmpays_cancelled']) && isset($_GET['order_id'])) {
            $this->handle_cancelled_return($_GET['order_id']);
        }
    }
    
    /**
     * Handle success return
     */
    private function handle_success_return($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Redirect to thank you page
        wp_redirect($this->get_return_url($order));
        exit;
    }
    
    /**
     * Handle failure return
     */
    private function handle_failure_return($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Redirect to checkout with error
        wc_add_notice(__('Payment failed. Please try again.', 'gmpays-woocommerce-gateway'), 'error');
        wp_redirect(wc_get_checkout_url());
        exit;
    }
    
    /**
     * Handle cancelled return
     */
    private function handle_cancelled_return($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Redirect to checkout with notice
        wc_add_notice(__('Payment was cancelled.', 'gmpays-woocommerce-gateway'), 'notice');
        wp_redirect(wc_get_checkout_url());
        exit;
    }
    
    /**
     * Get return URL for order
     */
    private function get_return_url($order) {
        return $order->get_checkout_order_received_url();
    }
}
