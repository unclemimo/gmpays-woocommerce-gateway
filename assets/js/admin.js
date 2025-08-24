/**
 * GMPays WooCommerce Gateway Admin JavaScript
 *
 * Handles dynamic form fields and admin functionality
 *
 * @package GMPaysWooCommerceGateway
 */

jQuery(document).ready(function($) {
    
    // Cache DOM elements
    var $authMethod = $('#woocommerce_gmpays_credit_card_auth_method');
    var $hmacKey = $('#woocommerce_gmpays_credit_card_hmac_key').closest('tr');
    var $privateKey = $('#woocommerce_gmpays_credit_card_private_key').closest('tr');
    var $keyInstructions = $('#woocommerce_gmpays_credit_card_key_generation_instructions').closest('tr');
    
    /**
     * Initialize admin functionality
     */
    function init() {
        // Show/hide fields based on authentication method
        toggleAuthFields();
        
        // Bind events
        bindEvents();
        
        // Initialize tooltips
        initTooltips();
    }
    
    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Authentication method change
        $authMethod.on('change', function() {
            toggleAuthFields();
        });
        
        // Form validation
        $('form').on('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
        });
        
        // Copy to clipboard functionality
        $('.copy-to-clipboard').on('click', function(e) {
            e.preventDefault();
            copyToClipboard($(this).data('text'));
        });
        
        // Test connection button
        $('.test-connection').on('click', function(e) {
            e.preventDefault();
            testConnection();
        });
    }
    
    /**
     * Toggle authentication fields based on selected method
     */
    function toggleAuthFields() {
        var selectedMethod = $authMethod.val();
        
        if (selectedMethod === 'hmac') {
            $hmacKey.show();
            $privateKey.hide();
            $keyInstructions.show();
        } else if (selectedMethod === 'rsa') {
            $hmacKey.hide();
            $privateKey.show();
            $keyInstructions.show();
        } else {
            $hmacKey.hide();
            $privateKey.hide();
            $keyInstructions.hide();
        }
    }
    
    /**
     * Validate form before submission
     */
    function validateForm() {
        var isValid = true;
        var selectedMethod = $authMethod.val();
        
        // Clear previous error messages
        $('.gmpays-error').remove();
        
        // Validate project ID
        var projectId = $('#woocommerce_gmpays_credit_card_project_id').val();
        if (!projectId || projectId.trim() === '') {
            showFieldError('project_id', 'Project ID is required');
            isValid = false;
        }
        
        // Validate authentication method specific fields
        if (selectedMethod === 'hmac') {
            var hmacKey = $('#woocommerce_gmpays_credit_card_hmac_key').val();
            if (!hmacKey || hmacKey.trim() === '') {
                showFieldError('hmac_key', 'HMAC Key is required for HMAC authentication');
                isValid = false;
            }
        } else if (selectedMethod === 'rsa') {
            var privateKey = $('#woocommerce_gmpays_credit_card_private_key').val();
            if (!privateKey || privateKey.trim() === '') {
                showFieldError('private_key', 'RSA Private Key is required for RSA authentication');
                isValid = false;
            } else if (!validatePrivateKey(privateKey)) {
                showFieldError('private_key', 'Invalid RSA Private Key format. Please check your key.');
                isValid = false;
            }
        }
        
        // Validate minimum amount
        var minimumAmount = $('#woocommerce_gmpays_credit_card_minimum_amount').val();
        if (minimumAmount && parseFloat(minimumAmount) < 5.0) {
            showFieldError('minimum_amount', 'Minimum amount must be at least 5.00 EUR');
            isValid = false;
        }
        
        return isValid;
    }
    
    /**
     * Show field error message
     */
    function showFieldError(fieldId, message) {
        var $field = $('#woocommerce_gmpays_credit_card_' + fieldId);
        var $row = $field.closest('tr');
        
        // Remove existing error
        $row.find('.gmpays-error').remove();
        
        // Add error message
        var $error = $('<div class="gmpays-error" style="color: #d63638; font-size: 12px; margin-top: 5px;">' + message + '</div>');
        $row.append($error);
        
        // Highlight field
        $field.addClass('error');
        
        // Scroll to first error
        if ($('.gmpays-error').length === 1) {
            $('html, body').animate({
                scrollTop: $field.offset().top - 100
            }, 500);
        }
    }
    
    /**
     * Validate RSA private key format
     */
    function validatePrivateKey(key) {
        // Check if it contains BEGIN and END markers
        if (key.indexOf('-----BEGIN') === -1 || key.indexOf('-----END') === -1) {
            return false;
        }
        
        // Check if it's a valid PEM format
        var lines = key.split('\n');
        var inKey = false;
        var keyContent = '';
        
        for (var i = 0; i < lines.length; i++) {
            var line = lines[i].trim();
            
            if (line === '-----BEGIN RSA PRIVATE KEY-----') {
                inKey = true;
            } else if (line === '-----END RSA PRIVATE KEY-----') {
                inKey = false;
                break;
            } else if (inKey && line !== '') {
                keyContent += line;
            }
        }
        
        // Check if we have key content
        return keyContent.length > 0;
    }
    
    /**
     * Copy text to clipboard
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            // Use modern clipboard API
            navigator.clipboard.writeText(text).then(function() {
                showCopySuccess();
            }).catch(function() {
                fallbackCopyToClipboard(text);
            });
        } else {
            // Fallback for older browsers
            fallbackCopyToClipboard(text);
        }
    }
    
    /**
     * Fallback copy to clipboard method
     */
    function fallbackCopyToClipboard(text) {
        var textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            showCopySuccess();
        } catch (err) {
            console.error('Fallback copy failed:', err);
            showCopyError();
        }
        
        document.body.removeChild(textArea);
    }
    
    /**
     * Show copy success message
     */
    function showCopySuccess() {
        var $message = $('<div class="notice notice-success" style="margin: 10px 0;"><p>Copied to clipboard successfully!</p></div>');
        $('.gmpays-copy-message').remove();
        $('.woocommerce-save-button').before($message);
        
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    /**
     * Show copy error message
     */
    function showCopyError() {
        var $message = $('<div class="notice notice-error" style="margin: 10px 0;"><p>Failed to copy to clipboard. Please copy manually.</p></div>');
        $('.gmpays-copy-message').remove();
        $('.woocommerce-save-button').before($message);
        
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Test GMPays connection
     */
    function testConnection() {
        var $button = $('.test-connection');
        var originalText = $button.text();
        
        // Disable button and show loading
        $button.prop('disabled', true).text('Testing...');
        
        // Get form data
        var formData = {
            action: 'gmpays_test_connection',
            nonce: gmpays_admin.nonce,
            project_id: $('#woocommerce_gmpays_credit_card_project_id').val(),
            auth_method: $('#woocommerce_gmpays_credit_card_auth_method').val(),
            hmac_key: $('#woocommerce_gmpays_credit_card_hmac_key').val(),
            private_key: $('#woocommerce_gmpays_credit_card_private_key').val(),
            api_url: $('#woocommerce_gmpays_credit_card_api_url').val()
        };
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showTestSuccess(response.data.message);
                } else {
                    showTestError(response.data);
                }
            },
            error: function() {
                showTestError('Connection test failed. Please check your settings and try again.');
            },
            complete: function() {
                // Re-enable button
                $button.prop('disabled', false).text(originalText);
            }
        });
    }
    
    /**
     * Show test success message
     */
    function showTestSuccess(message) {
        var $message = $('<div class="notice notice-success" style="margin: 10px 0;"><p>' + message + '</p></div>');
        $('.gmpays-test-message').remove();
        $('.woocommerce-save-button').before($message);
        
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Show test error message
     */
    function showTestError(message) {
        var $message = $('<div class="notice notice-error" style="margin: 10px 0;"><p>' + message + '</p></div>');
        $('.gmpays-test-message').remove();
        $('.woocommerce-save-button').before($message);
        
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 8000);
    }
    
    /**
     * Initialize tooltips
     */
    function initTooltips() {
        // Add tooltip functionality to help icons
        $('.gmpays-help').each(function() {
            var $help = $(this);
            var $tooltip = $('<div class="gmpays-tooltip" style="display: none; position: absolute; background: #333; color: white; padding: 8px; border-radius: 4px; font-size: 12px; z-index: 1000; max-width: 300px;"></div>');
            
            $help.after($tooltip);
            
            $help.on('mouseenter', function() {
                var helpText = $help.data('help');
                if (helpText) {
                    $tooltip.text(helpText).show();
                }
            });
            
            $help.on('mouseleave', function() {
                $tooltip.hide();
            });
        });
    }
    
    /**
     * Add help icons to form fields
     */
    function addHelpIcons() {
        var helpData = {
            'project_id': 'Your GMPays Project ID from the control panel (shown as "ID IN PROJECT")',
            'auth_method': 'Choose between HMAC (simpler) or RSA (more secure) authentication',
            'hmac_key': 'Your HMAC key from GMPays control panel. Keep this secure!',
            'private_key': 'Your RSA private key in PEM format. Include BEGIN and END lines.',
            'minimum_amount': 'Minimum order amount in EUR. GMPays requires at least 5.00 EUR.',
            'api_url': 'GMPays API URL from your control panel (usually https://pay.gmpays.com)'
        };
        
        Object.keys(helpData).forEach(function(fieldId) {
            var $field = $('#woocommerce_gmpays_credit_card_' + fieldId);
            var $row = $field.closest('tr');
            var $label = $row.find('label');
            
            if ($label.length && !$label.find('.gmpays-help').length) {
                var $help = $('<span class="gmpays-help dashicons dashicons-editor-help" data-help="' + helpData[fieldId] + '" style="margin-left: 5px; color: #0073aa; cursor: help;"></span>');
                $label.append($help);
            }
        });
    }
    
    /**
     * Add copy buttons to configuration fields
     */
    function addCopyButtons() {
        var copyData = {
            'webhook_url': home_url + '/wp-json/gmpays/v1/webhook',
            'success_url': home_url + '/?gmpays_success=1&order_id={order_id}',
            'failure_url': home_url + '/?gmpays_failure=1&order_id={order_id}',
            'cancel_url': home_url + '/?gmpays_cancelled=1&order_id={order_id}'
        };
        
        Object.keys(copyData).forEach(function(fieldType) {
            var $field = $('input[data-copy="' + fieldType + '"]');
            if ($field.length) {
                var $copyButton = $('<button type="button" class="button copy-to-clipboard" data-text="' + copyData[fieldType] + '">Copy</button>');
                $field.after($copyButton);
            }
        });
    }
    
    /**
     * Add test connection button
     */
    function addTestButton() {
        var $saveButton = $('.woocommerce-save-button');
        if ($saveButton.length && !$('.test-connection').length) {
            var $testButton = $('<button type="button" class="button test-connection" style="margin-right: 10px;">Test Connection</button>');
            $saveButton.before($testButton);
        }
    }
    
    // Initialize when DOM is ready
    init();
    
    // Add additional elements after a short delay
    setTimeout(function() {
        addHelpIcons();
        addCopyButtons();
        addTestButton();
    }, 100);
    
});
