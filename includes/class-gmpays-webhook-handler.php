<?php
/**
 * GMPays Webhook Handler Class
 *
 * Handles webhook notifications from GMPays payment processor
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
    
    /**
     * Handle webhook request
     *
     * @param WP_REST_Request $request Webhook request
     * @return WP_REST_Response Response
     */
    public static function handle_webhook($request) {
        $body = $request->get_body();
        $headers = $request->get_headers();
        
        // Get gateway settings for debug mode and API credentials
        $gateway_settings = get_option('woocommerce_gmpays_credit_card_settings', array());
        $debug_mode = isset($gateway_settings['debug']) && $gateway_settings['debug'] === 'yes';
        $testmode = isset($gateway_settings['testmode']) && $gateway_settings['testmode'] === 'yes';
        
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
            
            // Verify webhook signature
            if (!self::verify_webhook_signature($webhook_data, $gateway_settings, $testmode)) {
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
     * Verify webhook signature
     *
     * @param array $webhook_data Webhook data
     * @param array $gateway_settings Gateway settings
     * @param boolean $testmode Test mode flag
     * @return boolean True if signature is valid
     */
    private static function verify_webhook_signature($webhook_data, $gateway_settings, $testmode) {
        // Get HMAC key based on mode
        $hmac_key = $testmode ? 
            (isset($gateway_settings['test_hmac_key']) ? $gateway_settings['test_hmac_key'] : '') :
            (isset($gateway_settings['live_hmac_key']) ? $gateway_settings['live_hmac_key'] : '');
        
        if (empty($hmac_key)) {
            wc_get_logger()->warning('GMPays HMAC key not configured for webhook verification', array('source' => 'gmpays-webhook'));
            return false;
        }
        
        // Check if signature exists in webhook data
        if (!isset($webhook_data['signature'])) {
            wc_get_logger()->warning('GMPays webhook signature missing', array('source' => 'gmpays-webhook'));
            return false;
        }
        
        $received_signature = $webhook_data['signature'];
        
        // Remove signature from data for verification
        unset($webhook_data['signature']);
        
        // Sort data by keys
        ksort($webhook_data);
        
        // Build signature string according to GMPays documentation
        $signature_string = '';
        foreach ($webhook_data as $key => $value) {
            if (!is_array($value) && !is_object($value)) {
                $signature_string .= $value . ':';
            }
        }
        
        // Add HMAC key at the end
        $signature_string .= $hmac_key;
        
        // Generate MD5 hash (GMPays uses MD5 for signatures)
        $expected_signature = md5($signature_string);
        
        // Compare signatures
        return hash_equals($expected_signature, $received_signature);
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
        
        // Complete payment
        $order->payment_complete($transaction_id);
        
        // Add detailed order note
        $note = sprintf(
            __('Payment completed successfully via GMPays.\nTransaction ID: %s\nAmount: %s %s\nPayment Method: Credit Card', 'gmpays-woocommerce-gateway'),
            $transaction_id,
            isset($data['amount']) ? $data['amount'] : $order->get_total(),
            isset($data['currency']) ? strtoupper($data['currency']) : 'USD'
        );
        $order->add_order_note($note, false, true);
        
        // Update payment metadata
        $order->update_meta_data('_gmpays_transaction_id', $transaction_id);
        $order->update_meta_data('_gmpays_payment_status', 'completed');
        $order->update_meta_data('_gmpays_payment_completed_at', current_time('mysql'));
        
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
        
        // Add detailed failure note
        $note = sprintf(
            __('Payment failed via GMPays.\nInvoice ID: %s\nReason: %s\nPlease contact customer to retry payment.', 'gmpays-woocommerce-gateway'),
            isset($data['invoice']) ? $data['invoice'] : 'N/A',
            isset($data['reason']) ? $data['reason'] : __('Payment processing error', 'gmpays-woocommerce-gateway')
        );
        $order->add_order_note($note, false, true);
        
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
        
        // Add cancellation note
        $note = sprintf(
            __('Payment cancelled via GMPays.\nInvoice ID: %s\nCustomer did not complete payment.', 'gmpays-woocommerce-gateway'),
            isset($data['invoice']) ? $data['invoice'] : 'N/A'
        );
        $order->add_order_note($note, false, true);
        
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
        
        // Add refund note
        $note = sprintf(
            __('Refund processed via GMPays.\nRefund ID: %s\nAmount: %s %s\nReason: %s', 'gmpays-woocommerce-gateway'),
            isset($data['refund_id']) ? $data['refund_id'] : 'N/A',
            isset($data['amount']) ? $data['amount'] : 'N/A',
            isset($data['currency']) ? strtoupper($data['currency']) : 'USD',
            isset($data['reason']) ? $data['reason'] : __('No reason provided', 'gmpays-woocommerce-gateway')
        );
        $order->add_order_note($note, false, true);
        
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
