# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2024-12-19

### ✨ Added
- **Comprehensive Return URL Management**: Added proper handling for GMPays return URLs (success, failure, cancelled)
- **REST API Endpoints**: New endpoints for handling GMPays return notifications
- **Enhanced Order Status Management**: Orders now properly transition to "on-hold" status when payments are successful
- **Transaction ID Integration**: Improved integration with WooCommerce native transaction ID field
- **Return URL Parameters**: Added support for GMPays return URL parameters (transaction_id, amount, currency, reason)
- **AJAX Payment Status Check**: Admin can now check payment status directly from order page

### 🔧 Changed
- **Order Status Flow**: Successful payments now consistently set order to "on-hold" instead of "pending payment"
- **Return URL Handling**: Improved handling of customer returns from GMPays payment page
- **Webhook Processing**: Enhanced webhook handling to ensure proper order status updates
- **URL Structure**: Updated return URLs to include proper parameters for GMPays integration

### 🐛 Fixed
- **Failed Payment Returns**: Fixed issue where orders remained "pending payment" when customers returned from GMPays without completing payment
- **Order Status Updates**: Orders are now properly marked as failed when payment processing fails
- **Transaction ID Updates**: Fixed issue where transaction IDs weren't properly stored in WooCommerce
- **Return URL Processing**: Fixed handling of GMPays return notifications

### 📋 Technical Details
- Added `handle_gmpays_returns()` method for processing return URL parameters
- Implemented REST API endpoints for GMPays return handling
- Added `ajax_check_payment_status()` method for admin payment status verification
- Enhanced webhook handler to ensure consistent order status management
- Updated URL generation to include proper GMPays return parameters

## [1.3.3] - 2024-12-19

### ✨ Added
- **Minimum Amount Validation**: Added configurable minimum order amount (default: 5.00 EUR) to prevent orders below GMPays requirements
- **Frontend Validation**: Checkout now prevents proceeding if order amount is below minimum requirement
- **Failed Payment Handling**: Automatic order status management when customers return without completing payment
- **Cart Restoration**: Failed orders automatically restore items to customer's cart
- **Smart Currency Conversion**: Minimum amount validation works with multiple currencies using WooCommerce Multi Currency

### 🔧 Changed
- **Gateway Availability**: Gateway now automatically hides when order amount is below minimum
- **Error Handling**: Better error messages for minimum amount violations
- **Order Flow**: Failed payments now properly redirect to cart with failure parameters
- **User Experience**: Customers get clear feedback about minimum amount requirements

### 🐛 Fixed
- **Pending Order Issue**: Fixed issue where orders remained "pending payment" when customers returned from GMPays without completing payment
- **Order Status Management**: Orders are now properly marked as failed when payment processing fails
- **Cart State**: Cart items are properly restored when orders fail

### 📋 Technical Details
- Added `minimum_amount` configuration field in gateway settings
- Implemented `convert_to_eur()` method in currency manager for minimum amount validation
- Added `meets_minimum_amount()` and `check_minimum_amount()` methods for validation
- Implemented `handle_failed_payment_return()` for managing failed payment returns
- Added `restore_cart_from_order()` method for cart restoration

## [1.3.2] - 2024-12-19

### ✨ Added
- **Enhanced Order Management**: Orders now automatically move to "on-hold" status when payment is successful
- **Private Order Notes**: Added private notes for all payment events (success, failure, cancellation, refund)
- **Transaction ID Integration**: Now properly sets WooCommerce native `transaction_id` field
- **Processing Fee Notice**: Added note about banking processing fees in order descriptions
- **Improved Webhook Handling**: Enhanced webhook processing with better order status management

### 🔧 Changed
- **Order Status Flow**: Successful payments now set order to "on-hold" instead of "pending payment"
- **Note Visibility**: Public notes for customers, private notes for administrators
- **Description Format**: Order descriptions now include processing fee information
- **Metadata Management**: Better integration with WooCommerce native fields

### 🐛 Fixed
- **Order Status Updates**: Fixed issue where successful payments didn't update order status properly
- **Transaction Tracking**: Improved transaction ID handling and storage
- **Webhook Processing**: Enhanced error handling and logging for webhook events

## [1.3.1] - 2024-12-19

### 🐛 Bug Fixes

### Fixed HMAC Authentication Issues
- **Fixed API Client Initialization**: Updated `is_configured()` method to properly check HMAC vs RSA authentication
- **Fixed Private Key Validation Errors**: Updated `create_invoice()` method to check for HMAC key when HMAC is selected
- **Fixed Test Connection Method**: Updated `test_connection()` method to handle both authentication methods
- **Removed Hardcoded Private Key Requirements**: HMAC authentication no longer requires RSA private keys

### Updated API Integration
- **Changed API Endpoint**: Updated from `/invoice` to `/terminal/create` for credit card payments (per GMPays documentation)
- **Fixed Request Data Format**: 
  - Changed `description` to `comment` (per GMPays API specification)
  - Changed `email` to `add_email` (per GMPays API specification)
  - Added `wallet` field (required by GMPays terminal API)
