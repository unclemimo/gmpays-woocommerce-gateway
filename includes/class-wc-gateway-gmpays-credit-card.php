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
        $this->method_description = __('Accept international credit card payments via GMPays payment processor using RSA signatures.', 'gmpays-woocommerce-gateway');
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
        $this->private_key        = $this->get_option('private_key');
        
        // Initialize API client with RSA private key and API URL
        if (!empty($this->project_id) && !empty($this->private_key)) {
            $this->api_client = new GMPays_API_Client($this->project_id, $this->private_key, $this->api_url);
        }
        
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
            'webhook_configuration' => array(
                'title'       => __('Webhook Configuration', 'gmpays-woocommerce-gateway'),
                'type'        => 'title',
                'description' => sprintf(
                    __('Configure these URLs in your GMPays control panel:', 'gmpays-woocommerce-gateway') . 
                    '<br><strong>Notification URL (URL для оповещений о выплатах):</strong> <code>%s</code>' .
                    '<br><strong>Success URL (URL перенаправления пользователя в случае успешной оплаты):</strong> <code>%s</code>' .
                    '<br><strong>Failure URL (URL перенаправления пользователя в случае неуспешной оплаты):</strong> <code>%s</code>',
                    home_url('/wp-json/gmpays/v1/webhook'),
                    wc_get_checkout_url() . 'order-received/',
                    wc_get_checkout_url()
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
        
        return $this->is_valid_for_use();
    }
    
    /**
     * Check if this gateway is properly configured
     * 
     * @return bool
     */
    public function is_configured() {
        return !empty($this->project_id) && !empty($this->private_key);
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
        
        // Prepare invoice data for GMPays API
        $invoice_data = array(
            'amount' => $order_total_usd,
            'currency' => 'USD',
            'order_id' => $order->get_id(),
            'description' => $this->get_order_description($order),
            'customer_email' => $customer_email,
            'customer_name' => trim($customer_name),
            'customer_ip' => $order->get_customer_ip_address(),
            'return_url' => $this->get_return_url($order),
            'cancel_url' => wc_get_checkout_url(),
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