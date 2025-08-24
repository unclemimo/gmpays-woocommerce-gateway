<?php
/**
 * GMPays Credit Card Payment Gateway Class - Clean Implementation
 *
 * Handles credit card payment processing through GMPays with proper API integration
 *
 * @package GMPaysWooCommerceGateway
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gateway_GMPays_Credit_Card Class
 */
class WC_Gateway_GMPays_Credit_Card extends WC_Payment_Gateway {
    
    /** @var GMPays_API_Client */
    private $api_client;
    
    /** @var GMPays_Currency_Manager */
    private $currency_manager;
    
    /** @var string */
    private $api_url;
    
    /** @var string */
    private $private_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'gmpays_credit_card';
        $this->icon               = apply_filters('woocommerce_gmpays_credit_card_icon', GMPAYS_WC_GATEWAY_PLUGIN_URL . 'assets/images/credit-cards.png');
        $this->has_fields         = false;
        $this->method_title       = __('GMPays Credit Card', 'gmpays-woocommerce-gateway');
        $this->method_description = __('Accept international credit card payments via GMPays payment processor using HMAC or RSA signatures. Features enhanced order management, automatic status updates, comprehensive transaction tracking, minimum amount validation, and failed payment handling.', 'gmpays-woocommerce-gateway');
        $this->supports           = array(
            'products',
            'refunds',
        );
        
        // Load settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user settings
        $this->title              = $this->get_option('title', __('Credit Card', 'gmpays-woocommerce-gateway'));
        $this->description        = $this->get_option('description', __('Pay securely with your credit card', 'gmpays-woocommerce-gateway'));
        $this->enabled            = $this->get_option('enabled');
        $this->api_url            = $this->get_option('api_url', 'https://pay.gmpays.com');
        $this->project_id         = $this->get_option('project_id');
        $this->auth_method        = $this->get_option('auth_method', 'hmac');
        $this->hmac_key          = $this->get_option('hmac_key');
        $this->private_key        = $this->get_option('private_key');
        
        // Initialize API client based on authentication method
        if (!empty($this->project_id)) {
            if ($this->auth_method === 'hmac' && !empty($this->hmac_key)) {
                $this->api_client = new GMPays_API_Client($this->project_id, $this->hmac_key, $this->api_url, 'hmac');
                if ($this->get_option('debug') === 'yes') {
                    error_log('GMPays: HMAC API client initialized successfully');
                }
            } elseif ($this->auth_method === 'rsa' && !empty($this->private_key)) {
                $this->api_client = new GMPays_API_Client($this->project_id, $this->private_key, $this->api_url, 'rsa');
                if ($this->get_option('debug') === 'yes') {
                    error_log('GMPays: RSA API client initialized successfully');
                }
            } else {
                if ($this->get_option('debug') === 'yes') {
                    error_log('GMPays: API client not initialized. Project ID: ' . $this->project_id . ', Auth Method: ' . $this->auth_method . ', HMAC Key: ' . (!empty($this->hmac_key) ? 'set' : 'not set') . ', Private Key: ' . (!empty($this->private_key) ? 'set' : 'not set'));
                }
            }
        }
        
        $this->currency_manager = new GMPays_Currency_Manager();
        
        // Core WooCommerce hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_response'));
        