- **Updated Response Handling**: Modified to match GMPays terminal API response format
- **Fixed Signature Generation**: HMAC signatures now properly generated for terminal API calls

## [1.3.0] - 2024-12-19

### ✨ Added
- **Dual Authentication Support**: Added support for both HMAC and RSA authentication methods
- **Authentication Method Selection**: Users can now choose between HMAC (simpler) and RSA (more secure)
- **Dynamic Form Fields**: Admin interface automatically shows/hides relevant fields based on selected method
- **HMAC Key Support**: Plugin now properly supports HMAC keys from GMPays control panel
- **Backward Compatibility**: Existing RSA configurations continue to work

### 🔧 Changed
- Updated gateway settings to include authentication method selection
- Modified API client to handle both HMAC and RSA signatures
- Enhanced webhook signature verification for both methods
- Updated installation documentation with dual authentication instructions
- Improved error handling for authentication method mismatches

### 🐛 Fixed
- Fixed HMAC authentication not working due to hardcoded RSA requirements
- Fixed API client initialization errors when using HMAC keys
- Fixed signature verification failures for HMAC authentication
- Fixed configuration validation errors for HMAC method

## [1.2.0] - 2024-12-18

### ✨ Added
- **RSA Signature Support**: Replaced HMAC with RSA signatures for enhanced security
- **Enhanced Security**: Added RSA private key authentication
- **Improved Error Handling**: Better error messages and logging
- **Webhook Signature Verification**: Added RSA signature verification for webhooks

### 🔧 Changed
- **Authentication Method**: Changed from HMAC to RSA signatures
- **API Security**: Enhanced API request security with RSA signatures
- **Documentation**: Updated installation guide for RSA key setup

### 🚨 Breaking Changes
- **HMAC Keys No Longer Supported**: Plugin now requires RSA private keys
- **Configuration Update Required**: Users must regenerate RSA keys in GMPays control panel

## [1.1.0] - 2024-12-17

### ✨ Added
- **Initial Release**: Basic GMPays WooCommerce integration
- **Credit Card Support**: Accept international credit card payments
- **Multi-Currency Support**: Automatic USD conversion for international payments
- **Webhook Integration**: Payment status notifications
- **Admin Interface**: Payment gateway configuration and order management

### 🔧 Features
- **Payment Processing**: Secure credit card payment processing via GMPays
- **Order Management**: Automatic order status updates
- **Currency Conversion**: Real-time currency conversion to USD
- **Error Handling**: Comprehensive error handling and logging
- **Security**: HMAC signature verification for webhooks

## [1.2.1] - 2024-12-19

### 🔧 Changed
- Updated URLs to use elgrupito.com domain
- Success URL: `https://elgrupito.com/order-received/order-received/`
- Failure URL: `https://elgrupito.com/checkout/`
- Notification URL: `https://elgrupito.com/wp-json/gmpays/v1/webhook`

### 🔒 Security
- Added `.gitignore` to exclude private keys and sensitive files
- Enhanced security by preventing accidental commit of cryptographic keys

### 📦 Release
- Included all Composer dependencies in release ZIP
- No need to run `composer install` on server
- Plugin ready to use immediately after installation

## [1.1.0] - 2024-12-15

### ✨ Added
- Project ID and HMAC Key authentication
- Improved webhook configuration instructions
- Better integration with GMPays SDK
- Enhanced error handling and logging

### 🔧 Changed
- Removed sandbox mode (not available in GMPays)
- Updated authentication flow
- Improved webhook processing

### 🐛 Fixed
- Various minor bugs and improvements

## [1.0.0] - 2024-12-10

### ✨ Added
- Initial release
- Credit card payment processing
- Multi-currency support
- Webhook handling
- Spanish/English localization
- WooCommerce integration
- GMPays API integration

---

## Migration Guide from v1.1.0 to v1.2.0

### ⚠️ Important: This is a breaking change update

Due to the switch from HMAC to RSA signatures, you must follow these steps:

1. **Backup your current configuration**
2. **Generate new RSA keys** using the included script:
   ```bash
   cd wp-content/plugins/gmpays-woocommerce-gateway/
   chmod +x setup-gmpays.sh
   ./setup-gmpays.sh
   ```
3. **Upload your public key to GMPays**:
   - Go to [cp.gmpays.com/project/sign](https://cp.gmpays.com/project/sign)
   - Paste your public key in the "Public key" field
   - Click "Generate signature HMAC" to regenerate the HMAC key
4. **Update plugin settings**:
   - Replace HMAC Key with RSA Private Key
   - Copy the entire content of `private_key.pem` (including BEGIN/END lines)
5. **Test the integration** with a small transaction

### 🔒 Security Notes
- Keep your private key secure and never share it
- The private key is stored encrypted in WordPress options
- Public key is uploaded to GMPays for signature verification
- All communications now use RSA-SHA256 signatures

---

For detailed installation instructions, see [README.md](README.md) and [INSTALLATION.md](INSTALLATION.md).
