# Changelog

All notable changes to the GMPays WooCommerce Gateway plugin will be documented in this file.

## [1.4.6] - 2024-12-19

### 🚀 Major Changes
- **Implemented API-based status checking** as primary method for payment status updates
- **Resolved GMPays terminal endpoint limitations** that prevented webhook and URL parameter functionality
- **Automatic order status updates** when customers return from GMPays payment page

### ✅ Added
- **`check_and_update_payment_status()`** method for real-time API status checking
- **`process_successful_payment()`** method for handling successful payments
- **`process_failed_payment()` method for handling failed payments
- **`process_cancelled_payment()` method for handling cancelled payments
- **`process_processing_payment()` method for handling processing payments
- **Automatic status checking** on thank you page load
- **Enhanced order metadata** storage for GMPays invoice IDs

### 🔧 Fixed
- **Orders not updating** when users return from GMPays gateway
- **Missing payment status information** due to GMPays terminal limitations
- **Reliance on unreliable webhooks** and URL parameters
- **Order status management** for all payment scenarios
- **Admin status checking** functionality

### 🔄 Changed
- **Primary method**: Now uses API status checking instead of webhooks
- **Return handling**: Automatically checks payment status when customer returns
- **Status processing**: Direct API communication for real-time updates
- **Error handling**: Better fallback mechanisms and logging

### 🗑️ Removed
- **Dependency on webhook notifications** (kept as optional for future use)
- **Reliance on URL parameters** that GMPays doesn't send
- **Complex fallback logic** that wasn't working

### 📚 Documentation
- **Updated configuration guide** for API-based approach
- **Added troubleshooting section** for API-related issues
- **Updated testing procedures** for new implementation

## [1.4.5] - 2024-12-19

### 🚀 Major Changes
- **Complete architecture rewrite** from URL parameter-based to webhook-based system
- **Replaced deprecated hooks** with proper WooCommerce integration
- **Implemented proper webhook handling** for real-time payment status updates

### ✅ Added
- **Webhook endpoint** at `/wp-json/gmpays/v1/webhook` for GMPays notifications
- **API status polling** as backup mechanism when webhooks fail
- **Proper WooCommerce hooks** (`woocommerce_thankyou`, `woocommerce_order_status_changed`)
- **Admin meta box** with payment details and manual status check button
- **AJAX endpoint** for manual payment status verification
- **Comprehensive logging** for debugging and monitoring
- **Automatic order status updates** based on payment status
- **Fallback mechanisms** for reliable order management

### 🔧 Fixed
- **Orders not updating** when users return from GMPays gateway
- **Hooks not executing** due to incorrect implementation
- **Missing order notes** for payment status changes
- **Incompatible return URL handling** with GMPays terminal flow
- **Security vulnerabilities** in AJAX requests
- **Missing error handling** for failed API calls

### 🗑️ Removed
- **Deprecated URL parameter handling** (`gmpays_success`, `gmpays_failure`, etc.)
- **Unused cart restoration methods** that caused conflicts
- **Duplicate display methods** that cluttered the codebase
- **Ineffective fallback hooks** that didn't work properly

### 🔒 Security
- **RSA signature verification** for webhook authenticity
- **Nonce verification** for all AJAX requests
- **Input sanitization** for user data
- **Secure API communication** with GMPays

### 📚 Documentation
- **Updated configuration guide** with new webhook-based setup
- **Added troubleshooting section** for common issues
- **Included security best practices** and recommendations

### 🧪 Technical Improvements
- **Proper WordPress coding standards** compliance
- **Eliminated code duplication** and improved maintainability
- **Better error handling** and user feedback
- **Optimized database queries** and reduced overhead
- **Cleaner code structure** with logical method organization

## [1.4.4] - 2024-12-18

### ❌ Deprecated
- **URL parameter-based return handling** - replaced with webhooks
- **Manual cart restoration** - no longer needed
- **Custom return URL configuration** - now automatic

### ⚠️ Known Issues
- Orders not updating when users return from gateway
- Hooks not executing properly
- Missing order status updates
- Incompatible with GMPays terminal flow

## [1.4.3] - 2024-12-17

### ✅ Added
- Initial plugin release
- Basic GMPays integration
- HMAC and RSA authentication support
- Multi-currency support

### ❌ Issues
- Incorrect return URL handling
- Missing webhook support
- Incompatible with GMPays architecture

---

## Migration Guide

### From v1.4.5 to v1.4.6

1. **Backup your current configuration**
2. **Update the plugin** to v1.4.6
3. **Test with a small order** to verify API-based functionality
4. **Check admin meta box** for payment details
5. **Monitor debug logs** for API communication

### From v1.4.4 to v1.4.6

1. **Backup your current configuration**
2. **Update the plugin** to v1.4.6
3. **Configure API credentials** in WooCommerce settings
4. **Test with a small order** to verify functionality
5. **Check admin meta box** for payment details

### Important Notes

- **v1.4.6 is backward compatible** with v1.4.5 configurations
- **API credentials are required** for proper functionality
- **Webhooks are optional** and kept for future use
- **Test thoroughly** before deploying to production

---

## Support

For technical support or questions about this update:

1. Check the debug logs in WooCommerce → Status → Logs
2. Verify GMPays API configuration
3. Test API connection manually
4. Contact the development team with specific error details

---

**Note**: This update resolves the fundamental limitations of GMPays terminal endpoint by implementing reliable API-based status checking as the primary method for order updates.
