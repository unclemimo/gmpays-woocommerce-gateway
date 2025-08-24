# GMPays WooCommerce Gateway - Configuration Guide

## Overview

This plugin integrates GMPays payment processor with WooCommerce using **API status checking** as the primary method for payment status updates, since GMPays terminal endpoint doesn't reliably send webhooks or return URL parameters.

## Key Features

- ✅ **API status checking** for real-time payment status updates
- ✅ **Automatic order status updates** when customers return from GMPays
- ✅ **Proper WooCommerce integration** using standard hooks
- ✅ **Admin interface** for manual status checking
- ✅ **Comprehensive logging** for debugging
- ✅ **Fallback mechanisms** for reliability

## Configuration Steps

### 1. GMPays Control Panel Setup

In your GMPays control panel, configure:

- **Project ID**: Your unique project identifier
- **Authentication Method**: Choose between HMAC or RSA
- **API URL**: Your GMPays API endpoint
- **Webhook URL**: `https://yoursite.com/wp-json/gmpays/v1/webhook` (optional, for future use)

### 2. Plugin Settings

In WooCommerce → Settings → Payments → GMPays Credit Card:

- **Enable/Disable**: Enable the gateway
- **Title**: Payment method title (e.g., "Credit Card via GMPays")
- **Description**: Payment method description
- **API URL**: Your GMPays API URL (e.g., `https://paygate.gamemoney.com`)
- **Project ID**: Your GMPays Project ID
- **Authentication Method**: HMAC or RSA
- **HMAC Key** or **RSA Private Key**: Your authentication credentials
- **Debug Mode**: Enable for troubleshooting

### 3. Return URL Configuration

**IMPORTANT**: The plugin now uses WooCommerce's standard return URLs:

- **Success URL**: Automatically set to `/order-received/` (WooCommerce standard)
- **Cancel URL**: Automatically set to `/cart/` (WooCommerce standard)

**Do NOT** configure custom return URLs in GMPays - the plugin handles this automatically.

## How It Works

### Payment Flow

1. **Customer checkout** → Order created with "pending" status
2. **Redirect to GMPays** → Customer completes payment on GMPays terminal
3. **Customer returns** → Plugin automatically checks payment status via API
4. **Order status updated** → Based on actual payment status from GMPays
5. **Customer sees result** → Updated order status on thank you page

### Status Updates

The plugin automatically updates order statuses:

- **Pending** → Initial state when payment is initiated
- **On-Hold** → Payment received, awaiting confirmation
- **Processing** → Payment confirmed, order being processed
- **Completed** → Order fulfilled
- **Failed** → Payment failed
- **Cancelled** → Payment cancelled by customer

### API Status Checking

The plugin uses GMPays API to check payment status:

- **Automatic checking** when customer returns to thank you page
- **Real-time status updates** based on actual payment data
- **Comprehensive logging** for debugging and monitoring
- **Fallback mechanisms** for reliability

## Troubleshooting

### Common Issues

1. **Orders not updating**: Check API credentials and debug logs
2. **Payment status unknown**: Use admin "Check Payment Status" button
3. **API connection failures**: Verify GMPays configuration and network access

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

## Security Features

- **RSA signature verification** for webhooks (when available)
- **Nonce verification** for AJAX requests
- **Input sanitization** for all user data
- **Secure API communication** with GMPays

## Support

For issues or questions:

1. Check the debug logs
2. Verify GMPays configuration
3. Test API connection manually
4. Contact support with log details

## Version History

- **v1.4.6**: API-based status checking implementation
- **v1.4.5**: Webhook-based architecture (deprecated due to GMPays limitations)
- **v1.4.4**: Previous version with URL parameter approach (deprecated)
- **v1.4.3**: Initial release

---

**Note**: This plugin is designed to work with GMPays' terminal-based payment flow. It uses API status checking as the primary method since GMPays doesn't reliably send webhooks or return URL parameters with the terminal endpoint.
