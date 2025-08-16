<?php
/**
 * GMPays Credit Card Payment Gateway Class
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
    
    /**
     * GMPays API client
     */
    private $api_client;
    
    /**
     * Currency manager
     */
    private $currency_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'gmpays_credit_card';
        $this->icon               = apply_filters('woocommerce_gmpays_credit_card_icon', GMPAYS_WC_GATEWAY_PLUGIN_URL . 'assets/images/credit-cards.png');
        $this->has_fields         = false;
        $this->method_title       = __('Credit Card (GMPays)', 'gmpays-woocommerce-gateway');
        $this->method_description = __('Accept international credit card payments via GMPays payment processor.', 'gmpays-woocommerce-gateway');
        $this->supports           = array(
            'products',
            'refunds',
        );
        
        // Load settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user settings
        $this->title              = $this->get_option('title', __('Credit Card (GMPays)', 'gmpays-woocommerce-gateway'));
        $this->description        = $this->get_option('description', __('Pay securely with your credit card', 'gmpays-woocommerce-gateway'));
        $this->enabled            = $this->get_option('enabled');
        $this->project_id         = $this->get_option('project_id');
        $this->hmac_key           = $this->get_option('hmac_key');
        $this->webhook_secret     = $this->get_option('webhook_secret', $this->hmac_key); // Use HMAC key as default for webhooks
        
        // Initialize API client and currency manager
        $this->api_client = new GMPays_API_Client($this->project_id, $this->hmac_key);
        $this->currency_manager = new GMPays_Currency_Manager();
        
        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_response'));
        
        // Add admin order page hooks
        add_action('woocommerce_admin_order_data_after_payment_info', array($this, 'display_gmpays_payment_details'));
        add_action('add_meta_boxes', array($this, 'add_gmpays_payment_meta_box'));
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
                'default'     => __('Credit Card (GMPays)', 'gmpays-woocommerce-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'gmpays-woocommerce-gateway'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'gmpays-woocommerce-gateway'),
                'default'     => __('Pay securely using your credit card through GMPays secure payment gateway.', 'gmpays-woocommerce-gateway'),
                'desc_tip'    => true,
            ),
            'api_credentials_title' => array(
                'title'       => __('GMPays API Credentials', 'gmpays-woocommerce-gateway'),
                'type'        => 'title',
                'description' => __('Enter your GMPays credentials from your control panel at cp.gmpays.com', 'gmpays-woocommerce-gateway'),
            ),
            'project_id' => array(
                'title'       => __('Project ID', 'gmpays-woocommerce-gateway'),
                'type'        => 'text',
                'description' => __('Your GMPays Project ID (shown as "ID IN PROJECT" in your GMPays control panel). For example: 603', 'gmpays-woocommerce-gateway'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => '603',
            ),
            'hmac_key' => array(
                'title'       => __('HMAC Key', 'gmpays-woocommerce-gateway'),
                'type'        => 'password',
                'description' => __('Your GMPays HMAC Key. Generate this by clicking "Regenerate HMAC Key" in your GMPays control panel.', 'gmpays-woocommerce-gateway'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => '',
            ),
            'webhook_configuration' => array(
                'title'       => __('Webhook Configuration', 'gmpays-woocommerce-gateway'),
                'type'        => 'title',
                'description' => sprintf(
                    __('Configure the following URLs in your GMPays control panel:<br>
                    • Success URL: %s<br>
                    • Failure URL: %s<br>
                    • Notification URL: %s', 'gmpays-woocommerce-gateway'),
                    '<code>' . home_url('/checkout/order-received/') . '</code>',
                    '<code>' . wc_get_checkout_url() . '</code>',
                    '<code>' . home_url('/wp-json/gmpays/v1/webhook') . '</code>'
                ),
            ),
            'webhook_secret' => array(
                'title'       => __('Webhook Secret (Optional)', 'gmpays-woocommerce-gateway'),
                'type'        => 'password',
                'description' => __('If you want to use a different secret for webhook verification than your HMAC key, enter it here. Otherwise, leave blank to use HMAC key.', 'gmpays-woocommerce-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'debug' => array(
                'title'       => __('Debug Log', 'gmpays-woocommerce-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'gmpays-woocommerce-gateway'),
                'default'     => 'no',
                'description' => sprintf(
                    __('Log GMPays events inside %s', 'gmpays-woocommerce-gateway'),
                    '<code>' . WC_Log_Handler_File::get_log_file_path('gmpays') . '</code>'
                ),
            ),
        );
    }
    
    /**
     * Admin Panel Options
     */
    public function admin_options() {
        echo '<h3>' . esc_html__('GMPays Credit Card Gateway', 'gmpays-woocommerce-gateway') . '</h3>';
        echo '<p>' . esc_html__('Accept international credit card payments via GMPays payment processor.', 'gmpays-woocommerce-gateway') . '</p>';
        
        if (!$this->is_valid_for_use()) {
            echo '<div class="notice notice-error"><p>' . esc_html__('GMPays requires WooCommerce Multi Currency plugin for proper currency conversion to USD.', 'gmpays-woocommerce-gateway') . '</p></div>';
        }
        
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }
    
    /**
     * Check if gateway is available
     */
    public function is_available() {
        if ('yes' !== $this->enabled) {
            return false;
        }
        
        if (!$this->project_id || !$this->hmac_key) {
            return false;
        }
        
        return $this->is_valid_for_use();
    }
    
    /**
     * Check if gateway is valid for use
     */
    public function is_valid_for_use() {
        // GMPays processes in USD, so we need currency conversion capability
        if (!class_exists('WOOMULTI_CURRENCY_Data')) {
            // If WooCommerce Multi Currency is not installed, only allow USD
            $current_currency = get_woocommerce_currency();
            return $current_currency === 'USD';
        }
        
        // If WMC is installed, check if USD is configured
        $wmc_settings = WOOMULTI_CURRENCY_Data::get_ins();
        $currencies = $wmc_settings->get_list_currencies();
        
        return isset($currencies['USD']);
    }
    
    /**
     * Process the payment
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if ('yes' === $this->get_option('debug')) {
            wc_get_logger()->debug('GMPays process_payment started for order ID: ' . $order_id, array('source' => 'gmpays-gateway'));
        }
        
        try {
            // Prepare order data
            $order_data = $this->prepare_order_data($order);
            
            // Create payment invoice via GMPays API
            $response = $this->api_client->create_invoice($order_data);
            
            if ('yes' === $this->get_option('debug')) {
                wc_get_logger()->debug('GMPays API response: ' . print_r($response, true), array('source' => 'gmpays-gateway'));
            }
            
            if (!$response || !isset($response['invoice_id'])) {
                throw new Exception(__('Unable to create payment session with GMPays.', 'gmpays-woocommerce-gateway'));
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
            wc_add_notice(__('Payment error:', 'gmpays-woocommerce-gateway') . ' ' . $e->getMessage(), 'error');
            
            if ('yes' === $this->get_option('debug')) {
                wc_get_logger()->error('GMPays payment error: ' . $e->getMessage(), array('source' => 'gmpays-gateway'));
            }
            
            return array(
                'result' => 'fail',
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
        
        try {
            $response = $this->api_client->refund_payment($invoice_id, $amount, $reason);
            
            if ($response && isset($response['success']) && $response['success']) {
                $order->add_order_note(sprintf(
                    __('Refunded %s via GMPays. Reason: %s', 'gmpays-woocommerce-gateway'),
                    wc_price($amount),
                    $reason ?: __('No reason provided', 'gmpays-woocommerce-gateway')
                ));
                
                return true;
            } else {
                return new WP_Error('refund_failed', __('Refund failed. Please try again or contact GMPays support.', 'gmpays-woocommerce-gateway'));
            }
            
        } catch (Exception $e) {
            return new WP_Error('refund_error', $e->getMessage());
        }
    }
    
    /**
     * Prepare order data for GMPays API
     */
    private function prepare_order_data($order) {
        // Get order total in USD
        $order_total_usd = $this->currency_manager->convert_to_usd($order);
        
        if ('yes' === $this->get_option('debug')) {
            wc_get_logger()->debug('Order total in USD: ' . $order_total_usd, array('source' => 'gmpays-gateway'));
        }
        
        // Prepare invoice data for GMPays
        $invoice_data = array(
            'project_id' => $this->project_id,
            'amount' => $order_total_usd,
            'currency' => 'USD',
            'order_id' => $order->get_id(),
            'order_key' => $order->get_order_key(),
            'description' => $this->get_order_description($order),
            'customer' => array(
                'email' => $order->get_billing_email(),
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'phone' => $order->get_billing_phone(),
                'address' => array(
                    'line1' => $order->get_billing_address_1(),
                    'line2' => $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'postal_code' => $order->get_billing_postcode(),
                    'country' => $order->get_billing_country(),
                ),
            ),
            'success_url' => $this->get_return_url($order),
            'cancel_url' => wc_get_checkout_url(),
            'callback_url' => home_url('/wp-json/gmpays/v1/webhook'),
            'payment_method' => 'credit_card',
            'metadata' => array(
                'wc_order_id' => $order->get_id(),
                'wc_order_key' => $order->get_order_key(),
                'source' => 'woocommerce',
                'store_name' => get_bloginfo('name'),
                'original_currency' => $order->get_currency(),
                'original_amount' => $order->get_total(),
            ),
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
        return sprintf(
            __('Order #%s from %s: %s', 'gmpays-woocommerce-gateway'),
            $order->get_order_number(),
            get_bloginfo('name'),
            implode(', ', $items)
        );
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
     * Add GMPays payment meta box
     */
    public function add_gmpays_payment_meta_box($post_type) {
        if ($post_type === 'shop_order') {
            add_meta_box(
                'gmpays-payment-details',
                __('GMPays Payment Details', 'gmpays-woocommerce-gateway'),
                array($this, 'render_gmpays_payment_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    /**
     * Render GMPays payment meta box
     */
    public function render_gmpays_payment_meta_box($post) {
        $order = wc_get_order($post->ID);
        
        if (!$order || $order->get_payment_method() !== 'gmpays_credit_card') {
            echo '<p>' . __('This order was not paid with GMPays Credit Card', 'gmpays-woocommerce-gateway') . '</p>';
            return;
        }
        
        // Get GMPays metadata
        $invoice_id = $order->get_meta('_gmpays_invoice_id');
        $payment_url = $order->get_meta('_gmpays_payment_url');
        $payment_status = $order->get_meta('_gmpays_payment_status');
        $transaction_id = $order->get_meta('_gmpays_transaction_id');
        $payment_completed_at = $order->get_meta('_gmpays_payment_completed_at');
        
        echo '<div class="gmpays-payment-meta-box">';
        
        if ($invoice_id) {
            echo '<p><strong>' . __('Invoice ID:', 'gmpays-woocommerce-gateway') . '</strong><br>' . esc_html($invoice_id) . '</p>';
        }
        
        if ($transaction_id) {
            echo '<p><strong>' . __('Transaction ID:', 'gmpays-woocommerce-gateway') . '</strong><br>' . esc_html($transaction_id) . '</p>';
        }
        
        if ($payment_status) {
            echo '<p><strong>' . __('Payment Status:', 'gmpays-woocommerce-gateway') . '</strong><br>' . esc_html(ucfirst($payment_status)) . '</p>';
        }
        
        if ($payment_completed_at) {
            echo '<p><strong>' . __('Completed At:', 'gmpays-woocommerce-gateway') . '</strong><br>';
            echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment_completed_at))) . '</p>';
        }
        
        if ($payment_url) {
            echo '<p><strong>' . __('Payment URL:', 'gmpays-woocommerce-gateway') . '</strong><br>';
            echo '<a href="' . esc_url($payment_url) . '" target="_blank">' . __('View Payment', 'gmpays-woocommerce-gateway') . '</a></p>';
        }
        
        // Environment indicator
        $testmode = 'yes' === $this->get_option('testmode', 'no');
        echo '<p><strong>' . __('Environment:', 'gmpays-woocommerce-gateway') . '</strong><br>';
        echo ($testmode ? __('Test', 'gmpays-woocommerce-gateway') : __('Live', 'gmpays-woocommerce-gateway')) . '</p>';
        
        echo '</div>';
    }
}
