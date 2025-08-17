<?php
/**
 * GMPays Credit Card Payment Gateway Class - Fixed for RSA
 *
 * Handles credit card payment processing through GMPays with RSA signatures
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
        $this->api_url            = $this->get_option('api_url', 'https://paygate.gamemoney.com');
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
        
        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_response'));
        
        // Add admin order page hooks
        add_action('woocommerce_admin_order_data_after_payment_info', array($this, 'display_gmpays_payment_details'));
        add_action('add_meta_boxes', array($this, 'add_gmpays_payment_meta_box'));
        
        // Add admin scripts for dynamic form fields
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Add minimum amount validation message
        add_action('woocommerce_checkout_process', array($this, 'check_minimum_amount'));
        
        // Handle failed payment returns
        add_action('woocommerce_cart_loaded_from_session', array($this, 'handle_failed_payment_return'));
        
        // Handle GMPays return URLs - Use proper WooCommerce hooks
        error_log('GMPays DEBUG: Registering WooCommerce hooks for return handling');
        add_action('woocommerce_thankyou', array($this, 'handle_success_return_thankyou'), 10, 1);
        add_action('woocommerce_cart_loaded_from_session', array($this, 'handle_failure_cancelled_return'));
        add_action('woocommerce_before_cart', array($this, 'handle_failure_cancelled_return'));
        add_action('woocommerce_before_checkout_form', array($this, 'handle_failure_cancelled_return'));
        error_log('GMPays DEBUG: WooCommerce hooks registered successfully');
        
        // Add debugging hook to verify hook execution
        add_action('wp_loaded', array($this, 'debug_hook_registration'));
        
        // Add AJAX handlers for admin actions
        add_action('wp_ajax_gmpays_check_status', array($this, 'ajax_check_payment_status'));
        add_action('wp_ajax_nopriv_gmpays_check_status', array($this, 'ajax_check_payment_status'));
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
                'description' => __('GMPays API URL from your control panel (e.g., https://paygate.gamemoney.com)', 'gmpays-woocommerce-gateway'),
                'default'     => 'https://paygate.gamemoney.com',
                'desc_tip'    => true,
                'placeholder' => 'https://paygate.gamemoney.com',
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
     * Handle failed payment returns when customer comes back to cart
     */
    public function handle_failed_payment_return() {
        // Check if we have a failed payment return
        if (isset($_GET['gmpays_return']) && $_GET['gmpays_return'] === 'failed') {
            $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
            
            if ($order_id > 0) {
                $order = wc_get_order($order_id);
                
                if ($order && $order->get_payment_method() === 'gmpays_credit_card') {
                    // Mark order as failed
                    $order->update_status('failed', __('Payment failed - Customer returned without completing payment via GMPays', 'gmpays-woocommerce-gateway'));
                    
                    // Add private note
                    $order->add_order_note(
                        __('GMPays Payment Return - Customer returned to cart without completing payment. Order marked as failed.', 'gmpays-woocommerce-gateway'),
                        false,
                        true
                    );
                    
                    // Restore cart items
                    $this->restore_cart_from_order($order);
                    
                    // Show notice to customer
                    wc_add_notice(
                        __('Your payment was not completed. The order has been cancelled and items restored to your cart. Please try again with a valid amount.', 'gmpays-woocommerce-gateway'),
                        'notice'
                    );
                    
                    // Clear the URL parameters
                    wp_redirect(remove_query_arg(array('gmpays_return', 'order_id')));
                    exit;
                }
            }
        }
    }
    
    /**
     * Handle successful payment return from GMPays on thank you page
     */
    public function handle_success_return_thankyou($order_id) {
        // DEBUG: Log method entry
        error_log('GMPays DEBUG: handle_success_return_thankyou called with order_id: ' . $order_id);
        error_log('GMPays DEBUG: $_GET parameters: ' . print_r($_GET, true));
        
        // Only process if we have GMPays success parameters
        if (!isset($_GET['gmpays_success']) || !isset($_GET['order_id'])) {
            error_log('GMPays DEBUG: Missing required parameters - gmpays_success: ' . (isset($_GET['gmpays_success']) ? 'YES' : 'NO') . ', order_id: ' . (isset($_GET['order_id']) ? 'YES' : 'NO'));
            return;
        }
        
        // Verify order ID matches
        if (intval($_GET['order_id']) !== $order_id) {
            error_log('GMPays DEBUG: Order ID mismatch - GET order_id: ' . $_GET['order_id'] . ', method order_id: ' . $order_id);
            return;
        }
        
        error_log('GMPays DEBUG: Parameters validated, proceeding with order processing');
        
        if ($this->get_option('debug') === 'yes') {
            wc_get_logger()->info('GMPays: Processing success return for order ' . $order_id, array('source' => 'gmpays-gateway'));
        }
        
        error_log('GMPays DEBUG: Attempting to get order with ID: ' . $order_id);
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('GMPays DEBUG: Failed to get order with ID: ' . $order_id);
            return;
        }
        
        error_log('GMPays DEBUG: Order retrieved successfully - Order ID: ' . $order->get_id() . ', Payment Method: ' . $order->get_payment_method());
        
        if ($order->get_payment_method() !== 'gmpays_credit_card') {
            error_log('GMPays DEBUG: Order payment method mismatch - Expected: gmpays_credit_card, Got: ' . $order->get_payment_method());
            return;
        }
        
        // Check if order is already processed
        if ($order->is_paid()) {
            error_log('GMPays DEBUG: Order is already paid, skipping processing');
            return;
        }
        
        error_log('GMPays DEBUG: Order validation passed, proceeding with status update');
        
        // Get GMPays transaction details from URL parameters
        $transaction_id = isset($_GET['transaction_id']) ? sanitize_text_field($_GET['transaction_id']) : '';
        $amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
        $currency = isset($_GET['currency']) ? sanitize_text_field($_GET['currency']) : 'USD';
        
        // Also check for invoice parameter (GMPays specific)
        if (empty($transaction_id) && isset($_GET['invoice'])) {
            $transaction_id = sanitize_text_field($_GET['invoice']);
        }
        
        error_log('GMPays DEBUG: About to update order status to on-hold');
        
        // Mark order as on-hold (pending confirmation)
        $status_result = $order->update_status('on-hold', __('Payment received via GMPays - Order placed on hold for confirmation', 'gmpays-woocommerce-gateway'));
        error_log('GMPays DEBUG: Order status update result: ' . ($status_result ? 'SUCCESS' : 'FAILED'));
        
        // Add public note
        $note = sprintf(
            __('Payment completed successfully via GMPays.\nTransaction ID: %s\nAmount: %s %s\nPayment Method: Credit Card', 'gmpays-woocommerce-gateway'),
            $transaction_id ?: 'Pending',
            $amount ?: $order->get_total(),
            strtoupper($currency)
        );
        $note_result = $order->add_order_note($note, false, false);
        error_log('GMPays DEBUG: Public note addition result: ' . ($note_result ? 'SUCCESS' : 'FAILED'));
        
        // Add private note
        $private_note = sprintf(
            __('GMPays Payment Success - Order #%s has been paid via GMPays. Transaction ID: %s. Order placed on hold for manual review.', 'gmpays-woocommerce-gateway'),
            $order->get_order_number(),
            $transaction_id ?: 'Pending'
        );
        $private_note_result = $order->add_order_note($private_note, false, true);
        error_log('GMPays DEBUG: Private note addition result: ' . ($private_note_result ? 'SUCCESS' : 'FAILED'));
        
        error_log('GMPays DEBUG: About to update payment metadata');
        
        // Update payment metadata
        if ($transaction_id) {
            $order->update_meta_data('_gmpays_transaction_id', $transaction_id);
            $order->set_transaction_id($transaction_id);
            error_log('GMPays DEBUG: Transaction ID metadata updated: ' . $transaction_id);
        }
        $order->update_meta_data('_gmpays_payment_status', 'completed');
        $order->update_meta_data('_gmpays_payment_completed_at', current_time('mysql'));
        
        error_log('GMPays DEBUG: About to save order');
        $save_result = $order->save();
        error_log('GMPays DEBUG: Order save result: ' . ($save_result ? 'SUCCESS' : 'FAILED'));
        
        error_log('GMPays DEBUG: About to complete payment');
        // Complete payment
        $payment_complete_result = $order->payment_complete($transaction_id);
        error_log('GMPays DEBUG: Payment complete result: ' . ($payment_complete_result ? 'SUCCESS' : 'FAILED'));
        
        // Clear cart
        if (WC()->cart) {
            WC()->cart->empty_cart();
            error_log('GMPays DEBUG: Cart cleared successfully');
        } else {
            error_log('GMPays DEBUG: No cart available to clear');
        }
        
        error_log('GMPays DEBUG: handle_success_return_thankyou completed successfully for order: ' . $order_id);
    }
    
    /**
     * Handle failed/cancelled payment returns from GMPays
     */
    public function handle_failure_cancelled_return() {
        error_log('GMPays DEBUG: handle_failure_cancelled_return called');
        error_log('GMPays DEBUG: $_GET parameters: ' . print_r($_GET, true));
        
        // Only process on frontend
        if (is_admin()) {
            error_log('GMPays DEBUG: In admin area, skipping processing');
            return;
        }
        
        // Handle failure return from GMPays
        if (isset($_GET['gmpays_failure']) && isset($_GET['order_id'])) {
            error_log('GMPays DEBUG: Processing failure return for order ' . $_GET['order_id']);
            if ($this->get_option('debug') === 'yes') {
                wc_get_logger()->info('GMPays: Processing failure return for order ' . $_GET['order_id'], array('source' => 'gmpays-gateway'));
            }
            $this->handle_failure_return();
            return; // Exit after processing to avoid duplicate processing
        }
        
        // Handle cancelled return from GMPays
        if (isset($_GET['gmpays_cancelled']) && isset($_GET['order_id'])) {
            error_log('GMPays DEBUG: Processing cancelled return for order ' . $_GET['order_id']);
            if ($this->get_option('debug') === 'yes') {
                wc_get_logger()->info('GMPays: Processing cancelled return for order ' . $_GET['order_id'], array('source' => 'gmpays-gateway'));
            }
            $this->handle_cancelled_return();
            return; // Exit after processing to avoid duplicate processing
        }
        
        error_log('GMPays DEBUG: No matching parameters found for failure/cancelled return');
    }
    
    /**
     * Handle failed payment return from GMPays
     */
    private function handle_failure_return() {
        error_log('GMPays DEBUG: handle_failure_return called');
        $order_id = intval($_GET['order_id']);
        error_log('GMPays DEBUG: Processing failure for order ID: ' . $order_id);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('GMPays DEBUG: Failed to get order for failure return - Order ID: ' . $order_id);
            return;
        }
        
        error_log('GMPays DEBUG: Order retrieved for failure return - Order ID: ' . $order->get_id() . ', Payment Method: ' . $order->get_payment_method());
        
        if ($order->get_payment_method() !== 'gmpays_credit_card') {
            error_log('GMPays DEBUG: Payment method mismatch in failure return - Expected: gmpays_credit_card, Got: ' . $order->get_payment_method());
            return;
        }
        
        // Get failure reason from URL parameters
        $reason = isset($_GET['reason']) ? sanitize_text_field($_GET['reason']) : __('Payment processing failed', 'gmpays-woocommerce-gateway');
        $invoice_id = isset($_GET['invoice_id']) ? sanitize_text_field($_GET['invoice_id']) : '';
        
        // Also check for invoice parameter (GMPays specific)
        if (empty($invoice_id) && isset($_GET['invoice'])) {
            $invoice_id = sanitize_text_field($_GET['invoice']);
        }
        
        // Mark order as failed
        $order->update_status('failed', __('Payment failed via GMPays: ' . $reason, 'gmpays-woocommerce-gateway'));
        
        // Add public note
        $note = sprintf(
            __('Payment failed via GMPays.\nInvoice ID: %s\nReason: %s\nPlease contact customer to retry payment.', 'gmpays-woocommerce-gateway'),
            $invoice_id ?: 'N/A',
            $reason
        );
        $order->add_order_note($note, false, false);
        
        // Add private note
        $private_note = sprintf(
            __('GMPays Payment Failure - Order #%s payment failed via GMPays. Invoice ID: %s. Reason: %s', 'gmpays-woocommerce-gateway'),
            $order->get_order_number(),
            $invoice_id ?: 'N/A',
            $reason
        );
        $order->add_order_note($private_note, false, true);
        
        // Update payment metadata
        $order->update_meta_data('_gmpays_payment_status', 'failed');
        $order->update_meta_data('_gmpays_payment_failed_at', current_time('mysql'));
        if ($reason) {
            $order->update_meta_data('_gmpays_payment_failure_reason', $reason);
        }
        
        $order->save();
        
        // Restore cart items
        $this->restore_cart_from_order($order);
        
        // Show notice to customer
        wc_add_notice(
            sprintf(__('Your payment failed: %s. The order has been cancelled and items restored to your cart. Please try again.', 'gmpays-woocommerce-gateway'), $reason),
            'error'
        );
        
        // Redirect to cart
        wp_redirect(wc_get_cart_url());
        exit;
    }
    
    /**
     * Debug hook registration and execution
     */
    public function debug_hook_registration() {
        error_log('GMPays DEBUG: wp_loaded hook executed - Gateway class initialized');
        error_log('GMPays DEBUG: Current page: ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown'));
        error_log('GMPays DEBUG: Is admin: ' . (is_admin() ? 'YES' : 'NO'));
        error_log('GMPays DEBUG: Is frontend: ' . (!is_admin() ? 'YES' : 'NO'));
        
        // Check if we're on a thank you page
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
            error_log('GMPays DEBUG: On WooCommerce thank you page (order-received endpoint)');
        } else {
            error_log('GMPays DEBUG: NOT on WooCommerce thank you page');
        }
        
        // Check if we're on cart page
        if (function_exists('is_cart') && is_cart()) {
            error_log('GMPays DEBUG: On WooCommerce cart page');
        } else {
            error_log('GMPays DEBUG: NOT on WooCommerce cart page');
        }
        
        // Check if we're on checkout page
        if (function_exists('is_checkout') && is_checkout()) {
            error_log('GMPays DEBUG: On WooCommerce checkout page');
        } else {
            error_log('GMPays DEBUG: NOT on WooCommerce checkout page');
        }
        
        // Log current action and filter hooks
        error_log('GMPays DEBUG: Current action: ' . current_action());
        error_log('GMPays DEBUG: Current filter: ' . current_filter());
        
        // Check if our hooks are properly registered
        global $wp_filter;
        if (isset($wp_filter['woocommerce_thankyou'])) {
            error_log('GMPays DEBUG: woocommerce_thankyou hook is registered');
        } else {
            error_log('GMPays DEBUG: woocommerce_thankyou hook is NOT registered');
        }
        
        if (isset($wp_filter['woocommerce_cart_loaded_from_session'])) {
            error_log('GMPays DEBUG: woocommerce_cart_loaded_from_session hook is registered');
        } else {
            error_log('GMPays DEBUG: woocommerce_cart_loaded_from_session hook is NOT registered');
        }
    }
    
    /**
     * Handle cancelled payment return from GMPays
     */
    private function handle_cancelled_return() {
        error_log('GMPays DEBUG: handle_cancelled_return called');
        $order_id = intval($_GET['order_id']);
        error_log('GMPays DEBUG: Processing cancellation for order ID: ' . $order_id);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('GMPays DEBUG: Failed to get order for cancellation return - Order ID: ' . $order_id);
            return;
        }
        
        error_log('GMPays DEBUG: Order retrieved for cancellation return - Order ID: ' . $order->get_id() . ', Payment Method: ' . $order->get_payment_method());
        
        if ($order->get_payment_method() !== 'gmpays_credit_card') {
            error_log('GMPays DEBUG: Payment method mismatch in cancellation return - Expected: gmpays_credit_card, Got: ' . $order->get_payment_method());
            return;
        }
        
        // Get invoice ID if available
        $invoice_id = isset($_GET['invoice']) ? sanitize_text_field($_GET['invoice']) : '';
        
        // Mark order as cancelled
        $order->update_status('cancelled', __('Payment cancelled by customer via GMPays', 'gmpays-woocommerce-gateway'));
        
        // Add public note
        $note = __('Payment cancelled via GMPays.\nCustomer did not complete payment.', 'gmpays-woocommerce-gateway');
        if (!empty($invoice_id)) {
            $note .= '\nInvoice ID: ' . $invoice_id;
        }
        $order->add_order_note($note, false, false);
        
        // Add private note
        $private_note = sprintf(
            __('GMPays Payment Cancellation - Order #%s payment was cancelled via GMPays. Customer did not complete payment.', 'gmpays-woocommerce-gateway'),
            $order->get_order_number()
        );
        $order->add_order_note($private_note, false, true);
        
        // Update payment metadata
        $order->update_meta_data('_gmpays_payment_status', 'cancelled');
        $order->update_meta_data('_gmpays_payment_cancelled_at', current_time('mysql'));
        
        $order->save();
        
        // Restore cart items
        $this->restore_cart_from_order($order);
        
        // Show notice to customer
        wc_add_notice(
            __('Your payment was cancelled. The order has been cancelled and items restored to your cart. Please try again when ready.', 'gmpays-woocommerce-gateway'),
            'notice'
        );
        
        // Redirect to cart
        wp_redirect(wc_get_cart_url());
        exit;
    }
    
    /**
     * Restore cart items from a failed order
     */
    private function restore_cart_from_order($order) {
        if (!WC()->cart) {
            return;
        }
        
        // Clear current cart
        WC()->cart->empty_cart();
        
        // Add items back to cart
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();
            
            if ($variation_id > 0) {
                WC()->cart->add_to_cart($product_id, $quantity, $variation_id);
            } else {
                WC()->cart->add_to_cart($product_id, $quantity);
            }
        }
        
        // Restore cart totals
        WC()->cart->calculate_totals();
    }
    
    /**
     * AJAX handler for checking payment status
     */
    public function ajax_check_payment_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gmpays_check_status')) {
            wp_die(__('Security check failed', 'gmpays-woocommerce-gateway'));
        }
        
        $invoice_id = sanitize_text_field($_POST['invoice_id']);
        $order_id = intval($_POST['order_id']);
        
        if (!$invoice_id || !$order_id) {
            wp_send_json_error(__('Invalid parameters', 'gmpays-woocommerce-gateway'));
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(__('Order not found', 'gmpays-woocommerce-gateway'));
        }
        
        try {
            // Check payment status via GMPays API
            $status_response = $this->api_client->get_invoice_status($invoice_id);
            
            if ($status_response && isset($status_response['status'])) {
                $status = $status_response['status'];
                
                switch ($status) {
                    case 'Paid':
                    case 'paid':
                    case 'success':
                        // Update order status to on-hold
                        $order->update_status('on-hold', __('Payment confirmed via GMPays API check', 'gmpays-woocommerce-gateway'));
                        
                        // Add note
                        $order->add_order_note(
                            __('Payment status confirmed via GMPays API check. Order moved to on-hold status.', 'gmpays-woocommerce-gateway'),
                            false,
                            true
                        );
                        
                        wp_send_json_success(array(
                            'message' => __('Payment confirmed! Order status updated to on-hold.', 'gmpays-woocommerce-gateway'),
                            'status' => 'on-hold'
                        ));
                        break;
                        
                    case 'Failed':
                    case 'failed':
                    case 'error':
                        // Update order status to failed
                        $order->update_status('failed', __('Payment failed via GMPays API check', 'gmpays-woocommerce-gateway'));
                        
                        // Add note
                        $order->add_order_note(
                            __('Payment failed via GMPays API check. Order marked as failed.', 'gmpays-woocommerce-gateway'),
                            false,
                            true
                        );
                        
                        wp_send_json_success(array(
                            'message' => __('Payment failed! Order status updated to failed.', 'gmpays-woocommerce-gateway'),
                            'status' => 'failed'
                        ));
                        break;
                        
                    case 'Pending':
                    case 'pending':
                    case 'processing':
                        wp_send_json_success(array(
                            'message' => __('Payment is still processing. Please wait for confirmation.', 'gmpays-woocommerce-gateway'),
                            'status' => 'pending'
                        ));
                        break;
                        
                    default:
                        wp_send_json_success(array(
                            'message' => sprintf(__('Payment status: %s', 'gmpays-woocommerce-gateway'), $status),
                            'status' => $status
                        ));
                        break;
                }
            } else {
                wp_send_json_error(__('Unable to retrieve payment status from GMPays', 'gmpays-woocommerce-gateway'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(__('Error checking payment status: ', 'gmpays-woocommerce-gateway') . $e->getMessage());
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
            
            if (!$response || !isset($response['invoice_id']) || !isset($response['payment_url'])) {
                throw new Exception(__('Unable to create payment session. Please try again or contact support.', 'gmpays-woocommerce-gateway'));
            }
            
            // Save GMPays invoice data to order
            $order->update_meta_data('_gmpays_invoice_id', $response['invoice_id']);
            $order->update_meta_data('_gmpays_payment_url', $response['payment_url']);
            $order->update_meta_data('_gmpays_payment_method', 'credit_card');
            
            // Set payment method
            $order->set_payment_method('gmpays_credit_card');
            $order->set_payment_method_title($this->title);
            
            // Add order note
            $order->add_order_note(sprintf(
                __('Payment initiated via GMPays. Invoice ID: %s. Customer redirected to payment page.', 'gmpays-woocommerce-gateway'),
                $response['invoice_id']
            ), false, true);
            
            $order->save();
            
            // Set order status to pending
            $order->update_status('pending', __('Awaiting GMPays payment confirmation.', 'gmpays-woocommerce-gateway'));
            
            // Reduce stock levels
            wc_reduce_stock_levels($order_id);
            
            // Remove cart
            WC()->cart->empty_cart();
            
            // Redirect to GMPays payment page
            return array(
                'result'   => 'success',
                'redirect' => $response['payment_url'],
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
            
            // Return to cart with failure parameters
            return array(
                'result' => 'fail',
                'redirect' => add_query_arg(array(
                    'gmpays_return' => 'failed',
                    'order_id' => $order_id
                ), wc_get_cart_url())
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
        
        // Prepare return URLs for GMPays - Use proper WooCommerce endpoints
        $success_url = add_query_arg(array(
            'gmpays_success' => '1',
            'order_id' => $order->get_id()
        ), $this->get_return_url($order));
        
        $failure_url = add_query_arg(array(
            'gmpays_failure' => '1',
            'order_id' => $order->get_id()
        ), wc_get_cart_url());
        
        $cancel_url = add_query_arg(array(
            'gmpays_cancelled' => '1',
            'order_id' => $order->get_id()
        ), wc_get_cart_url());
        
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
     * Display GMPays payment details in admin
     */
    public function display_gmpays_payment_details($order) {
        $invoice_id = get_post_meta($order->get_id(), '_gmpays_invoice_id', true);
        
        if ($invoice_id) {
            echo '<div class="gmpays-payment-details">';
            echo '<strong>' . __('GMPays Invoice ID:', 'gmpays-woocommerce-gateway') . '</strong> ' . esc_html($invoice_id);
            echo '</div>';
        }
    }
    
    /**
     * Add meta box for GMPays payment details
     */
    public function add_gmpays_payment_meta_box() {
        add_meta_box(
            'gmpays_payment_details',
            __('GMPays Payment Details', 'gmpays-woocommerce-gateway'),
            array($this, 'render_payment_meta_box'),
            'shop_order',
            'side',
            'high'
        );
    }
    
    /**
     * Render payment details meta box
     */
    public function render_payment_meta_box($post) {
        $order = wc_get_order($post->ID);
        
        if ($order->get_payment_method() !== $this->id) {
            echo '<p>' . __('This order was not paid via GMPays.', 'gmpays-woocommerce-gateway') . '</p>';
            return;
        }
        
        $invoice_id = $order->get_meta('_gmpays_invoice_id');
        $payment_url = $order->get_meta('_gmpays_payment_url');
        $transaction_id = $order->get_meta('_gmpays_transaction_id');
        $payment_status = $order->get_meta('_gmpays_payment_status');
        
        echo '<div class="gmpays-payment-meta-box">';
        
        if ($invoice_id) {
            echo '<p><strong>' . __('Invoice ID:', 'gmpays-woocommerce-gateway') . '</strong><br>' . esc_html($invoice_id) . '</p>';
        }
        
        if ($transaction_id) {
            echo '<p><strong>' . __('Transaction ID:', 'gmpays-woocommerce-gateway') . '</strong><br>' . esc_html($transaction_id) . '</p>';
        }
        
        if ($payment_status) {
            echo '<p><strong>' . __('Payment Status:', 'gmpays-woocommerce-gateway') . '</strong><br>' . esc_html($payment_status) . '</p>';
        }
        
        if ($payment_url) {
            echo '<p><strong>' . __('Payment URL:', 'gmpays-woocommerce-gateway') . '</strong><br>';
            echo '<a href="' . esc_url($payment_url) . '" target="_blank">' . __('View Payment Page', 'gmpays-woocommerce-gateway') . '</a></p>';
        }
        
        // Add button to check payment status
        if ($invoice_id && $this->api_client) {
            echo '<p><button type="button" class="button" id="gmpays-check-status" data-invoice="' . esc_attr($invoice_id) . '" data-order="' . esc_attr($order->get_id()) . '">';
            echo __('Check Payment Status', 'gmpays-woocommerce-gateway') . '</button></p>';
            echo '<div id="gmpays-status-result"></div>';
            
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#gmpays-check-status').on('click', function() {
                    var button = $(this);
                    var invoiceId = button.data('invoice');
                    var orderId = button.data('order');
                    var resultDiv = $('#gmpays-status-result');
                    
                    button.prop('disabled', true);
                    resultDiv.html('<p><?php echo esc_js(__('Checking status...', 'gmpays-woocommerce-gateway')); ?></p>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'gmpays_check_status',
                            invoice_id: invoiceId,
                            order_id: orderId,
                            nonce: '<?php echo wp_create_nonce('gmpays_check_status'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                resultDiv.html('<p style="color: green;">' + response.data.message + '</p>');
                            } else {
                                resultDiv.html('<p style="color: red;">' + response.data + '</p>');
                            }
                        },
                        error: function() {
                            resultDiv.html('<p style="color: red;"><?php echo esc_js(__('Error checking status', 'gmpays-woocommerce-gateway')); ?></p>');
                        },
                        complete: function() {
                            button.prop('disabled', false);
                        }
                    });
                });
            });
            </script>
            <?php
        }
        
        echo '</div>';
    }
}