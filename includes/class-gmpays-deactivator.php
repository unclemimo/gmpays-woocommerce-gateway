<?php
/**
 * GMPays Plugin Deactivator Class
 *
 * Handles plugin deactivation and uninstall tasks
 *
 * @package GMPaysWooCommerceGateway
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GMPays_Deactivator Class
 */
class GMPays_Deactivator {
    
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear scheduled events
        self::clear_scheduled_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any transients
        delete_transient('gmpays_gateway_activated');
    }
    
    /**
     * Clear scheduled cron events
     */
    private static function clear_scheduled_events() {
        // Clear any scheduled events
        $timestamp = wp_next_scheduled('gmpays_check_pending_payments');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'gmpays_check_pending_payments');
        }
    }
    
    /**
     * Uninstall the plugin (called when plugin is deleted)
     */
    public static function uninstall() {
        // Only run if explicitly uninstalling
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Clear scheduled events
        self::clear_scheduled_events();
        
        // Optionally remove plugin settings (uncomment if you want to remove all data on uninstall)
        // self::remove_plugin_data();
    }
    
    /**
     * Remove all plugin data (optional - disabled by default)
     */
    private static function remove_plugin_data() {
        // Remove plugin settings
        delete_option('woocommerce_gmpays_credit_card_settings');
        delete_option('gmpays_gateway_version');
        
        // Remove any other gateway settings if you add more payment methods
        // delete_option('woocommerce_gmpays_pix_settings');
        // delete_option('woocommerce_gmpays_spei_settings');
        
        // Remove order meta data (be careful with this!)
        // This will remove all GMPays payment data from orders
        global $wpdb;
        
        // Remove GMPays meta data from orders
        $meta_keys = array(
            '_gmpays_invoice_id',
            '_gmpays_payment_url',
            '_gmpays_payment_method',
            '_gmpays_transaction_id',
            '_gmpays_payment_status',
            '_gmpays_payment_completed_at',
            '_gmpays_payment_failed_at',
            '_gmpays_payment_cancelled_at',
            '_gmpays_payment_failure_reason',
            '_gmpays_refund_id',
            '_gmpays_refund_processed_at',
            '_gmpays_card_last4',
            '_gmpays_card_brand',
            '_gmpays_payment_method_used',
        );
        
        foreach ($meta_keys as $meta_key) {
            $wpdb->delete(
                $wpdb->postmeta,
                array('meta_key' => $meta_key),
                array('%s')
            );
        }
    }
}