        // Admin hooks
        add_action('add_meta_boxes', array($this, 'add_gmpays_payment_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Checkout validation
        add_action('woocommerce_checkout_process', array($this, 'check_minimum_amount'));
        
        // Payment return handling
        add_action('woocommerce_thankyou', array($this, 'handle_payment_return'), 10, 1);
        
        // AJAX handlers for admin actions
        add_action('wp_ajax_gmpays_check_status', array($this, 'ajax_check_payment_status'));
        add_action('wp_ajax_nopriv_gmpays_check_status', array($this, 'ajax_check_payment_status'));
        
        if ($this->get_option('debug') === 'yes') {
            error_log('GMPays DEBUG: Gateway class constructor completed - Hooks registered');
        }
    }
    
    /**
     * Enqueue admin scripts for dynamic form fields
     */
    public function admin_scripts() {
        if (isset($_GET['section']) && $_GET['section'] === 'gmpays_credit_card') {
            wp_enqueue_script('gmpays-admin', GMPAYS_WC_GATEWAY_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), GMPAYS_WC_GATEWAY_VERSION, true);
        }
    }
    
    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'gmpays-woocommerce-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable GMPays Credit Card Gateway', 'gmpays-woocommerce-gateway'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Title', 'gmpays-woocommerce-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'gmpays-woocommerce-gateway'),
                'default'     => __('Credit Card', 'gmpays-woocommerce-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'gmpays-woocommerce-gateway'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'gmpays-woocommerce-gateway'),
                'default'     => __('Pay securely using your credit card through GMPays secure payment gateway.', 'gmpays-woocommerce-gateway'),
                'desc_tip'    => true,
            ),
            'api_url' => array(
                'title'       => __('API URL', 'gmpays-woocommerce-gateway'),
                'type'        => 'text',
                'description' => __('GMPays API URL from your control panel (e.g., https://pay.gmpays.com)', 'gmpays-woocommerce-gateway'),
                'default'     => 'https://pay.gmpays.com',
                'desc_tip'    => true,
                'placeholder' => 'https://pay.gmpays.com',
            ),
            'project_id' => array(
                'title'       => __('Project ID', 'gmpays-woocommerce-gateway'),
                'type'        => 'text',
                'description' => __('Your GMPays Project ID (shown as "ID IN PROJECT" in your GMPays control panel, e.g., 603).', 'gmpays-woocommerce-gateway'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => '603',
            ),
            'auth_method' => array(
                'title'       => __('Authentication Method', 'gmpays-woocommerce-gateway'),
                'type'        => 'select',
                'description' => __('Choose how GMPays will authenticate your requests.', 'gmpays-woocommerce-gateway'),
                'default'     => 'hmac',
                'options'     => array(
                    'hmac' => __('HMAC', 'gmpays-woocommerce-gateway'),
                    'rsa'  => __('RSA', 'gmpays-woocommerce-gateway'),
                ),
            ),
            'hmac_key' => array(
                'title'       => __('HMAC Key', 'gmpays-woocommerce-gateway'),
                'type'        => 'textarea',
                'description' => __('Your HMAC Key (for HMAC authentication). Keep this secure!', 'gmpays-woocommerce-gateway'),
                'default'     => '',
                'desc_tip'    => false,
                'placeholder' => "Your HMAC Key",
                'custom_attributes' => array(
                    'rows' => 5,
                    'style' => 'font-family: monospace; width: 100%;'
                ),
            ),
            'private_key' => array(
                'title'       => __('RSA Private Key', 'gmpays-woocommerce-gateway'),
                'type'        => 'textarea',
                'description' => __('Your RSA Private Key (PEM format). Include the full key with BEGIN and END lines. Keep this secure!', 'gmpays-woocommerce-gateway'),
                'default'     => '',
                'desc_tip'    => false,
                'placeholder' => "-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----",
                'custom_attributes' => array(
                    'rows' => 10,
                    'style' => 'font-family: monospace; width: 100%;'
                ),
            ),
            'key_generation_instructions' => array(
                'title'       => __('RSA Key Setup Instructions', 'gmpays-woocommerce-gateway'),
                'type'        => 'title',
                'description' => '<div style="background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0;">
                    <h4 style="margin-top: 0;">How to Generate RSA Keys:</h4>
                    <ol>
                        <li><strong>Generate Private Key:</strong><br>
                        <code>openssl genrsa -out private_key.pem 2048</code></li>
                        <li><strong>Extract Public Key:</strong><br>
                        <code>openssl rsa -in private_key.pem -pubout -out public_key.pem</code></li>
                        <li><strong>Upload Public Key to GMPays:</strong><br>
                        Go to <a href="https://cp.gmpays.com/project/sign" target="_blank">GMPays Signatures page</a> and paste your public key</li>
                        <li><strong>Paste Private Key Above:</strong><br>
                        Copy the entire content of private_key.pem (including BEGIN/END lines) to the field above</li>
                    </ol>
                    <p style="color: #d9534f; margin-bottom: 0;"><strong>⚠️ Security Note:</strong> Never share your private key. Keep it secure!</p>
                </div>',
            ),
            'minimum_amount' => array(
                'title'       => __('Minimum Amount', 'gmpays-woocommerce-gateway'),
                'type'        => 'text',
                'description' => __('Minimum order amount in EUR (GMPays requirement). Orders below this amount will be rejected.', 'gmpays-woocommerce-gateway'),
                'default'     => '5.00',
                'desc_tip'    => true,
                'placeholder' => '5.00',
            ),
            'webhook_configuration' => array(
                'title'       => __('Webhook Configuration', 'gmpays-woocommerce-gateway'),
                'type'        => 'title',
                'description' => sprintf(
                    __('Configure these URLs in your GMPays control panel:', 'gmpays-woocommerce-gateway') . 
                    '<br><strong>Notification URL (URL для оповещений о выплатах):</strong> <code>%s</code>' .
                    '<br><strong>Success URL (URL перенаправления пользователя в случае успешной оплаты):</strong> <code>%s</code>' .
                    '<br><strong>Failure URL (URL перенаправления пользователя в случае неуспешной оплаты):</strong> <code>%s</code>' .
                    '<br><strong>Cancel URL (URL перенаправления пользователя при отмене оплаты):</strong> <code>%s</code>',
                    home_url('/wp-json/gmpays/v1/webhook'),
                    home_url('/?gmpays_success=1&order_id={order_id}'),
                    home_url('/?gmpays_failure=1&order_id={order_id}'),
                    home_url('/?gmpays_cancelled=1&order_id={order_id}')
                ),
            ),
            'debug' => array(
                'title'       => __('Debug Log', 'gmpays-woocommerce-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'gmpays-woocommerce-gateway'),
                'default'     => 'no',
                'description' => sprintf(__('Log GMPays events, such as API requests, inside %s', 'gmpays-woocommerce-gateway'), '<code>' . WC_Log_Handler_File::get_log_file_path('gmpays-gateway') . '</code>'),
            ),
        );
    }
    
    /**
     * Validate private key field
     */
    public function validate_private_key_field($key, $value) {
        if (empty($value)) {
            return '';
        }
        
        // Check if it looks like a valid PEM private key
        if (strpos($value, '-----BEGIN') === false || strpos($value, '-----END') === false) {
            WC_Admin_Settings::add_error(__('Invalid private key format. Please include the full PEM formatted key with BEGIN and END lines.', 'gmpays-woocommerce-gateway'));
            return '';
        }
        
        // Test if the private key can be parsed
        $test_key = openssl_pkey_get_private($value);
        if (!$test_key) {
            WC_Admin_Settings::add_error(__('Invalid private key. Please check your key format.', 'gmpays-woocommerce-gateway'));
            return '';
        }
        
        return $value;
    }
    
    /**
     * Check if the gateway is available
     */
    public function is_available() {
        if ('yes' !== $this->enabled) {
            return false;
        }
        
        if (!$this->is_configured()) {
            return false;
        }
        
        if (!$this->is_valid_for_use()) {
            return false;
        }
        
        // Check minimum amount requirement
        if (!$this->meets_minimum_amount()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if this gateway is properly configured
     * 
     * @return bool
     */
    public function is_configured() {
        if (empty($this->project_id)) {
            return false;
        }
        
        if ($this->auth_method === 'hmac') {
            return !empty($this->hmac_key);
        } elseif ($this->auth_method === 'rsa') {
            return !empty($this->private_key);
        }
        
        return false;
    }
    
    /**
     * Check if this gateway is available for the user's currency
     */
    public function is_valid_for_use() {
        // Get current currency
        $currency = get_woocommerce_currency();
        
        // GMPays processes in USD, but we can accept orders in any currency
        // as long as we have the WooCommerce Multi Currency plugin to convert
        if (!in_array($currency, array('USD', 'EUR', 'COP', 'MXN', 'ARS', 'VES', 'PEN', 'CLP', 'BRL', 'UYU'))) {
            return false;
        }
        
        // Check if WooCommerce Multi Currency is active and has USD configured
        if ($currency !== 'USD' && !$this->is_multi_currency_configured()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if WooCommerce Multi Currency is configured with USD
     */
    private function is_multi_currency_configured() {
        if (!class_exists('WOOMULTI_CURRENCY_Data')) {
            return false;
        }
        
        $wmc_settings = new WOOMULTI_CURRENCY_Data();
        $currencies = $wmc_settings->get_list_currencies();
        
        return isset($currencies['USD']);
    }
    
    /**
     * Check if the current order meets the minimum amount requirement
     */
    private function meets_minimum_amount() {
        $minimum_amount = floatval($this->get_option('minimum_amount', '5.00'));
        
        // Get cart total
        $cart_total = WC()->cart ? WC()->cart->get_total('edit') : 0;
        
        // If we have a cart, check the total
        if ($cart_total > 0) {
            // Convert to EUR if needed (GMPays minimum is in EUR)
            $cart_total_eur = $this->currency_manager->convert_to_eur(WC()->cart);
            
            if ($cart_total_eur < $minimum_amount) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check minimum amount during checkout process
     */
    public function check_minimum_amount() {
        if (!$this->meets_minimum_amount()) {
            $minimum_amount = $this->get_option('minimum_amount', '5.00');
            $store_currency = get_woocommerce_currency();
            
            // Convert minimum amount to store currency for display
            $display_minimum = $minimum_amount;
            if ($store_currency !== 'EUR') {
                // Try to convert EUR to store currency for better user experience
                if (class_exists('WOOMULTI_CURRENCY_Data')) {
                    try {
                        $wmc_settings = WOOMULTI_CURRENCY_Data::get_ins();
                        $currencies = $wmc_settings->get_list_currencies();
                        if (isset($currencies['EUR']) && isset($currencies[$store_currency])) {
                            $eur_rate = floatval($currencies['EUR']['rate']);
                            $store_rate = floatval($currencies[$store_currency]['rate']);
                            if ($eur_rate > 0 && $store_rate > 0) {
                                $display_minimum = ($minimum_amount / $eur_rate) * $store_rate;
                                $display_minimum = number_format($display_minimum, 2);
                            }
                        }
                    } catch (Exception $e) {
                        // Keep EUR amount if conversion fails
                    }
                }
            }
            
            wc_add_notice(
                sprintf(
                    __('Order amount is below the minimum required (%s EUR). Please add more items to your cart.', 'gmpays-woocommerce-gateway'),
                    $display_minimum
                ),
                'error'
            );
        }
    }
    
    /**
     * Handle payment return using WooCommerce's built-in thank you page hook
     * This is the PRIMARY method for handling payment returns
     */
    public function handle_payment_return($order_id) {
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== 'gmpays_credit_card') {
            return;
        }
        
        if ($this->get_option('debug') === 'yes') {
            error_log('GMPays DEBUG: handle_payment_return called for order ID: ' . $order_id);
        }
        
        // Check if order is already processed
        if ($order->is_paid() || in_array($order->get_status(), array('processing', 'completed', 'on-hold'))) {
            if ($this->get_option('debug') === 'yes') {
                error_log('GMPays DEBUG: Order ' . $order_id . ' already processed, skipping');
            }
            return;
        }
        
        // Get GMPays invoice ID from order metadata
        $invoice_id = $order->get_meta('_gmpays_invoice_id');
        if (!$invoice_id) {
            if ($this->get_option('debug') === 'yes') {
                error_log('GMPays DEBUG: No invoice ID found for order ' . $order_id);
            }
            return;
        }
        
        if ($this->get_option('debug') === 'yes') {
            error_log('GMPays DEBUG: Checking payment status for invoice ID: ' . $invoice_id);
        }
        
        // Check payment status via GMPays API
        $this->check_and_update_payment_status($order, $invoice_id);
    }
    
    /**
     * Check payment status via GMPays API and update order accordingly
     */
    private function check_and_update_payment_status($order, $invoice_id) {
        if (!$this->api_client) {
            if ($this->get_option('debug') === 'yes') {
                error_log('GMPays DEBUG: API client not initialized');
            }
            return;
        }
        
        try {
            // Get payment status from GMPays
            $status_response = $this->api_client->get_invoice_status($invoice_id);
            
            if ($this->get_option('debug') === 'yes') {
                error_log('GMPays DEBUG: Status response: ' . print_r($status_response, true));
            }
            
            if (!$status_response || !isset($status_response['state']) || $status_response['state'] !== 'success') {
                if ($this->get_option('debug') === 'yes') {
                    error_log('GMPays DEBUG: Failed to get status for invoice ' . $invoice_id);
                }
                return;
            }
            
            $payment_data = $status_response;
            $payment_status = $payment_data['status'] ?? 'unknown';
            
            if ($this->get_option('debug') === 'yes') {
                error_log('GMPays DEBUG: Payment status: ' . $payment_status);
            }
            
            // Process payment status based on GMPays documentation
            switch (strtolower($payment_status)) {
                case 'paid':
                    $this->process_successful_payment($order, $payment_data);
                    break;
                    
                case 'refused':
                    $this->process_failed_payment($order, $payment_data);
                    break;
                    
                case 'new':
                    $this->process_cancelled_payment($order, $payment_data);
                    break;
                    
                case 'processing':
                    $this->process_processing_payment($order, $payment_data);
                    break;
                    
                default:
                    if ($this->get_option('debug') === 'yes') {
                        error_log('GMPays DEBUG: Unknown payment status: ' . $payment_status);
                    }
                    break;
            }
            
        } catch (Exception $e) {
            if ($this->get_option('debug') === 'yes') {
                error_log('GMPays DEBUG: Error checking payment status: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Process successful payment
     */
    private function process_successful_payment($order, $payment_data) {
        $order_id = $order->get_id();
        
        if ($this->get_option('debug') === 'yes') {
            error_log('GMPays DEBUG: Processing successful payment for order ' . $order_id);
        }
        
        // Get transaction details
        $transaction_id = $payment_data['invoice'] ?? $payment_data['project_invoice'] ?? $order_id;
        $amount = $payment_data['amount'] ?? $order->get_total();
        $currency = $payment_data['currency_project'] ?? 'USD';
        
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
            $order->get_order_number(),
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
        
        if ($this->get_option('debug') === 'yes') {
            error_log('GMPays DEBUG: Order ' . $order_id . ' marked as successful');
        }
    }
    
    /**
     * Process failed payment
     */
    private function process_failed_payment($order, $payment_data) {
        $order_id = $order->get_id();
        
        if ($this->get_option('debug') === 'yes') {
            error_log('GMPays DEBUG: Processing failed payment for order ' . $order_id);
        }
        
        // Get failure details
        $reason = $payment_data['reason'] ?? $payment_data['comment'] ?? __('Payment was refused by payment processor', 'gmpays-woocommerce-gateway');
        
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
            $order->get_order_number(),
            $reason
        );
        $order->add_order_note($private_note, false, true);
        
        // Update metadata
        $order->update_meta_data('_gmpays_payment_status', 'failed');
        $order->update_meta_data('_gmpays_payment_failed_at', current_time('mysql'));
        $order->update_meta_data('_gmpays_payment_failure_reason', $reason);
        
        $order->save();
        
        if ($this->get_option('debug') === 'yes') {
            error_log('GMPays DEBUG: Order ' . $order_id . ' marked as failed');
        }
    }
    
    /**
     * Process cancelled payment
     */
    private function process_cancelled_payment($order, $payment_data) {
        $order_id = $order->get_id();
        
        if ($this->get_option('debug') === 'yes') {
            error_log('GMPays DEBUG: Processing cancelled payment for order ' . $order_id);
        }
        
        // Update order status
        $order->update_status('cancelled', __('Payment cancelled via GMPays', 'gmpays-woocommerce-gateway'));
        
        // Add order notes
        $public_note = __('Payment was cancelled via GMPays. You can try again or choose a different payment method.', 'gmpays-woocommerce-gateway');
        $order->add_order_note($public_note, false, false);
        
        $private_note = sprintf(
            __('GMPays Payment Cancelled - Order #%s payment was cancelled via GMPays. Customer did not complete payment.', 'gmpays-woocommerce-gateway'),
            $order->get_order_number()
        );
        $order->add_order_note($private_note, false, true);
        
        // Update metadata
        $order->update_meta_data('_gmpays_payment_status', 'cancelled');
        $order->update_meta_data('_gmpays_payment_cancelled_at', current_time('mysql'));
        
        $order->save();
        
        if ($this->get_option('debug') === 'yes') {
            error_log('GMPays DEBUG: Order ' . $order_id . ' marked as cancelled');
        }
    }
    
    /**
     * Process processing payment
     */
    private function process_processing_payment($order, $payment_data) {
        $order_id = $order->get_id();
        
        if ($this->get_option('debug') === 'yes') {
            error_log('GMPays DEBUG: Processing payment for order ' . $order_id);
        }
        
        // Update order status to processing
        $order->update_status('processing', __('Payment is being processed via GMPays', 'gmpays-woocommerce-gateway'));
        
        // Add order note
        $note = __('Payment is being processed via GMPays. Please wait for confirmation.', 'gmpays-woocommerce-gateway');
        $order->add_order_note($note, false, false);
        
        // Update metadata
        $order->update_meta_data('_gmpays_payment_status', 'processing');
        $order->update_meta_data('_gmpays_payment_processing_at', current_time('mysql'));
        
        $order->save();
        
        if ($this->get_option('debug') === 'yes') {
            error_log('GMPays DEBUG: Order ' . $order_id . ' marked as processing');
        }
    }
    
    /**
     * AJAX handler for checking payment status from admin
     */
    public function ajax_check_payment_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gmpays_check_status')) {
            wp_die(__('Security check failed', 'gmpays-woocommerce-gateway'));
        }
        
        $order_id = intval($_POST['order_id']);
        if (!$order_id) {
            wp_send_json_error(__('Invalid order ID', 'gmpays-woocommerce-gateway'));
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(__('Order not found', 'gmpays-woocommerce-gateway'));
        }
        
        if ($order->get_payment_method() !== 'gmpays_credit_card') {
            wp_send_json_error(__('This order is not a GMPays payment', 'gmpays-woocommerce-gateway'));
        }
        
        // Get GMPays invoice ID
        $invoice_id = $order->get_meta('_gmpays_invoice_id');
        if (!$invoice_id) {
            wp_send_json_error(__('No GMPays invoice ID found for this order', 'gmpays-woocommerce-gateway'));
        }
        
        try {
            // Check payment status via API
            $this->check_and_update_payment_status($order, $invoice_id);
            
            // Get updated order
            $order = wc_get_order($order_id);
            $current_status = $order->get_status();
            $status_name = wc_get_order_status_name($current_status);
            
            wp_send_json_success(array(
                'message' => sprintf(__('Payment status updated successfully. Current status: %s', 'gmpays-woocommerce-gateway'), $status_name),
                'status' => $current_status,
                'status_name' => $status_name
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(sprintf(__('Error checking payment status: %s', 'gmpays-woocommerce-gateway'), $e->getMessage()));
        }
    }
    
    /**
     * Process the payment
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $logger = wc_get_logger();
        
        if ('yes' === $this->get_option('debug')) {
            $logger->debug('GMPays process_payment started for order ID: ' . $order_id, array('source' => 'gmpays-gateway'));
        }
        
        try {
            // Check if API client is initialized
            if (!$this->api_client) {
                throw new Exception(__('Payment gateway not properly configured. Please contact the store administrator.', 'gmpays-woocommerce-gateway'));
            }
            
            // Prepare order data
            $order_data = $this->prepare_order_data($order);
            
            if ('yes' === $this->get_option('debug')) {
                $logger->debug('Prepared order data: ' . json_encode($order_data), array('source' => 'gmpays-gateway'));
            }
            
            // Create payment invoice via GMPays API
            $response = $this->api_client->create_invoice($order_data);
            
            if ('yes' === $this->get_option('debug')) {
                $logger->debug('GMPays API response: ' . json_encode($response), array('source' => 'gmpays-gateway'));
            }
            
            if (!$response || !isset($response['success']) || !$response['success']) {
                throw new Exception(__('Unable to create payment session. Please try again or contact support.', 'gmpays-woocommerce-gateway'));
            }
            
            // Store GMPays invoice ID in order metadata for later status checking
            if (isset($response['invoice_id'])) {
                $order->update_meta_data('_gmpays_invoice_id', $response['invoice_id']);
                $order->save();
                
                if ($this->get_option('debug') === 'yes') {
                    $logger->debug('GMPays invoice ID stored in order metadata: ' . $response['invoice_id'], array('source' => 'gmpays-gateway'));
                }
            }
            
            // Return success with redirect URL
            return array(
                'result' => 'success',
                'redirect' => $response['payment_url']
            );
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            wc_add_notice(__('Payment error:', 'gmpays-woocommerce-gateway') . ' ' . $error_message, 'error');
            
            if ('yes' === $this->get_option('debug')) {
                $logger->error('GMPays payment error: ' . $error_message . ' - Order ID: ' . $order_id, array('source' => 'gmpays-gateway'));
                $logger->error('Stack trace: ' . $e->getTraceAsString(), array('source' => 'gmpays-gateway'));
            }
            
            // Add order note about the failure
            $order->add_order_note(
                sprintf(__('GMPays payment failed: %s', 'gmpays-woocommerce-gateway'), $error_message)
            );
            
            // Mark order as failed
            $order->update_status('failed', __('Payment failed during processing: ' . $error_message, 'gmpays-woocommerce-gateway'));
            
            return array(
                'result' => 'fail',
                'redirect' => wc_get_cart_url()
            );
        }
    }
    
    /**
     * Process refund
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('invalid_order', __('Invalid order ID', 'gmpays-woocommerce-gateway'));
        }
        
        $invoice_id = $order->get_meta('_gmpays_invoice_id');
        
        if (!$invoice_id) {
            return new WP_Error('no_invoice', __('No GMPays invoice found for this order', 'gmpays-woocommerce-gateway'));
        }
        
        // Note: GMPays refund API needs to be implemented when available
        return new WP_Error('not_implemented', __('Refunds must be processed manually through GMPays control panel', 'gmpays-woocommerce-gateway'));
    }
    
    /**
     * Prepare order data for GMPays API
     */
    private function prepare_order_data($order) {
        // Get order total in USD
        $order_total_usd = $this->currency_manager->convert_to_usd($order);
        
        $logger = wc_get_logger();
        if ('yes' === $this->get_option('debug')) {
            $logger->debug('Order total in USD: ' . $order_total_usd, array('source' => 'gmpays-gateway'));
            $logger->debug('Original currency: ' . $order->get_currency() . ', Original amount: ' . $order->get_total(), array('source' => 'gmpays-gateway'));
        }
        
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $customer_email = $order->get_billing_email();
        
        // Use WooCommerce's standard return URLs
        $success_url = $this->get_return_url($order);
        $cancel_url = wc_get_cart_url();
        
        // Prepare invoice data for GMPays API
        $invoice_data = array(
            'amount' => $order_total_usd,
            'currency' => 'USD',
            'order_id' => $order->get_id(),
            'description' => $this->get_order_description($order),
            'customer_email' => $customer_email,
            'customer_name' => trim($customer_name),
            'customer_ip' => $order->get_customer_ip_address(),
            'return_url' => $success_url,
            'cancel_url' => $cancel_url,
            'webhook_url' => home_url('/wp-json/gmpays/v1/webhook'),
        );
        
        return apply_filters('gmpays_invoice_data', $invoice_data, $order);
    }
    
    /**
     * Get order description
     */
    private function get_order_description($order) {
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = $item->get_name() . ' x ' . $item->get_quantity();
        }
        
        $description = sprintf(
            __('Order #%s from %s', 'gmpays-woocommerce-gateway'),
            $order->get_order_number(),
            get_bloginfo('name')
        );
        
        // Limit description length
        if (!empty($items)) {
            $items_text = implode(', ', array_slice($items, 0, 3));
            if (count($items) > 3) {
                $items_text .= '...';
            }
            $description .= ': ' . $items_text;
        }
        
        // Add note about processing fee
        $description .= ' (El importe reflejado muestra la comisión de procesamiento bancario)';
        
        // GMPays has a 255 character limit for descriptions
        if (strlen($description) > 255) {
            $description = substr($description, 0, 252) . '...';
        }
        
        return $description;
    }
    
    /**
     * Add meta box for GMPays payment details
     */
    public function add_gmpays_payment_meta_box() {
        add_meta_box(
            'gmpays-payment-details',
            __('GMPays Payment Details', 'gmpays-woocommerce-gateway'),
            array($this, 'display_gmpays_payment_meta_box'),
            'shop_order',
            'side',
            'default'
        );
    }
    
    /**
     * Display GMPays payment meta box content
     */
    public function display_gmpays_payment_meta_box($post) {
        $order = wc_get_order($post->ID);
        if (!$order || $order->get_payment_method() !== 'gmpays_credit_card') {
            echo '<p>' . __('This order is not a GMPays payment.', 'gmpays-woocommerce-gateway') . '</p>';
            return;
        }
        
        $invoice_id = $order->get_meta('_gmpays_invoice_id');
        $payment_status = $order->get_meta('_gmpays_payment_status');
        
        echo '<div class="gmpays-payment-meta-box">';
        
        if ($invoice_id) {
            echo '<p><strong>' . __('Invoice ID:', 'gmpays-woocommerce-gateway') . '</strong> ' . esc_html($invoice_id) . '</p>';
        }
        
        if ($payment_status) {
            echo '<p><strong>' . __('Payment Status:', 'gmpays-woocommerce-gateway') . '</strong> ' . esc_html($payment_status) . '</p>';
        }
        
        echo '<p><strong>' . __('Order Status:', 'gmpays-woocommerce-gateway') . '</strong> ' . wc_get_order_status_name($order->get_status()) . '</p>';
        
        // Add check status button
        echo '<p><button type="button" class="button button-secondary gmpays-check-status" data-order-id="' . $order->get_id() . '">' . __('Check Payment Status', 'gmpays-woocommerce-gateway') . '</button></p>';
        
        // Add status result div
        echo '<div id="gmpays-status-result-' . $order->get_id() . '" class="gmpays-status-result" style="display: none;"></div>';
        
        echo '</div>';
        
        // Add JavaScript for AJAX functionality
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.gmpays-check-status').on('click', function() {
                var button = $(this);
                var orderId = button.data('order-id');
                var resultDiv = $('#gmpays-status-result-' + orderId);
                
                button.prop('disabled', true).text('<?php _e('Checking...', 'gmpays-woocommerce-gateway'); ?>');
                resultDiv.html('').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gmpays_check_status',
                        order_id: orderId,
                        nonce: '<?php echo wp_create_nonce('gmpays_check_status'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            resultDiv.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<div class="notice notice-error"><p><?php _e('Error checking status', 'gmpays-woocommerce-gateway'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Check Payment Status', 'gmpays-woocommerce-gateway'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}