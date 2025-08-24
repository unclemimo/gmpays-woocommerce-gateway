# GMPays WooCommerce Gateway

A robust and reliable WooCommerce payment gateway for GMPays payment processor, designed with modern webhook-based architecture for real-time payment status updates.

## 🚀 Features

- **Real-time Payment Updates**: Webhook-based notifications for instant order status updates
- **Dual Authentication**: Support for both HMAC and RSA authentication methods
- **Multi-currency Support**: Automatic USD conversion for international payments
- **Comprehensive Logging**: Detailed logging for debugging and monitoring
- **Admin Interface**: Built-in payment status checking and management tools
- **Fallback Mechanisms**: API polling when webhooks fail
- **Security First**: RSA signature verification and secure API communication

## 🔧 Requirements

- **WordPress**: 5.0 or higher
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher (8.0+ recommended)
- **SSL Certificate**: Required for production use

## 📦 Installation

1. **Upload the plugin** to `/wp-content/plugins/gmpays-woocommerce-gateway/`
2. **Activate the plugin** through WordPress admin
3. **Configure the gateway** in WooCommerce → Settings → Payments
4. **Set up webhooks** in your GMPays control panel

## ⚙️ Configuration

### 1. Plugin Settings

Navigate to **WooCommerce → Settings → Payments → GMPays Credit Card**:

- **Enable/Disable**: Toggle the gateway
- **Title**: Payment method display name
- **Description**: Payment method description
- **API URL**: Your GMPays API endpoint
- **Project ID**: Your GMPays Project ID
- **Authentication Method**: Choose HMAC or RSA
- **HMAC Key** or **RSA Private Key**: Your credentials
- **Debug Mode**: Enable for troubleshooting

### 2. GMPays Control Panel

Configure in your GMPays control panel:

- **Webhook URL**: `https://yoursite.com/wp-json/gmpays/v1/webhook`
- **Project ID**: Your unique project identifier
- **Authentication**: HMAC key or RSA public key

### 3. Return URLs

**IMPORTANT**: The plugin automatically handles return URLs:

- **Success**: `/order-received/` (WooCommerce standard)
- **Cancel**: `/cart/` (WooCommerce standard)

**Do NOT** configure custom return URLs in GMPays.

## 🔄 How It Works

### Payment Flow

1. **Customer checkout** → Order created with "pending" status
2. **Redirect to GMPays** → Customer completes payment on GMPays terminal
3. **GMPays processes payment** → Sends webhook notification
4. **Plugin receives webhook** → Updates order status automatically
5. **Customer returns** → Sees updated order status on thank you page

### Webhook Processing

The plugin registers a webhook endpoint that:

- Receives payment notifications from GMPays
- Verifies signatures for security
- Updates order statuses automatically
- Logs all activities for debugging

### Fallback Mechanisms

If webhooks fail, the plugin includes:

- **API status polling** on thank you page
- **Manual status checking** from admin panel
- **Automatic retry logic** for failed operations

## 🛠️ Troubleshooting

### Common Issues

1. **Orders not updating**: Check webhook URL configuration in GMPays
2. **Payment status unknown**: Use admin "Check Payment Status" button
3. **Webhook failures**: Check server logs and GMPays webhook settings

### Debug Mode

Enable debug mode to see detailed logs in:

- WooCommerce → Status → Logs → `gmpays-gateway`
- WordPress error log (if configured)

### Manual Status Check

From the admin order page:

1. Go to WooCommerce → Orders
2. Click on a GMPays order
3. Look for "GMPays Payment Details" meta box
4. Click "Check Payment Status" button

## 🔒 Security

- **RSA signature verification** for webhooks
- **Nonce verification** for AJAX requests
- **Input sanitization** for all user data
- **Secure API communication** with GMPays

## 📚 Documentation

- **[Configuration Guide](GMPAYS_CONFIGURATION.md)**: Complete setup instructions
- **[Changelog](CHANGELOG.md)**: Version history and updates
- **[Troubleshooting](GMPAYS_CONFIGURATION.md#troubleshooting)**: Common issues and solutions

## 🆘 Support

For technical support:

1. Check the debug logs
2. Verify GMPays configuration
3. Test webhook endpoint accessibility
4. Contact support with specific error details

## 🔄 Migration

### From Previous Versions

If upgrading from v1.4.4 or earlier:

1. **Backup your configuration**
2. **Update the plugin**
3. **Configure webhook URL** in GMPays
4. **Remove custom return URLs**
5. **Test thoroughly**

**Note**: v1.4.5 is NOT backward compatible with previous versions.

## 📄 License

This plugin is proprietary software. All rights reserved.

## 🏗️ Architecture

### Core Components

- **Gateway Class**: Main payment gateway implementation
- **API Client**: Handles communication with GMPays API
- **Webhook Handler**: Processes payment notifications
- **Currency Manager**: Handles multi-currency conversion
- **Admin Interface**: Provides management tools

### Hooks and Filters

The plugin integrates with WooCommerce using:

- `woocommerce_thankyou`: Handles payment returns
- `woocommerce_order_status_changed`: Manages status updates
- `add_meta_boxes`: Adds admin interface
- `wp_ajax_*`: Handles AJAX requests

### Database Integration

- **Order Meta**: Stores payment details and status
- **Transaction Tracking**: Links orders to GMPays invoices
- **Logging**: Comprehensive activity logging

---

**Version**: 1.4.5  
**Last Updated**: December 19, 2024  
**Compatibility**: WordPress 5.0+, WooCommerce 5.0+