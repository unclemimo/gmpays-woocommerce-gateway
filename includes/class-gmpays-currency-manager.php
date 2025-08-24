<?php
/**
 * GMPays Currency Manager Class - Clean Implementation
 *
 * Handles currency conversions and multi-currency support for GMPays payments
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
    
    /** @var array Supported currencies for GMPays */
    private $supported_currencies = array(
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'COP' => 'Colombian Peso',
        'MXN' => 'Mexican Peso',
        'ARS' => 'Argentine Peso',
        'VES' => 'Venezuelan Bolivar',
        'PEN' => 'Peruvian Sol',
        'CLP' => 'Chilean Peso',
        'BRL' => 'Brazilian Real',
        'UYU' => 'Uruguayan Peso'
    );
    
    /** @var string Default currency for GMPays */
    private $default_currency = 'USD';
    
    /** @var array Currency exchange rates (cached) */
    private $exchange_rates = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize exchange rates
        $this->init_exchange_rates();
    }
    
    /**
     * Initialize exchange rates
     */
    private function init_exchange_rates() {
        // Try to get rates from WooCommerce Multi Currency plugin
        if (class_exists('WOOMULTI_CURRENCY_Data')) {
            try {
                $wmc_settings = WOOMULTI_CURRENCY_Data::get_ins();
                $currencies = $wmc_settings->get_list_currencies();
                
                if (!empty($currencies)) {
                    foreach ($currencies as $currency_code => $currency_data) {
                        if (isset($currency_data['rate']) && is_numeric($currency_data['rate'])) {
                            $this->exchange_rates[$currency_code] = floatval($currency_data['rate']);
                        }
                    }
                }
            } catch (Exception $e) {
                // Log error but continue with default rates
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('GMPays: Error getting WMC exchange rates: ' . $e->getMessage());
                }
            }
        }
        
        // Set default USD rate
        $this->exchange_rates['USD'] = 1.0;
        
        // Set fallback rates for supported currencies if not available from WMC
        $fallback_rates = array(
            'EUR' => 0.85,
            'COP' => 3800.0,
            'MXN' => 20.0,
            'ARS' => 100.0,
            'VES' => 3000000.0,
            'PEN' => 3.7,
            'CLP' => 800.0,
            'BRL' => 5.0,
            'UYU' => 40.0
        );
        
        foreach ($fallback_rates as $currency => $rate) {
            if (!isset($this->exchange_rates[$currency])) {
                $this->exchange_rates[$currency] = $rate;
            }
        }
    }
    
    /**
     * Convert order total to USD for GMPays processing
     *
     * @param WC_Order $order WooCommerce order
     * @return float Amount in USD
     */
    public function convert_to_usd($order) {
        $order_currency = $order->get_currency();
        $order_total = $order->get_total();
        
        // If already in USD, return as is
        if ($order_currency === 'USD') {
            return $order_total;
        }
        
        // Try to get rate from WooCommerce Multi Currency
        $rate = $this->get_exchange_rate($order_currency, 'USD');
        
        if ($rate > 0) {
            $usd_amount = $order_total / $rate;
            return round($usd_amount, 2);
        }
        
        // Fallback: use order total as is (this should not happen in production)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GMPays: Could not convert ' . $order_currency . ' to USD for order ' . $order->get_id());
        }
        
        return $order_total;
    }
    
    /**
     * Convert order total to EUR for minimum amount validation
     *
     * @param WC_Order $order WooCommerce order
     * @return float Amount in EUR
     */
    public function convert_to_eur($order) {
        $order_currency = $order->get_currency();
        $order_total = $order->get_total();
        
        // If already in EUR, return as is
        if ($order_currency === 'EUR') {
            return $order_total;
        }
        
        // Try to get rate from WooCommerce Multi Currency
        $rate = $this->get_exchange_rate($order_currency, 'EUR');
        
        if ($rate > 0) {
            $eur_amount = $order_total / $rate;
            return round($eur_amount, 2);
        }
        
        // Fallback: convert via USD
        $usd_amount = $this->convert_to_usd($order);
        $usd_to_eur_rate = $this->get_exchange_rate('USD', 'EUR');
        
        if ($usd_to_eur_rate > 0) {
            $eur_amount = $usd_amount * $usd_to_eur_rate;
            return round($eur_amount, 2);
        }
        
        // Final fallback: use order total as is
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GMPays: Could not convert ' . $order_currency . ' to EUR for order ' . $order->get_id());
        }
        
        return $order_total;
    }
    
    /**
     * Convert cart total to USD for GMPays processing
     *
     * @param WC_Cart $cart WooCommerce cart
     * @return float Amount in USD
     */
    public function convert_cart_to_usd($cart) {
        $cart_total = $cart->get_total('edit');
        $store_currency = get_woocommerce_currency();
        
        // If already in USD, return as is
        if ($store_currency === 'USD') {
            return $cart_total;
        }
        
        // Try to get rate from WooCommerce Multi Currency
        $rate = $this->get_exchange_rate($store_currency, 'USD');
        
        if ($rate > 0) {
            $usd_amount = $cart_total / $rate;
            return round($usd_amount, 2);
        }
        
        // Fallback: use cart total as is
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GMPays: Could not convert cart total from ' . $store_currency . ' to USD');
        }
        
        return $cart_total;
    }
    
    /**
     * Convert cart total to EUR for minimum amount validation
     *
     * @param WC_Cart $cart WooCommerce cart
     * @return float Amount in EUR
     */
    public function convert_cart_to_eur($cart) {
        $cart_total = $cart->get_total('edit');
        $store_currency = get_woocommerce_currency();
        
        // If already in EUR, return as is
        if ($store_currency === 'EUR') {
            return $cart_total;
        }
        
        // Try to get rate from WooCommerce Multi Currency
        $rate = $this->get_exchange_rate($store_currency, 'EUR');
        
        if ($rate > 0) {
            $eur_amount = $cart_total / $rate;
            return round($eur_amount, 2);
        }
        
        // Fallback: convert via USD
        $usd_amount = $this->convert_cart_to_usd($cart);
        $usd_to_eur_rate = $this->get_exchange_rate('USD', 'EUR');
        
        if ($usd_to_eur_rate > 0) {
            $eur_amount = $usd_amount * $usd_to_eur_rate;
            return round($eur_amount, 2);
        }
        
        // Final fallback: use cart total as is
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GMPays: Could not convert cart total from ' . $store_currency . ' to EUR');
        }
        
        return $cart_total;
    }
    
    /**
     * Get exchange rate between two currencies
     *
     * @param string $from_currency Source currency
     * @param string $to_currency Target currency
     * @return float Exchange rate
     */
    public function get_exchange_rate($from_currency, $to_currency) {
        // If same currency, return 1
        if ($from_currency === $to_currency) {
            return 1.0;
        }
        
        // If we have direct rates for both currencies
        if (isset($this->exchange_rates[$from_currency]) && isset($this->exchange_rates[$to_currency])) {
            $from_rate = $this->exchange_rates[$from_currency];
            $to_rate = $this->exchange_rates[$to_currency];
            
            if ($from_rate > 0 && $to_rate > 0) {
                return $to_rate / $from_rate;
            }
        }
        
        // Try to get rate from WooCommerce Multi Currency
        if (class_exists('WOOMULTI_CURRENCY_Data')) {
            try {
                $wmc_settings = WOOMULTI_CURRENCY_Data::get_ins();
                $currencies = $wmc_settings->get_list_currencies();
                
                if (isset($currencies[$from_currency]['rate']) && isset($currencies[$to_currency]['rate'])) {
                    $from_rate = floatval($currencies[$from_currency]['rate']);
                    $to_rate = floatval($currencies[$to_currency]['rate']);
                    
                    if ($from_rate > 0 && $to_rate > 0) {
                        return $to_rate / $from_rate;
                    }
                }
            } catch (Exception $e) {
                // Log error but continue
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('GMPays: Error getting WMC exchange rate: ' . $e->getMessage());
                }
            }
        }
        
        // Return 0 if no rate found
        return 0.0;
    }
    
    /**
     * Check if a currency is supported by GMPays
     *
     * @param string $currency Currency code
     * @return bool True if supported
     */
    public function is_currency_supported($currency) {
        return isset($this->supported_currencies[strtoupper($currency)]);
    }
    
    /**
     * Get list of supported currencies
     *
     * @return array Supported currencies
     */
    public function get_supported_currencies() {
        return $this->supported_currencies;
    }
    
    /**
     * Get default currency for GMPays
     *
     * @return string Default currency code
     */
    public function get_default_currency() {
        return $this->default_currency;
    }
    
    /**
     * Format amount for display
     *
     * @param float $amount Amount to format
     * @param string $currency Currency code
     * @return string Formatted amount
     */
    public function format_amount($amount, $currency = 'USD') {
        $currency_symbol = $this->get_currency_symbol($currency);
        $formatted_amount = number_format($amount, 2);
        
        return $currency_symbol . $formatted_amount;
    }
    
    /**
     * Get currency symbol
     *
     * @param string $currency Currency code
     * @return string Currency symbol
     */
    public function get_currency_symbol($currency) {
        $symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'COP' => '$',
            'MXN' => '$',
            'ARS' => '$',
            'VES' => 'Bs.',
            'PEN' => 'S/',
            'CLP' => '$',
            'BRL' => 'R$',
            'UYU' => '$'
        );
        
        return isset($symbols[strtoupper($currency)]) ? $symbols[strtoupper($currency)] : strtoupper($currency);
    }
    
    /**
     * Validate minimum amount in EUR
     *
     * @param float $amount Amount to validate
     * @param string $currency Currency of the amount
     * @param float $minimum_eur Minimum amount in EUR
     * @return bool True if amount meets minimum
     */
    public function validate_minimum_amount($amount, $currency, $minimum_eur) {
        // Convert amount to EUR
        $amount_eur = $this->convert_amount($amount, $currency, 'EUR');
        
        return $amount_eur >= $minimum_eur;
    }
    
    /**
     * Convert amount between currencies
     *
     * @param float $amount Amount to convert
     * @param string $from_currency Source currency
     * @param string $to_currency Target currency
     * @return float Converted amount
     */
    public function convert_amount($amount, $from_currency, $to_currency) {
        $rate = $this->get_exchange_rate($from_currency, $to_currency);
        
        if ($rate > 0) {
            return round($amount * $rate, 2);
        }
        
        // Return original amount if conversion fails
        return $amount;
    }
    
    /**
     * Get current exchange rates (for debugging)
     *
     * @return array Exchange rates
     */
    public function get_current_rates() {
        return $this->exchange_rates;
    }
    
    /**
     * Update exchange rates from external source
     *
     * @return bool True if rates updated successfully
     */
    public function update_exchange_rates() {
        // This method can be extended to fetch rates from external APIs
        // For now, we rely on WooCommerce Multi Currency plugin
        
        if (class_exists('WOOMULTI_CURRENCY_Data')) {
            try {
                $wmc_settings = WOOMULTI_CURRENCY_Data::get_ins();
                $currencies = $wmc_settings->get_list_currencies();
                
                if (!empty($currencies)) {
                    foreach ($currencies as $currency_code => $currency_data) {
                        if (isset($currency_data['rate']) && is_numeric($currency_data['rate'])) {
                            $this->exchange_rates[$currency_code] = floatval($currency_data['rate']);
                        }
                    }
                    
                    return true;
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('GMPays: Error updating exchange rates: ' . $e->getMessage());
                }
            }
        }
        
        return false;
    }
}
