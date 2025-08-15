<?php
/**
 * GMPays Plugin Activator Class
 *
 * Handles plugin activation tasks
 *
 * @package GMPaysWooCommerceGateway
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GMPays_Activator Class
 */
class GMPays_Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(GMPAYS_WC_GATEWAY_PLUGIN_BASENAME);
            wp_die(sprintf(
                __('GMPays WooCommerce Gateway requires PHP version 7.4 or higher. Your current version is %s.', 'gmpays-woocommerce-gateway'),
                PHP_VERSION
            ));
        }
        
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(GMPAYS_WC_GATEWAY_PLUGIN_BASENAME);
            wp_die(sprintf(
                __('GMPays WooCommerce Gateway requires WooCommerce to be installed and active. %s', 'gmpays-woocommerce-gateway'),
                '<a href="' . admin_url('plugin-install.php?s=woocommerce&tab=search&type=term') . '">' . __('Install WooCommerce', 'gmpays-woocommerce-gateway') . '</a>'
            ));
        }
        
        // Create default settings
        self::create_default_settings();
        
        // Schedule events if needed
        self::schedule_events();
        
        // Flush rewrite rules for REST API endpoint
        flush_rewrite_rules();
        
        // Set activation flag
        set_transient('gmpays_gateway_activated', true, 30);
    }
    
    /**
     * Create default plugin settings
     */
    private static function create_default_settings() {
        // Get existing settings
        $settings = get_option('woocommerce_gmpays_credit_card_settings', array());
        
        // Set default values if not exists
        $defaults = array(
            'enabled' => 'yes',
            'title' => __('Credit Card', 'gmpays-woocommerce-gateway'),
            'description' => __('Pay securely using your credit card through GMPays secure payment gateway.', 'gmpays-woocommerce-gateway'),
            'testmode' => 'yes',
            'test_payment_page_url' => 'https://checkout.pay.gmpays.com',
            'live_payment_page_url' => 'https://checkout.gmpays.com',
            'payment_action' => 'sale',
            'debug' => 'no',
        );
        
        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }
        
        // Save settings
        update_option('woocommerce_gmpays_credit_card_settings', $settings);
        
        // Add plugin version
        update_option('gmpays_gateway_version', GMPAYS_WC_GATEWAY_VERSION);
    }
    
    /**
     * Schedule cron events if needed
     */
    private static function schedule_events() {
        // Schedule any recurring tasks if needed
        // For example, checking payment status for pending orders
        if (!wp_next_scheduled('gmpays_check_pending_payments')) {
            wp_schedule_event(time(), 'hourly', 'gmpays_check_pending_payments');
        }
    }
}
