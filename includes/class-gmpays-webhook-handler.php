<?php
/**
 * GMPays Webhook Handler Class - Updated for RSA Authentication
 *
 * Handles webhook notifications from GMPays payment processor using RSA signatures
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
    
    /** @var string GMPays certificate for signature verification */
    private static $gmpays_certificate = '-----BEGIN CERTIFICATE-----
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
    
    /**
     * Handle webhook request
     *
     * @param WP_REST_Request $request Webhook request
     * @return WP_REST_Response Response
     */
    public static function handle_webhook($request) {
        $body = $request->get_body();
        $headers = $request->get_headers();
        
        // Get gateway settings for debug mode
        $gateway_settings = get_option('woocommerce_gmpays_credit_card_settings', array());
        $debug_mode = isset($gateway_settings['debug']) && $gateway_settings['debug'] === 'yes';
        
        if ($debug_mode) {
            wc_get_logger()->debug('GMPays webhook received - Headers: ' . print_r($headers, true), array('source' => 'gmpays-webhook'));
            wc_get_logger()->debug('GMPays webhook received - Body: ' . $body, array('source' => 'gmpays-webhook'));
        } else {
            wc_get_logger()->info('GMPays webhook received', array('source' => 'gmpays-webhook'));
        }
        
        try {
            // Parse webhook data
            $webhook_data = json_decode($body, true);
            
            if (!$webhook_data) {
                wc_get_logger()->error('GMPays webhook data is invalid JSON', array('source' => 'gmpays-webhook'));
                return new WP_REST_Response(array('error' => 'Invalid JSON'), 400);
            }
            
            if ($debug_mode) {
                wc_get_logger()->debug('GMPays webhook data parsed: ' . print_r($webhook_data, true), array('source' => 'gmpays-webhook'));
            }
            
            // Verify webhook signature using RSA
            if (!self::verify_webhook_signature($webhook_data)) {
                wc_get_logger()->error('GMPays webhook signature verification failed', array('source' => 'gmpays-webhook'));
                return new WP_REST_Response(array('error' => 'Invalid signature'), 401);
            }
            
            // Process webhook event
            $result = self::process_webhook_event($webhook_data);
            
            if ($result) {
                if ($debug_mode) {
                    wc_get_logger()->debug('GMPays webhook processed successfully', array('source' => 'gmpays-webhook'));
                }
                return new WP_REST_Response(array('status' => 'success'), 200);
            } else {
                wc_get_logger()->error('GMPays webhook processing failed', array('source' => 'gmpays-webhook'));
                return new WP_REST_Response(array('error' => 'Processing failed'), 500);
            }
            
        } catch (Exception $e) {
            wc_get_logger()->error('GMPays webhook processing error: ' . $e->getMessage(), array('source' => 'gmpays-webhook'));
            return new WP_REST_Response(array('error' => 'Internal error'), 500);
        }
    }
    
    /**
     * Verify webhook signature using RSA
     *
     * @param array $webhook_data Webhook data
     * @return boolean True if signature is valid
     */
    private static function verify_webhook_signature($webhook_data) {
        // Check if signature exists in webhook data
        if (!isset($webhook_data['signature'])) {
            wc_get_logger()->warning('GMPays webhook signature missing', array('source' => 'gmpays-webhook'));
            return false;
        }
        
        $received_signature = $webhook_data['signature'];
        
        // Create a copy of data without signature for verification
        $verify_data = $webhook_data;
        unset($verify_data['signature']);
        
        // Convert data to string according to GMPays specification
        $string_to_verify = self::array_to_string($verify_data);
        
        wc_get_logger()->debug('String to verify: ' . $string_to_verify, array('source' => 'gmpays-webhook'));
        
        // Get public key from GMPays certificate
        $public_key = openssl_pkey_get_public(self::$gmpays_certificate);
        if (!$public_key) {
            wc_get_logger()->error('Failed to extract public key from certificate', array('source' => 'gmpays-webhook'));
            return false;
        }
        
        // Decode the signature from base64
        $signature_binary = base64_decode($received_signature);
        
        // Verify signature using SHA256
        $result = openssl_verify($string_to_verify, $signature_binary, $public_key, OPENSSL_ALGO_SHA256);
        
        if ($result === 1) {
            wc_get_logger()->info('Webhook signature verified successfully', array('source' => 'gmpays-webhook'));
            return true;
        } elseif ($result === 0) {
            wc_get_logger()->error('Webhook signature verification failed', array('source' => 'gmpays-webhook'));
            return false;
        } else {
            wc_get_logger()->error('Error during signature verification: ' . openssl_error_string(), array('source' => 'gmpays-webhook'));
            return false;
        }
    }
    
    /**
     * Convert array to string for signature verification according to GMPays specification
     *
     * @param array $data Data to convert
     * @return string Formatted string for signature
     */
    private static function array_to_string($data) {
        // Sort by keys alphabetically
        ksort($data);
        
        $result = '';
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // For arrays, process recursively
                if (self::is_assoc($value)) {
                    // Associative array
                    $result .= $key . ':' . self::array_to_string_recursive($value) . ';';
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
    private static function array_to_string_recursive($data) {
        ksort($data);
        $result = '';
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result .= $key . ':' . self::array_to_string_recursive($value) . ';';
            } else {
                $result .= $key . ':' . $value . ';';
            }
        }
        
        return $result;
    }
    
    /**
     * Check if array is associative
     */
    private static function is_assoc($arr) {
        if (!is_array($arr)) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
    
    /**
     * Process webhook event
     *
     * @param array $webhook_data Webhook data
     * @return boolean True if processed successfully
     */
    private static function process_webhook_event($webhook_data) {
        // Check for required fields
        if (!isset($webhook_data['type']) || !isset($webhook_data['data'])) {
            wc_get_logger()->error('GMPays webhook missing required fields', array('source' => 'gmpays-webhook'));
            return false;
        }
        
        $event_type = $webhook_data['type'];
        $event_data = $webhook_data['data'];
        
        wc_get_logger()->info('Processing GMPays webhook event: ' . $event_type, array('source' => 'gmpays-webhook'));
        
        // Handle different event types
        switch ($event_type) {
            case 'payment':
            case 'invoice.paid':
                return self::handle_payment_success($event_data);
                
            case 'payment.failed':
            case 'invoice.failed':
                return self::handle_payment_failed($event_data);
                
            case 'payment.cancelled':
            case 'invoice.cancelled':
                return self::handle_payment_cancelled($event_data);
                
            case 'refund':
            case 'invoice.refunded':
                return self::handle_refund($event_data);
                
            default:
                wc_get_logger()->info('Unhandled GMPays webhook event type: ' . $event_type, array('source' => 'gmpays-webhook'));
                return true; // Return true to acknowledge receipt
        }
    }
    
    /**
     * Handle successful payment
     *
     * @param array $data Event data
     * @return boolean
     */
    private static function handle_payment_success($data) {
        $order = self::get_order_from_webhook_data($data);
        
        if (!$order) {
            wc_get_logger()->warning('Order not found for GMPays payment success webhook', array('source' => 'gmpays-webhook'));
            return false;
        }
        
        // Check if order is already paid
        if ($order->is_paid()) {
            wc_get_logger()->info('Order already paid, skipping GMPays webhook processing', array('source' => 'gmpays-webhook'));
            return true;
        }
        
        // Get invoice/transaction ID
        $transaction_id = isset($data['invoice']) ? $data['invoice'] : 
                         (isset($data['id']) ? $data['id'] : 
                         (isset($data['transaction_id']) ? $data['transaction_id'] : ''));
        
        // Complete payment and set order to on-hold (pending confirmation)
        $order->payment_complete($transaction_id);
        $order->update_status('on-hold', __('Payment received via GMPays - Order placed on hold for confirmation', 'gmpays-woocommerce-gateway'));
        
        // Add detailed order note (public)
        $note = sprintf(
            __('Payment completed successfully via GMPays.\nTransaction ID: %s\nAmount: %s %s\nPayment Method: Credit Card', 'gmpays-woocommerce-gateway'),
            $transaction_id,
            isset($data['amount']) ? $data['amount'] : $order->get_total(),
            isset($data['currency']) ? strtoupper($data['currency']) : 'USD'
        );
        $order->add_order_note($note, false, false);
        
        // Add private note with transaction details
        $private_note = sprintf(
            __('GMPays Payment Success - Order #%s has been paid via GMPays. Transaction ID: %s. Order placed on hold for manual review.', 'gmpays-woocommerce-gateway'),
            $order->get_order_number(),
            $transaction_id
        );
        $order->add_order_note($private_note, false, true);
        
        // Update payment metadata
        $order->update_meta_data('_gmpays_transaction_id', $transaction_id);
        $order->update_meta_data('_gmpays_payment_status', 'completed');
        $order->update_meta_data('_gmpays_payment_completed_at', current_time('mysql'));
        
        // Set WooCommerce native transaction ID field
        $order->set_transaction_id($transaction_id);
        
        if (isset($data['payment_method'])) {
            $order->update_meta_data('_gmpays_payment_method_used', $data['payment_method']);
        }
        
        if (isset($data['card_last4'])) {
            $order->update_meta_data('_gmpays_card_last4', $data['card_last4']);
        }
        
        if (isset($data['card_brand'])) {
            $order->update_meta_data('_gmpays_card_brand', $data['card_brand']);
        }
        
        $order->save();
        
        wc_get_logger()->info('GMPays payment success processed for order #' . $order->get_id(), array('source' => 'gmpays-webhook'));
        
        return true;
    }
    
    /**
     * Handle failed payment
     *
     * @param array $data Event data
     * @return boolean
     */
    private static function handle_payment_failed($data) {
        $order = self::get_order_from_webhook_data($data);
        
        if (!$order) {
            wc_get_logger()->warning('Order not found for GMPays payment failed webhook', array('source' => 'gmpays-webhook'));
            return false;
        }
        
        // Update order status to failed
        $order->update_status('failed', __('Payment failed via GMPays', 'gmpays-woocommerce-gateway'));
        
        // Add detailed failure note (public)
        $note = sprintf(
            __('Payment failed via GMPays.\nInvoice ID: %s\nReason: %s\nPlease contact customer to retry payment.', 'gmpays-woocommerce-gateway'),
            isset($data['invoice']) ? $data['invoice'] : 'N/A',
            isset($data['reason']) ? $data['reason'] : __('Payment processing error', 'gmpays-woocommerce-gateway')
        );
        $order->add_order_note($note, false, false);
        
        // Add private note with failure details
        $private_note = sprintf(
            __('GMPays Payment Failure - Order #%s payment failed via GMPays. Invoice ID: %s. Reason: %s', 'gmpays-woocommerce-gateway'),
            $order->get_order_number(),
            isset($data['invoice']) ? $data['invoice'] : 'N/A',
            isset($data['reason']) ? $data['reason'] : __('Payment processing error', 'gmpays-woocommerce-gateway')
        );
        $order->add_order_note($private_note, false, true);
        
        // Update payment metadata
        $order->update_meta_data('_gmpays_payment_status', 'failed');
        $order->update_meta_data('_gmpays_payment_failed_at', current_time('mysql'));
        
        if (isset($data['reason'])) {
            $order->update_meta_data('_gmpays_payment_failure_reason', $data['reason']);
        }
        
        $order->save();
        
        wc_get_logger()->info('GMPays payment failure processed for order #' . $order->get_id(), array('source' => 'gmpays-webhook'));
        
        return true;
    }
    
    /**
     * Handle cancelled payment
     *
     * @param array $data Event data
     * @return boolean
     */
    private static function handle_payment_cancelled($data) {
        $order = self::get_order_from_webhook_data($data);
        
        if (!$order) {
            wc_get_logger()->warning('Order not found for GMPays payment cancelled webhook', array('source' => 'gmpays-webhook'));
            return false;
        }
        
        // Update order status to cancelled
        $order->update_status('cancelled', __('Payment cancelled via GMPays', 'gmpays-woocommerce-gateway'));
        
        // Add cancellation note (public)
        $note = sprintf(
            __('Payment cancelled via GMPays.\nInvoice ID: %s\nCustomer did not complete payment.', 'gmpays-woocommerce-gateway'),
            isset($data['invoice']) ? $data['invoice'] : 'N/A'
        );
        $order->add_order_note($note, false, false);
        
        // Add private note with cancellation details
        $private_note = sprintf(
            __('GMPays Payment Cancellation - Order #%s payment was cancelled via GMPays. Invoice ID: %s. Customer did not complete payment.', 'gmpays-woocommerce-gateway'),
            $order->get_order_number(),
            isset($data['invoice']) ? $data['invoice'] : 'N/A'
        );
        $order->add_order_note($private_note, false, true);
        
        // Update payment metadata
        $order->update_meta_data('_gmpays_payment_status', 'cancelled');
        $order->update_meta_data('_gmpays_payment_cancelled_at', current_time('mysql'));
        
        $order->save();
        
        wc_get_logger()->info('GMPays payment cancellation processed for order #' . $order->get_id(), array('source' => 'gmpays-webhook'));
        
        return true;
    }
    
    /**
     * Handle refund notification
     *
     * @param array $data Event data
     * @return boolean
     */
    private static function handle_refund($data) {
        $order = self::get_order_from_webhook_data($data);
        
        if (!$order) {
            wc_get_logger()->warning('Order not found for GMPays refund webhook', array('source' => 'gmpays-webhook'));
            return false;
        }
        
        // Add refund note (public)
        $note = sprintf(
            __('Refund processed via GMPays.\nRefund ID: %s\nAmount: %s %s\nReason: %s', 'gmpays-woocommerce-gateway'),
            isset($data['refund_id']) ? $data['refund_id'] : 'N/A',
            isset($data['amount']) ? $data['amount'] : 'N/A',
            isset($data['currency']) ? strtoupper($data['currency']) : 'USD',
            isset($data['reason']) ? $data['reason'] : __('No reason provided', 'gmpays-woocommerce-gateway')
        );
        $order->add_order_note($note, false, false);
        
        // Add private note with refund details
        $private_note = sprintf(
            __('GMPays Refund Processed - Order #%s refund processed via GMPays. Refund ID: %s. Amount: %s %s. Reason: %s', 'gmpays-woocommerce-gateway'),
            $order->get_order_number(),
            isset($data['refund_id']) ? $data['refund_id'] : 'N/A',
            isset($data['amount']) ? $data['amount'] : 'N/A',
            isset($data['currency']) ? strtoupper($data['currency']) : 'USD',
            isset($data['reason']) ? $data['reason'] : __('No reason provided', 'gmpays-woocommerce-gateway')
        );
        $order->add_order_note($private_note, false, true);
        
        // Update refund metadata
        $order->update_meta_data('_gmpays_refund_id', isset($data['refund_id']) ? $data['refund_id'] : '');
        $order->update_meta_data('_gmpays_refund_processed_at', current_time('mysql'));
        
        $order->save();
        
        wc_get_logger()->info('GMPays refund processed for order #' . $order->get_id(), array('source' => 'gmpays-webhook'));
        
        return true;
    }
    
    /**
     * Get order from webhook data
     *
     * @param array $data Webhook data
     * @return WC_Order|false Order object or false if not found
     */
    private static function get_order_from_webhook_data($data) {
        // Try to find order by invoice ID first
        if (isset($data['invoice'])) {
            $orders = wc_get_orders(array(
                'meta_key' => '_gmpays_invoice_id',
                'meta_value' => $data['invoice'],
                'limit' => 1,
            ));
            
            if (!empty($orders)) {
                return $orders[0];
            }
        }
        
        // Try to find by order ID in metadata
        if (isset($data['metadata']['wc_order_id'])) {
            $order = wc_get_order($data['metadata']['wc_order_id']);
            if ($order) {
                return $order;
            }
        }
        
        // Try to find by order key
        if (isset($data['metadata']['wc_order_key'])) {
            $order_id = wc_get_order_id_by_order_key($data['metadata']['wc_order_key']);
            if ($order_id) {
                return wc_get_order($order_id);
            }
        }
        
        // Try to find by add_fields data (GMPays specific)
        if (isset($data['add_fields'])) {
            if (isset($data['add_fields']['order_id'])) {
                $order = wc_get_order($data['add_fields']['order_id']);
                if ($order) {
                    return $order;
                }
            }
            
            if (isset($data['add_fields']['order_key'])) {
                $order_id = wc_get_order_id_by_order_key($data['add_fields']['order_key']);
                if ($order_id) {
                    return wc_get_order($order_id);
                }
            }
        }
        
        return false;
    }
}
