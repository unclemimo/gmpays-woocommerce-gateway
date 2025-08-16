/**
 * GMPays Checkout JavaScript
 * 
 * Handles checkout interactions for GMPays payment gateway
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // GMPays checkout handler
    var GMPaysCheckout = {
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.setupPaymentMethodChange();
        },
        
        // Bind events
        bindEvents: function() {
            // Listen for checkout form submission
            $(document.body).on('checkout_place_order_gmpays_credit_card', this.onCheckoutSubmit);
            
            // Listen for payment method change
            $(document.body).on('change', 'input[name="payment_method"]', this.onPaymentMethodChange);
            
            // Listen for checkout error
            $(document.body).on('checkout_error', this.onCheckoutError);
        },
        
        // Setup payment method change handler
        setupPaymentMethodChange: function() {
            var self = this;
            $('input[name="payment_method"]').on('change', function() {
                self.onPaymentMethodChange();
            });
        },
        
        // Handle payment method change
        onPaymentMethodChange: function() {
            var selectedMethod = $('input[name="payment_method"]:checked').val();
            
            if (selectedMethod === 'gmpays_credit_card') {
                // Show GMPays specific information if needed
                this.showGMPaysInfo();
            } else {
                // Hide GMPays specific information
                this.hideGMPaysInfo();
            }
        },
        
        // Show GMPays payment information
        showGMPaysInfo: function() {
            // Add any GMPays specific UI elements if needed
            var $paymentBox = $('.payment_method_gmpays_credit_card');
            
            if ($paymentBox.length) {
                // Add security badges or additional information
                if (!$paymentBox.find('.gmpays-security-info').length) {
                    var securityInfo = '<div class="gmpays-security-info" style="margin-top: 10px; padding: 10px; background: #f0f0f0; border-radius: 4px;">' +
                        '<span style="color: #27ae60;">🔒 ' + this.getTranslation('secure_payment', 'Secure payment processed by GMPays') + '</span>' +
                        '</div>';
                    $paymentBox.find('.payment_box').append(securityInfo);
                }
            }
        },
        
        // Hide GMPays payment information
        hideGMPaysInfo: function() {
            $('.gmpays-security-info').remove();
        },
        
        // Handle checkout form submission
        onCheckoutSubmit: function(e) {
            var $form = $('form.checkout');
            
            // Check if GMPays is selected
            if ($('input[name="payment_method"]:checked').val() !== 'gmpays_credit_card') {
                return true;
            }
            
            // Add loading indicator
            GMPaysCheckout.showLoader();
            
            // Form will be submitted normally to process_payment
            return true;
        },
        
        // Handle checkout errors
        onCheckoutError: function() {
            GMPaysCheckout.hideLoader();
        },
        
        // Show loading indicator
        showLoader: function() {
            // Block checkout form
            $('.woocommerce-checkout-payment').block({
                message: this.getTranslation('processing', 'Processing your payment...'),
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        },
        
        // Hide loading indicator
        hideLoader: function() {
            $('.woocommerce-checkout-payment').unblock();
        },
        
        // Get translated text
        getTranslation: function(key, defaultText) {
            if (typeof gmpays_translations !== 'undefined' && gmpays_translations[key]) {
                return gmpays_translations[key];
            }
            return defaultText;
        },
        
        // Currency conversion notice
        showCurrencyNotice: function() {
            // Check if we need to show currency conversion notice
            if (typeof gmpays_params !== 'undefined' && gmpays_params.show_currency_notice) {
                var notice = '<div class="gmpays-currency-notice woocommerce-info">' +
                    this.getTranslation('currency_notice', 'Your order will be processed in USD. The amount shown will be converted at checkout.') +
                    '</div>';
                
                if (!$('.gmpays-currency-notice').length) {
                    $('.payment_method_gmpays_credit_card').prepend(notice);
                }
            }
        },
        
        // Validate checkout fields specific to GMPays
        validateCheckout: function() {
            // Add any GMPays specific validation if needed
            var isValid = true;
            
            // Example: Validate email format
            var email = $('#billing_email').val();
            if (email && !this.isValidEmail(email)) {
                this.showError(this.getTranslation('invalid_email', 'Please enter a valid email address.'));
                isValid = false;
            }
            
            return isValid;
        },
        
        // Email validation helper
        isValidEmail: function(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        // Show error message
        showError: function(message) {
            var $notices = $('.woocommerce-notices-wrapper').first();
            
            if ($notices.length) {
                $notices.html('<ul class="woocommerce-error" role="alert"><li>' + message + '</li></ul>');
                
                // Scroll to error
                $('html, body').animate({
                    scrollTop: $notices.offset().top - 100
                }, 500);
            }
        }
    };
    
    // Initialize GMPays checkout
    GMPaysCheckout.init();
    
    // Handle AJAX checkout updates
    $(document.body).on('updated_checkout', function() {
        GMPaysCheckout.setupPaymentMethodChange();
        
        // Check if GMPays is selected and show info
        if ($('input[name="payment_method"]:checked').val() === 'gmpays_credit_card') {
            GMPaysCheckout.showGMPaysInfo();
            GMPaysCheckout.showCurrencyNotice();
        }
    });
    
    // Handle payment method selection on page load
    if ($('input[name="payment_method"]:checked').val() === 'gmpays_credit_card') {
        GMPaysCheckout.showGMPaysInfo();
        GMPaysCheckout.showCurrencyNotice();
    }
});

// Translations object (will be populated by PHP localization)
var gmpays_translations = window.gmpays_translations || {
    'secure_payment': 'Secure payment processed by GMPays',
    'processing': 'Processing your payment...',
    'currency_notice': 'Your order will be processed in USD. The amount shown will be converted at checkout.',
    'invalid_email': 'Please enter a valid email address.',
    'payment_error': 'There was an error processing your payment. Please try again.'
};
