<?php
/**
 * GMPays Currency Manager Class
 *
 * Handles currency conversions for GMPays payment processing
 * Integrates with WooCommerce Multi Currency plugin
 *
 * @package GMPaysWooCommerceGateway
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GMPays_Currency_Manager Class
 */
class GMPays_Currency_Manager {
    
    /**
     * Debug mode flag
     */
    private $debug;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get debug setting from gateway options
        $gateway_settings = get_option('woocommerce_gmpays_credit_card_settings', array());
        $this->debug = isset($gateway_settings['debug']) && $gateway_settings['debug'] === 'yes';
    }
    
    /**
     * Convert order total to USD for GMPays processing
     *
     * @param WC_Order $order Order object
     * @return float Order total in USD
     */
    public function convert_to_usd($order) {
        $order_total = $order->get_total();
        $order_currency = $order->get_currency();
        
        if ($this->debug) {
            wc_get_logger()->debug('Converting order total - Original: ' . $order_total . ' ' . $order_currency, array('source' => 'gmpays-currency'));
        }
        
        // If already in USD, return as is
        if (strtoupper($order_currency) === 'USD') {
            return $order_total;
        }
        
        // Check if WooCommerce Multi Currency is active
        if (class_exists('WOOMULTI_CURRENCY_Data')) {
            return $this->convert_with_wmc($order_total, $order_currency, 'USD');
        }
        
        // If no currency converter available, throw exception
        throw new Exception(sprintf(
            __('Currency conversion from %s to USD is not available. Please install WooCommerce Multi Currency plugin or use USD as store currency.', 'gmpays-woocommerce-gateway'),
            $order_currency
        ));
    }
    
    /**
     * Convert order total to EUR for minimum amount validation
     *
     * @param WC_Cart $cart Cart object
     * @return float Cart total in EUR
     */
    public function convert_to_eur($cart) {
        $cart_total = $cart->get_total('edit');
        $store_currency = get_woocommerce_currency();
        
        if ($this->debug) {
            wc_get_logger()->debug('Converting cart total to EUR - Original: ' . $cart_total . ' ' . $store_currency, array('source' => 'gmpays-currency'));
        }
        
        // If already in EUR, return as is
        if (strtoupper($store_currency) === 'EUR') {
            return $cart_total;
        }
        
        // Check if WooCommerce Multi Currency is active
        if (class_exists('WOOMULTI_CURRENCY_Data')) {
            return $this->convert_with_wmc($cart_total, $store_currency, 'EUR');
        }
        
        // If no currency converter available, use a fallback rate (approximate)
        // This is not ideal but prevents the gateway from being completely unavailable
        $fallback_rates = array(
            'USD' => 0.85,  // USD to EUR (approximate)
            'COP' => 0.0002, // COP to EUR (approximate)
            'MXN' => 0.042, // MXN to EUR (approximate)
            'ARS' => 0.003, // ARS to EUR (approximate)
            'VES' => 0.0000001, // VES to EUR (approximate)
            'PEN' => 0.22, // PEN to EUR (approximate)
            'CLP' => 0.0011, // CLP to EUR (approximate)
            'BRL' => 0.16, // BRL to EUR (approximate)
            'UYU' => 0.021, // UYU to EUR (approximate)
        );
        
        if (isset($fallback_rates[$store_currency])) {
            $converted_amount = $cart_total * $fallback_rates[$store_currency];
            if ($this->debug) {
                wc_get_logger()->debug('Using fallback rate for EUR conversion: ' . $cart_total . ' ' . $store_currency . ' = ' . $converted_amount . ' EUR', array('source' => 'gmpays-currency'));
            }
            return $converted_amount;
        }
        
        // If we can't convert, assume it meets the minimum (better than blocking the gateway)
        if ($this->debug) {
            wc_get_logger()->warning('Could not convert ' . $store_currency . ' to EUR, assuming minimum amount requirement is met', array('source' => 'gmpays-currency'));
        }
        return 10.00; // Assume it meets minimum
    }
    
    /**
     * Convert amount using WooCommerce Multi Currency plugin
     *
     * @param float $amount Amount to convert
     * @param string $from_currency Source currency
     * @param string $to_currency Target currency
     * @return float Converted amount
     */
    private function convert_with_wmc($amount, $from_currency, $to_currency) {
        try {
            $wmc_settings = WOOMULTI_CURRENCY_Data::get_ins();
            $currencies = $wmc_settings->get_list_currencies();
            
            // Check if both currencies are configured
            if (!isset($currencies[$from_currency])) {
                throw new Exception(sprintf(
                    __('Currency %s is not configured in WooCommerce Multi Currency', 'gmpays-woocommerce-gateway'),
                    $from_currency
                ));
            }
            
            if (!isset($currencies[$to_currency])) {
                throw new Exception(sprintf(
                    __('Currency %s is not configured in WooCommerce Multi Currency', 'gmpays-woocommerce-gateway'),
                    $to_currency
                ));
            }
            
            // Get exchange rates
            $from_rate = floatval($currencies[$from_currency]['rate']);
            $to_rate = floatval($currencies[$to_currency]['rate']);
            
            if ($from_rate <= 0 || $to_rate <= 0) {
                throw new Exception(__('Invalid exchange rates configured', 'gmpays-woocommerce-gateway'));
            }
            
            // Convert to base currency first, then to target currency
            $base_amount = $amount / $from_rate;
            $converted_amount = $base_amount * $to_rate;
            
            if ($this->debug) {
                wc_get_logger()->debug(sprintf(
                    'WMC Conversion: %s %s = %s %s (Rates: %s=%s, %s=%s)',
                    $amount,
                    $from_currency,
                    $converted_amount,
                    $to_currency,
                    $from_currency,
                    $from_rate,
                    $to_currency,
                    $to_rate
                ), array('source' => 'gmpays-currency'));
            }
            
            return round($converted_amount, 2);
            
        } catch (Exception $e) {
            if ($this->debug) {
                wc_get_logger()->error('WMC Conversion error: ' . $e->getMessage(), array('source' => 'gmpays-currency'));
            }
            throw $e;
        }
    }
    
    /**
     * Get current currency from WooCommerce Multi Currency
     *
     * @return string Current currency code
     */
    public function get_current_currency() {
        if (class_exists('WOOMULTI_CURRENCY_Data')) {
            $wmc_settings = WOOMULTI_CURRENCY_Data::get_ins();
            return $wmc_settings->get_current_currency();
        }
        
        return get_woocommerce_currency();
    }
    
    /**
     * Get order currency with Multi Currency support
     *
     * @param WC_Order $order Order object
     * @return string Currency code
     */
    public function get_order_currency($order) {
        // First check if order has a specific currency set
        $order_currency = $order->get_currency();
        
        // If WooCommerce Multi Currency is active, check for current session currency
        if (class_exists('WOOMULTI_CURRENCY_Data')) {
            $wmc_settings = WOOMULTI_CURRENCY_Data::get_ins();
            $current_currency = $wmc_settings->get_current_currency();
            
            // Use the current currency if it's different from order currency
            // This handles cases where user selected a different currency after order was created
            if ($current_currency && $current_currency !== $order_currency) {
                return $current_currency;
            }
        }
        
        return $order_currency;
    }
    
    /**
     * Get exchange rate for a currency
     *
     * @param string $currency Currency code
     * @return float Exchange rate
     */
    public function get_currency_rate($currency = null) {
        if (!$currency) {
            $currency = get_woocommerce_currency();
        }
        
        if (!class_exists('WOOMULTI_CURRENCY_Data')) {
            return 1;
        }
        
        $wmc_settings = WOOMULTI_CURRENCY_Data::get_ins();
        $currencies = $wmc_settings->get_list_currencies();
        
        return isset($currencies[$currency]['rate']) ? floatval($currencies[$currency]['rate']) : 1;
    }
    
    /**
     * Format price with currency symbol
     *
     * @param float $amount Amount
     * @param string $currency Currency code
     * @return string Formatted price
     */
    public function format_price($amount, $currency = null) {
        if (!$currency) {
            $currency = get_woocommerce_currency();
        }
        
        return wc_price($amount, array('currency' => $currency));
    }
    
    /**
     * Get supported currencies for display
     *
     * @return array Array of supported currencies
     */
    public function get_supported_currencies() {
        $currencies = array(
            'USD' => __('US Dollar', 'gmpays-woocommerce-gateway'),
            'EUR' => __('Euro', 'gmpays-woocommerce-gateway'),
            'COP' => __('Colombian Peso', 'gmpays-woocommerce-gateway'),
            'MXN' => __('Mexican Peso', 'gmpays-woocommerce-gateway'),
            'ARS' => __('Argentine Peso', 'gmpays-woocommerce-gateway'),
            'VES' => __('Venezuelan Bolívar', 'gmpays-woocommerce-gateway'),
            'PEN' => __('Peruvian Sol', 'gmpays-woocommerce-gateway'),
            'CLP' => __('Chilean Peso', 'gmpays-woocommerce-gateway'),
            'BRL' => __('Brazilian Real', 'gmpays-woocommerce-gateway'),
            'UYU' => __('Uruguayan Peso', 'gmpays-woocommerce-gateway'),
        );
        
        return apply_filters('gmpays_supported_currencies', $currencies);
    }
    
    /**
     * Check if WooCommerce Multi Currency is properly configured for GMPays
     *
     * @return boolean|WP_Error True if configured properly, WP_Error otherwise
     */
    public function check_currency_configuration() {
        // Check if WMC is installed
        if (!class_exists('WOOMULTI_CURRENCY_Data')) {
            // If not installed, check if store currency is USD
            if (get_woocommerce_currency() !== 'USD') {
                return new WP_Error(
                    'currency_not_configured',
                    __('GMPays requires either USD as store currency or WooCommerce Multi Currency plugin for currency conversion.', 'gmpays-woocommerce-gateway')
                );
            }
            return true;
        }
        
        // Check if USD is configured in WMC
        $wmc_settings = WOOMULTI_CURRENCY_Data::get_ins();
        $currencies = $wmc_settings->get_list_currencies();
        
        if (!isset($currencies['USD'])) {
            return new WP_Error(
                'usd_not_configured',
                __('USD currency must be configured in WooCommerce Multi Currency for GMPays to work.', 'gmpays-woocommerce-gateway')
            );
        }
        
        // Check if USD rate is valid
        if (!isset($currencies['USD']['rate']) || floatval($currencies['USD']['rate']) <= 0) {
            return new WP_Error(
                'invalid_usd_rate',
                __('USD exchange rate is not properly configured in WooCommerce Multi Currency.', 'gmpays-woocommerce-gateway')
            );
        }
        
        return true;
    }
    
    /**
     * Convert amount from USD back to original currency (for refunds, etc.)
     *
     * @param float $amount Amount in USD
     * @param string $target_currency Target currency
     * @return float Converted amount
     */
    public function convert_from_usd($amount, $target_currency) {
        // If target is USD, return as is
        if (strtoupper($target_currency) === 'USD') {
            return $amount;
        }
        
        // Use WMC for conversion
        if (class_exists('WOOMULTI_CURRENCY_Data')) {
            return $this->convert_with_wmc($amount, 'USD', $target_currency);
        }
        
        // If no converter available, return original amount
        return $amount;
    }
}
