# GMPays WooCommerce Gateway Configuration Guide

## Overview

This plugin integrates GMPays payment processor with WooCommerce, providing secure credit card processing with support for multiple currencies and dual authentication (HMAC/RSA).

## Installation

1. Upload the plugin to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Settings > Payments to configure the gateway

## Configuration

### Basic Settings

1. **Enable/Disable**: Toggle the gateway on/off
2. **Title**: Display name shown to customers during checkout
3. **Description**: Payment method description visible to customers
4. **API URL**: Your GMPays API endpoint (default: https://paygate.gamemoney.com)
5. **Project ID**: Your GMPays Project ID from the control panel

### Authentication

Choose between two authentication methods:

#### HMAC Authentication
- **HMAC Key**: Your HMAC secret key from GMPays
- **Security**: High - uses cryptographic hash for request signing

#### RSA Authentication (Recommended)
- **Private Key**: Your RSA private key in PEM format
- **Security**: Highest - uses asymmetric encryption for maximum security

### Advanced Settings

- **Minimum Amount**: Minimum order amount in EUR (GMPays requirement)
- **Debug Log**: Enable detailed logging for troubleshooting

## GMPays Control Panel Configuration

### Return URLs Configuration

Configure these URLs in your GMPays control panel:

#### Success URL (URL перенаправления пользователя в случае успешной оплаты)
```
https://yourdomain.com/?gmpays_success=1&order_id={order_id}
```

#### Failure URL (URL перенаправления пользователя в случае неуспешной оплаты)
```
https://yourdomain.com/?gmpays_failure=1&order_id={order_id}
```

#### Cancel URL (URL перенаправления пользователя при отмене оплаты)
```
https://yourdomain.com/?gmpays_cancelled=1&order_id={order_id}
```

#### Notification URL (URL для оповещений о выплатах)
```
https://yourdomain.com/wp-json/gmpays/v1/webhook
```

### Important Notes

- **Replace `{order_id}`**: GMPays will automatically replace this placeholder with the actual order ID
- **Use HTTPS**: Always use HTTPS for production environments
- **Domain Verification**: Ensure your domain matches exactly what's configured in GMPays

## How It Works

### Payment Flow

1. **Customer Checkout**: Customer selects GMPays payment method and completes checkout
2. **Order Creation**: WooCommerce creates order with "pending payment" status
3. **Redirect to GMPays**: Customer is redirected to GMPays payment page
4. **Payment Processing**: Customer completes payment on GMPays
5. **Return to Store**: Customer returns to your store via configured return URLs
6. **Order Processing**: Plugin processes return and updates order status accordingly

### Return URL Processing

The plugin automatically handles all return scenarios:

- **Success**: Order marked as "on-hold" for manual review
- **Failure**: Order marked as "failed" with reason
- **Cancellation**: Order marked as "cancelled" by customer

### Webhook Notifications

GMPays sends webhook notifications to update order statuses in real-time. The webhook handler:

- Verifies signatures using RSA authentication
- Updates order statuses automatically
- Logs all transactions for audit purposes

## Troubleshooting

### Common Issues

#### Return URLs Not Working
- Verify URLs are configured correctly in GMPays control panel
- Check that your domain matches exactly
- Ensure HTTPS is used for production

#### Orders Not Updating
- Check debug logs for error messages
- Verify webhook endpoint is accessible
- Confirm signature verification is working

#### Payment Method Not Showing
- Check minimum amount requirements
- Verify currency compatibility
- Ensure gateway is enabled in WooCommerce settings

### Debug Mode

Enable debug mode to troubleshoot issues:

1. Go to WooCommerce > Settings > Payments
2. Click on GMPays Credit Card gateway
3. Check "Enable logging" option
4. Check logs at WooCommerce > Status > Logs

### Log Locations

- **Gateway Logs**: `wp-content/uploads/wc-logs/gmpays-gateway-*.log`
- **Webhook Logs**: `wp-content/uploads/wc-logs/gmpays-webhook-*.log`

## Security Considerations

### Best Practices

1. **Use RSA Authentication**: Provides highest security level
2. **Keep Keys Secure**: Never share private keys or HMAC secrets
3. **HTTPS Only**: Always use HTTPS in production
4. **Regular Updates**: Keep plugin updated to latest version
5. **Monitor Logs**: Regularly check logs for suspicious activity

### Key Management

- Store private keys securely
- Rotate keys periodically
- Use different keys for test and production environments
- Never commit keys to version control

## Support

For technical support:

1. Check the debug logs for error messages
2. Verify GMPays control panel configuration
3. Test with small amounts first
4. Contact ElGrupito Development Team

## Changelog

See `CHANGELOG.md` for detailed version history and updates.
