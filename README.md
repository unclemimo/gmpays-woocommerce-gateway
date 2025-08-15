# GMPays WooCommerce Payment Gateway

A comprehensive WooCommerce payment gateway plugin that integrates GMPays payment processor for international credit card processing with multi-currency support.

## 🌟 Features

- **International Credit Card Processing**: Accept major credit cards (Visa, MasterCard, American Express, etc.)
- **Multi-Currency Support**: Full integration with WooCommerce Multi Currency plugin
- **Automatic Currency Conversion**: Converts local currency to USD for GMPays processing
- **Secure Webhook Integration**: Real-time order status updates via webhooks
- **Refund Support**: Process refunds directly from WooCommerce admin
- **Multilingual**: Spanish and English language support
- **Modular Architecture**: Designed to easily add more payment methods in the future
- **Debug Mode**: Comprehensive logging for troubleshooting
- **Test Mode**: Sandbox environment support for testing

## 📋 Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- SSL Certificate (required for production)
- GMPays merchant account with API credentials
- Composer (for dependency management)

## 🚀 Installation

See [INSTALLATION.md](INSTALLATION.md) for detailed installation instructions in English and Spanish.

### Quick Start

1. Install dependencies:
```bash
cd wp-content/plugins/gmpays-woocommerce-gateway
composer install
```

2. Activate the plugin in WordPress Admin

3. Configure at **WooCommerce → Settings → Payments → Credit Card (GMPays)**

4. Enter your GMPays API credentials

5. Configure webhooks in your GMPays dashboard

## 🔧 Configuration

### API Credentials

Obtain from your GMPays dashboard:
- **Project ID**: Your unique project identifier
- **API Key**: Authentication key for API requests
- **HMAC Key**: Key for signature verification

### Webhook Setup

Configure webhook URL in GMPays:
```
https://yourdomain.com/wp-json/gmpays/v1/webhook
```

### Currency Configuration

For non-USD stores, install and configure WooCommerce Multi Currency plugin to enable automatic currency conversion to USD.

## 💳 Supported Payment Methods

Currently supported:
- **Credit Cards**: Visa, MasterCard, American Express, Discover

Future support planned:
- PIX (Brazil)
- SPEI (Mexico)
- PSE (Colombia)
- Local payment methods for Latin America

## 🌍 Multi-Currency Support

The plugin seamlessly integrates with WooCommerce Multi Currency plugin:

- Displays prices in local currency
- Automatically converts to USD for payment processing
- Supports all major Latin American currencies:
  - 🇨🇴 COP (Colombian Peso)
  - 🇲🇽 MXN (Mexican Peso)
  - 🇦🇷 ARS (Argentine Peso)
  - 🇻🇪 VES (Venezuelan Bolívar)
  - 🇵🇪 PEN (Peruvian Sol)
  - 🇨🇱 CLP (Chilean Peso)
  - 🇧🇷 BRL (Brazilian Real)
  - 🇺🇾 UYU (Uruguayan Peso)
  - 🇪🇸 EUR (Euro)
  - 🇺🇸 USD (US Dollar)

## 🔐 Security Features

- **HMAC Signature Verification**: All webhooks are verified using HMAC-MD5 signatures
- **SSL/TLS Required**: Enforces secure connections in production
- **PCI Compliance**: Payment card data is handled by GMPays's PCI-compliant infrastructure
- **Secure API Communication**: All API calls use HTTPS with authentication

## 🧪 Testing

### Test Mode

1. Enable **Test Mode** in plugin settings
2. Use GMPays sandbox credentials
3. Process test payments without real transactions
4. Check logs for debugging information

### Debug Logging

Enable debug mode to log all GMPays interactions:
- Location: **WooCommerce → Status → Logs → gmpays**
- Includes API requests, responses, and webhook data

## 📝 Order Management

### Order Statuses

The plugin automatically updates order statuses:
- **Pending**: Payment initiated
- **Processing**: Payment successful
- **Failed**: Payment failed
- **Cancelled**: Payment cancelled by customer
- **Refunded**: Refund processed

### Admin Features

- View GMPays payment details in order admin
- Process refunds directly from WooCommerce
- Access transaction IDs and payment status
- Debug information available in meta box

## 🌐 Localization

