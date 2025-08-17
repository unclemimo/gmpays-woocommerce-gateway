<?php
/**
 * Plugin Name: GMPays WooCommerce Payment Gateway
 * Plugin URI: https://elgrupito.com/
 * Description: Accept credit card payments via GMPays - International payment processor with support for multiple currencies, dual authentication (HMAC/RSA), enhanced order management, automatic status updates, minimum amount validation, failed payment handling, and comprehensive return URL management.
 * Version: 1.4.0
 * Author: ElGrupito Development Team
 * Author URI: https://elgrupito.com/
 * Text Domain: gmpays-woocommerce-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GMPAYS_WC_GATEWAY_VERSION', '1.4.0');
define('GMPAYS_WC_GATEWAY_PLUGIN_FILE', __FILE__);
define('GMPAYS_WC_GATEWAY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GMPAYS_WC_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GMPAYS_WC_GATEWAY_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load Composer autoloader if available
if (file_exists(GMPAYS_WC_GATEWAY_PLUGIN_PATH . 'vendor/autoload.php')) {
    require_once GMPAYS_WC_GATEWAY_PLUGIN_PATH . 'vendor/autoload.php';
}

// Load activator and deactivator classes early for activation hooks
require_once GMPAYS_WC_GATEWAY_PLUGIN_PATH . 'includes/class-gmpays-activator.php';
require_once GMPAYS_WC_GATEWAY_PLUGIN_PATH . 'includes/class-gmpays-deactivator.php';

/**
 * Main plugin class
 */
class GMPaysWooCommerceGateway {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance of the class
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load plugin on plugins_loaded
        add_action('plugins_loaded', array($this, 'init'));
        
        // Load text domain
        add_action('init', array($this, 'load_textdomain'));
        
        // Add plugin action links
        add_filter('plugin_action_links_' . GMPAYS_WC_GATEWAY_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
        
        // Handle any uncaught errors
        add_action('shutdown', array($this, 'handle_shutdown_errors'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        try {
            // Check if WooCommerce is active
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
                return;
            }
            
            // Check PHP version
            if (version_compare(PHP_VERSION, '7.4', '<')) {
                add_action('admin_notices', array($this, 'php_version_notice'));
                return;
            }
            
            // Check if Composer dependencies are installed
            if (!file_exists(GMPAYS_WC_GATEWAY_PLUGIN_PATH . 'vendor/autoload.php')) {
                add_action('admin_notices', array($this, 'composer_missing_notice'));
                return;
            }
            
            // Include required files
            $this->includes();
            
            // Add payment gateways
            add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
            
            // Register webhook endpoint
            add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
            
            // Enqueue scripts
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            
        } catch (Exception $e) {
            $this->log_error('Plugin initialization failed: ' . $e->getMessage());
            add_action('admin_notices', array($this, 'initialization_error_notice'));
        }
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Include utility classes
        require_once GMPAYS_WC_GATEWAY_PLUGIN_PATH . 'includes/class-gmpays-api-client.php';
        require_once GMPAYS_WC_GATEWAY_PLUGIN_PATH . 'includes/class-gmpays-currency-manager.php';
        require_once GMPAYS_WC_GATEWAY_PLUGIN_PATH . 'includes/class-gmpays-webhook-handler.php';
        
        // Include payment gateway classes
        require_once GMPAYS_WC_GATEWAY_PLUGIN_PATH . 'includes/class-wc-gateway-gmpays-credit-card.php';
        // Future: Add more payment method classes here
        // require_once GMPAYS_WC_GATEWAY_PLUGIN_PATH . 'includes/class-wc-gateway-gmpays-pix.php';
        // require_once GMPAYS_WC_GATEWAY_PLUGIN_PATH . 'includes/class-wc-gateway-gmpays-spei.php';
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('gmpays-woocommerce-gateway', false, dirname(GMPAYS_WC_GATEWAY_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Add payment gateways to WooCommerce
     */
    public function add_gateways($gateways) {
        $gateways[] = 'WC_Gateway_GMPays_Credit_Card';
        // Future: Add more payment methods
        // $gateways[] = 'WC_Gateway_GMPays_PIX';
        // $gateways[] = 'WC_Gateway_GMPays_SPEI';
        // $gateways[] = 'WC_Gateway_GMPays_PSE';
        return $gateways;
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        if (is_checkout()) {
            wp_enqueue_script(
                'gmpays-checkout',
                GMPAYS_WC_GATEWAY_PLUGIN_URL . 'assets/js/gmpays-checkout.js',
                array('jquery'),
                GMPAYS_WC_GATEWAY_VERSION,
                true
            );
            
            wp_localize_script('gmpays-checkout', 'gmpays_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gmpays_checkout_nonce'),
            ));
        }
    }
    
    /**
     * Register webhook endpoint for GMPays notifications
     */
    public function register_webhook_endpoint() {
        register_rest_route('gmpays/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array('GMPays_Webhook_Handler', 'handle_webhook'),
            'permission_callback' => '__return_true',
        ));
        
        // Note: Return handling is now managed by the gateway class methods
        // to avoid conflicts and ensure proper order processing
    }
    
    // Note: Payment return handling methods have been moved to the gateway class
    // to ensure proper order processing and avoid conflicts
    
    // Note: Payment failure handling is now managed by the gateway class
    
    // Note: Payment cancellation handling is now managed by the gateway class
    
    /**
     * Add plugin action links
     */
    public function plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-settings&tab=checkout&section=gmpays_credit_card'),
            __('Settings', 'gmpays-woocommerce-gateway')
        );
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Display WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . sprintf(
            __('GMPays WooCommerce Gateway requires WooCommerce to be installed and active. You can download %s here.', 'gmpays-woocommerce-gateway'),
            '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
        ) . '</strong></p></div>';
    }
    
    /**
     * Display PHP version notice
     */
    public function php_version_notice() {
        echo '<div class="error"><p><strong>' . sprintf(
            __('GMPays WooCommerce Gateway requires PHP version 7.4 or higher. Your current version is %s.', 'gmpays-woocommerce-gateway'),
            PHP_VERSION
        ) . '</strong></p></div>';
    }
    
    /**
     * Display Composer missing notice
     */
    public function composer_missing_notice() {
        echo '<div class="error"><p><strong>' . 
            __('GMPays WooCommerce Gateway requires Composer dependencies to be installed. Please run "composer install" in the plugin directory.', 'gmpays-woocommerce-gateway') . 
        '</strong></p></div>';
    }
    
    /**
     * Display initialization error notice
     */
    public function initialization_error_notice() {
        echo '<div class="error"><p><strong>' . 
            __('GMPays WooCommerce Gateway encountered an error during initialization. Please check the error logs for more details.', 'gmpays-woocommerce-gateway') . 
        '</strong></p></div>';
    }
    
    /**
     * Handle shutdown errors
     */
    public function handle_shutdown_errors() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            // Check if the error is related to our plugin
            if (strpos($error['file'], 'gmpays-woocommerce-gateway') !== false) {
                $this->log_error('Fatal error in GMPays plugin: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
            }
        }
    }
    
    /**
     * Log error messages
     */
    private function log_error($message) {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->error($message, array('source' => 'gmpays-gateway'));
        } else {
            error_log('[GMPays Gateway] ' . $message);
        }
    }
}

