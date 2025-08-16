/**
 * GMPays WooCommerce Gateway - Admin JavaScript
 * 
 * Handles dynamic form fields for authentication method selection
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Function to toggle key fields based on authentication method
    function toggleKeyFields() {
        var authMethod = $('#woocommerce_gmpays_credit_card_auth_method').val();
        
        if (authMethod === 'hmac') {
            // Show HMAC key field, hide RSA private key field
            $('.form-table tr:has(#woocommerce_gmpays_credit_card_hmac_key)').show();
            $('.form-table tr:has(#woocommerce_gmpays_credit_card_private_key)').hide();
            $('.form-table tr:has(.key_generation_instructions)').hide();
        } else {
            // Show RSA private key field, hide HMAC key field
            $('.form-table tr:has(#woocommerce_gmpays_credit_card_hmac_key)').hide();
            $('.form-table tr:has(#woocommerce_gmpays_credit_card_private_key)').show();
            $('.form-table tr:has(.key_generation_instructions)').show();
        }
    }
    
    // Initial toggle on page load
    toggleKeyFields();
    
    // Toggle when authentication method changes
    $('#woocommerce_gmpays_credit_card_auth_method').on('change', function() {
        toggleKeyFields();
    });
    
    // Add some styling to make the form more user-friendly
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .form-table tr:has(#woocommerce_gmpays_credit_card_hmac_key),
            .form-table tr:has(#woocommerce_gmpays_credit_card_private_key) {
                transition: all 0.3s ease;
            }
            .form-table tr:has(#woocommerce_gmpays_credit_card_auth_method) {
                background-color: #f9f9f9;
                border-left: 4px solid #0073aa;
            }
        `)
        .appendTo('head');
});