The plugin includes translations for:
- 🇬🇧 English
- 🇪🇸 Spanish (Latin America)

Translation files are located in the `/languages` directory.

## 🏗️ Architecture

### Modular Design

The plugin is architected for extensibility:

```
gmpays-woocommerce-gateway/
├── includes/
│   ├── class-wc-gateway-gmpays-credit-card.php  # Credit card gateway
│   ├── class-gmpays-api-client.php              # API communication
│   ├── class-gmpays-currency-manager.php        # Currency conversion
│   ├── class-gmpays-webhook-handler.php         # Webhook processing
│   └── [Future payment method classes]
├── assets/
│   └── js/gmpays-checkout.js                    # Frontend scripts
├── languages/                                    # Translations
└── composer.json                                 # Dependencies
```

### Adding New Payment Methods

To add a new GMPays payment method:

1. Create new gateway class extending `WC_Payment_Gateway`
2. Add to `includes/` directory
3. Register in main plugin file
4. Configure specific settings and behavior

Example structure for future payment methods:
- `class-wc-gateway-gmpays-pix.php`
- `class-wc-gateway-gmpays-spei.php`
- `class-wc-gateway-gmpays-pse.php`

## 🔄 Webhook Events

The plugin handles the following webhook events:
- `payment` / `invoice.paid`: Payment successful
- `payment.failed` / `invoice.failed`: Payment failed
- `payment.cancelled` / `invoice.cancelled`: Payment cancelled
- `refund` / `invoice.refunded`: Refund processed

## 🛠️ API Integration

### GMPays API Endpoints

The plugin integrates with GMPays API v1:
- Create Invoice: `POST /api/invoice`
- Check Status: `POST /api/invoice/status`
- Process Refund: `POST /api/invoice/refund`
- Cancel Invoice: `POST /api/invoice/cancel`

### Authentication

Uses Bearer token authentication with HMAC-MD5 signature verification.

## 📊 Reporting

Payment data is stored as order metadata:
- `_gmpays_invoice_id`: GMPays invoice identifier
- `_gmpays_transaction_id`: Transaction identifier
- `_gmpays_payment_status`: Payment status
- `_gmpays_payment_completed_at`: Completion timestamp

## 🤝 Contributing

### Development Setup

1. Clone the repository
2. Install dependencies: `composer install`
3. Follow WordPress coding standards
4. Test thoroughly before submitting PR

### Code Standards

- Follow WordPress Coding Standards
- Add inline documentation
- Include unit tests for new features
- Update documentation as needed

## 📄 License

This plugin is licensed under GPL v2 or later.

## 🆘 Support

### For Plugin Support
- Contact ElGrupito development team
- Check the [INSTALLATION.md](INSTALLATION.md) guide
- Review debug logs for troubleshooting

### For GMPays API Support
- Email: support@gmpays.com
- Documentation: [https://cp.gmpays.com/apidoc](https://cp.gmpays.com/apidoc)

### For WooCommerce Support
- Documentation: [https://docs.woocommerce.com](https://docs.woocommerce.com)
- Community Forums: [https://wordpress.org/support/plugin/woocommerce/](https://wordpress.org/support/plugin/woocommerce/)

## 📈 Changelog

### Version 1.0.0 (Initial Release)
- Credit card payment processing via GMPays
- Multi-currency support with automatic USD conversion
- Webhook integration for real-time updates
- Refund support
- Spanish and English localization
- Debug logging
- Test/Production mode support

## 🔮 Roadmap

### Version 1.1.0 (Planned)
- PIX payment method (Brazil)
- SPEI payment method (Mexico)
- Enhanced currency conversion options

### Version 1.2.0 (Planned)
- PSE payment method (Colombia)
- Bank transfer support
- Recurring payments support

### Version 2.0.0 (Future)
- Mobile wallet integrations
- Advanced fraud detection
- Enhanced reporting dashboard

## 👥 Credits

Developed by ElGrupito Development Team for [ElGrupito.com](https://elgrupito.com)

Special thanks to:
- GMPays for payment processing services
- WooCommerce team for the e-commerce platform
- WordPress community for continuous support

---

**Note**: This plugin requires proper configuration of GMPays API credentials and may require additional setup for currency conversion. Please refer to the installation guide for detailed instructions.