/**
 * Main function to return plugin instance
 */
function GMPaysWooCommerceGateway() {
    return GMPaysWooCommerceGateway::instance();
}

/**
 * Plugin activation hook
 */
function gmpays_activate_plugin() {
    try {
        // Check PHP version first
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(sprintf(
                __('GMPays WooCommerce Gateway requires PHP version 7.4 or higher. Your current version is %s. Please upgrade PHP before activating this plugin.', 'gmpays-woocommerce-gateway'),
                PHP_VERSION
            ));
        }
        
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('GMPays WooCommerce Gateway requires WooCommerce to be installed and activated. Please install and activate WooCommerce first.', 'gmpays-woocommerce-gateway'));
        }
        
        // Check if vendor directory exists
        if (!file_exists(GMPAYS_WC_GATEWAY_PLUGIN_PATH . 'vendor/autoload.php')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('GMPays WooCommerce Gateway is missing required dependencies. Please run "composer install" in the plugin directory before activating.', 'gmpays-woocommerce-gateway'));
        }
        
        // Call the activator
        GMPays_Activator::activate();
        
    } catch (Exception $e) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Plugin activation failed: ' . $e->getMessage());
    }
}

/**
 * Plugin deactivation hook
 */
function gmpays_deactivate_plugin() {
    try {
        GMPays_Deactivator::deactivate();
    } catch (Exception $e) {
        error_log('GMPays plugin deactivation error: ' . $e->getMessage());
    }
}

/**
 * Plugin uninstall hook
 */
function gmpays_uninstall_plugin() {
    try {
        GMPays_Deactivator::uninstall();
    } catch (Exception $e) {
        error_log('GMPays plugin uninstall error: ' . $e->getMessage());
    }
}

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'gmpays_activate_plugin');
register_deactivation_hook(__FILE__, 'gmpays_deactivate_plugin');
register_uninstall_hook(__FILE__, 'gmpays_uninstall_plugin');

// Initialize the plugin
GMPaysWooCommerceGateway();