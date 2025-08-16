# GMPays WooCommerce Payment Gateway

A comprehensive WooCommerce payment gateway for GMPays international payment processor with support for multiple currencies, dual authentication methods, and enhanced order management.

## Features

### 🔐 Authentication Methods
- **HMAC Authentication**: Simple and secure HMAC key-based authentication
- **RSA Authentication**: Advanced RSA private key authentication for enhanced security

### 💳 Payment Processing
- **Credit Card Payments**: Accept international credit card payments
- **Multiple Currencies**: Support for USD, EUR, COP, MXN, ARS, VES, PEN, CLP, BRL, UYU
- **Currency Conversion**: Automatic currency conversion using WooCommerce Multi Currency

### 📊 Order Management
- **Automatic Status Updates**: Orders automatically transition to appropriate statuses
- **Transaction Tracking**: Complete transaction history and metadata storage
- **Order Notes**: Public and private notes for all payment events
- **Minimum Amount Validation**: Configurable minimum order amounts (default: 5.00 EUR)

### 🔄 Return URL Management
- **Success Returns**: Handle successful payment returns with proper order status updates
- **Failure Returns**: Process failed payment returns and restore cart items
- **Cancellation Returns**: Handle payment cancellations gracefully
- **REST API Endpoints**: Dedicated endpoints for GMPays return notifications

### 📡 Webhook Integration
- **Real-time Notifications**: Receive instant payment status updates from GMPays
- **Signature Verification**: Secure webhook processing with RSA signature verification
- **Automatic Processing**: Orders are automatically updated based on webhook notifications

### 🛠️ Admin Features
- **Payment Status Check**: Verify payment status directly from order page
- **Transaction Details**: View complete GMPays transaction information
- **Debug Logging**: Comprehensive logging for troubleshooting
- **Meta Box Integration**: Dedicated meta box for GMPays payment details

## Installation

1. **Upload Plugin**: Upload the plugin files to `/wp-content/plugins/gmpays-woocommerce-gateway/`
2. **Activate Plugin**: Activate the plugin through the 'Plugins' menu in WordPress
3. **Configure Gateway**: Go to WooCommerce > Settings > Payments > GMPays Credit Card
4. **Set Up Authentication**: Choose between HMAC or RSA authentication and configure keys
5. **Configure URLs**: Set up return URLs and webhook endpoints in GMPays control panel

## Configuration

### Authentication Setup

#### HMAC Authentication (Recommended for most users)
1. Get your HMAC key from GMPays control panel
2. Select "HMAC" as authentication method
3. Enter your HMAC key in the settings

#### RSA Authentication (Advanced users)
1. Generate RSA key pair:
   ```bash
   openssl genrsa -out private_key.pem 2048
   openssl rsa -in private_key.pem -pubout -out public_key.pem -outform PEM
   ```
2. Upload public key to GMPays control panel
3. Select "RSA" as authentication method
4. Paste your private key in the settings

### URL Configuration

Configure these URLs in your GMPays control panel:

- **Notification URL**: `https://yoursite.com/wp-json/gmpays/v1/webhook`
- **Success URL**: `https://yoursite.com/?gmpays_success=1&order_id={order_id}`
- **Failure URL**: `https://yoursite.com/?gmpays_failure=1&order_id={order_id}`
- **Cancel URL**: `https://yoursite.com/?gmpays_cancelled=1&order_id={order_id}`

Replace `{order_id}` with the actual order ID in your GMPays configuration.

## How It Works

### Payment Flow
1. **Customer Checkout**: Customer selects GMPays Credit Card as payment method
2. **Order Creation**: WooCommerce creates order and redirects to GMPays
3. **Payment Processing**: Customer completes payment on GMPays platform
4. **Return Handling**: Customer returns to your site via configured return URLs
5. **Order Update**: Order status is automatically updated based on payment result
6. **Webhook Processing**: GMPays sends webhook notifications for real-time updates

### Return URL Processing
The plugin handles three types of returns from GMPays:

- **Success Returns**: Orders are marked as "on-hold" with transaction details
- **Failure Returns**: Orders are marked as "failed" with failure reasons
- **Cancellation Returns**: Orders are marked as "cancelled" and cart is restored

### Webhook Processing
Webhooks provide real-time payment status updates:
- **Payment Success**: Order status updated to "on-hold"
- **Payment Failure**: Order status updated to "failed"
- **Payment Cancellation**: Order status updated to "cancelled"

## Order Statuses

- **Pending**: Initial order status, awaiting payment
- **On-Hold**: Payment received, awaiting manual confirmation
- **Processing**: Order confirmed and being processed
- **Completed**: Order fulfilled and completed
- **Failed**: Payment failed or was rejected
- **Cancelled**: Payment was cancelled by customer

## Troubleshooting

### Common Issues

1. **Authentication Errors**: Verify your HMAC key or RSA private key is correct
2. **Webhook Failures**: Check that your webhook URL is accessible and properly configured
3. **Order Status Issues**: Verify return URLs are correctly configured in GMPays
4. **Currency Conversion**: Ensure WooCommerce Multi Currency is properly configured

### Debug Mode
Enable debug logging in the gateway settings to troubleshoot issues:
- Logs are stored in WooCommerce logs
- Check for GMPays-specific log entries
- Verify API requests and responses

### Support
For technical support and issues:
- Check the debug logs for error messages
- Verify your GMPays account configuration
- Contact GMPays support for payment processing issues

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history and updates.

## Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- SSL certificate (required for production)

## License

This plugin is licensed under the GPL v2 or later.

## Support

For support and feature requests, please contact the development team at ElGrupito.