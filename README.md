# GMPays WooCommerce Payment Gateway

![Version](https://img.shields.io/badge/version-1.2.1-blue.svg)
![WooCommerce](https://img.shields.io/badge/WooCommerce-4.0%2B-purple.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)

A robust WooCommerce payment gateway plugin for processing international credit card payments through GMPays payment processor using RSA signatures, specifically designed for Latin American e-commerce.

## 🌟 Features

- ✅ **International Credit Card Processing** - Accept Visa, MasterCard, American Express, and more
- 💱 **Multi-Currency Support** - Automatic currency conversion to USD using WooCommerce Multi Currency plugin
- 🌍 **Localization Ready** - Spanish and English translations included
- 🔒 **Secure RSA Signatures** - RSA signature verification for payment notifications and API requests
- 📊 **Comprehensive Logging** - Detailed debug logs for troubleshooting
- 🎨 **Modern Architecture** - Modular design for easy extension with additional payment methods

## 📋 Requirements

- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.4 or higher
- [WooCommerce Multi Currency](https://wordpress.org/plugins/woocommerce-multi-currency/) plugin (recommended for non-USD stores)
- GMPays merchant account

## 🚀 Installation

### From GitHub Release

1. Download the latest release ZIP file from [GitHub Releases](https://github.com/unclemimo/gmpays-woocommerce-gateway/releases)
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**
3. Select the downloaded ZIP file and click **Install Now**
4. Click **Activate Plugin**

### Manual Installation

1. Clone or download this repository
2. If installing from source, install dependencies:
   ```bash
   composer install --no-dev
   ```
3. Upload the `gmpays-woocommerce-gateway` folder to `/wp-content/plugins/`
4. Activate the plugin through the WordPress admin panel

## ⚙️ Configuration

### 1. Generate RSA Keys

**Important**: This plugin now uses RSA signatures instead of HMAC. You need to generate RSA keys:

1. **Run the setup script** (included in the plugin):
   ```bash
   cd wp-content/plugins/gmpays-woocommerce-gateway/
   chmod +x setup-gmpays.sh
   ./setup-gmpays.sh
   ```

2. **The script will generate**:
   - `private_key.pem` - Your private key (keep secure!)
   - `public_key.pem` - Your public key (upload to GMPays)

### 2. Configure GMPays Control Panel

1. **Log in to GMPays**: [cp.gmpays.com](https://cp.gmpays.com)
2. **Go to Signatures page**: [cp.gmpays.com/project/sign](https://cp.gmpays.com/project/sign)
3. **Upload your public key**:
   - Copy the entire content of `public_key.pem` (including BEGIN/END lines)
   - Paste it in the "Public key" field
   - Click "Generate signature HMAC" to regenerate the HMAC key
4. **Note your Project ID** (shown as "ID IN PROJECT", e.g., `603`)

### 3. Configure Plugin Settings

1. Navigate to **WooCommerce → Settings → Payments**
2. Find **Credit Card (GMPays)** and click **Manage**
3. Configure the following:

#### API Credentials
- **API URL**: Your GMPays API URL (usually `https://paygate.gamemoney.com`)
- **Project ID**: Your GMPays Project ID (e.g., `603`)
- **RSA Private Key**: Copy the entire content of `private_key.pem` (including BEGIN/END lines)

### 4. Configure GMPays Webhook URLs

In your GMPays control panel, configure these URLs:

- **Success URL (URL перенаправления пользователя в случае успешной оплаты)**:
  ```
  https://elgrupito.com/order-received/order-received/
  ```

- **Failure URL (URL перенаправления пользователя в случае неуспешной оплаты)**:
  ```
  https://elgrupito.com/checkout/
  ```

- **Notification URL (URL для оповещений о выплатах)**:
  ```
  https://yourdomain.com/wp-json/gmpays/v1/webhook
  ```

### 5. Currency Configuration

For non-USD stores, install and configure [WooCommerce Multi Currency](https://docs.villatheme.com/?item=woocommerce-multi-currency) plugin to enable automatic currency conversion to USD.

## 💳 Supported Payment Methods

Currently supported:
- **Credit Cards**: Visa, MasterCard, American Express, Discover

Future support planned:
- PIX (Brazil)
- SPEI (Mexico)
- PSE (Colombia)
- Other local payment methods for Latin America

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

- **RSA Signature Verification**: All webhooks and API requests use RSA-SHA256 signatures
- **SSL/TLS Required**: Enforces secure connections
- **PCI Compliance**: Payment card data is handled by GMPays's PCI-compliant infrastructure
- **Secure API Communication**: All API calls use HTTPS with RSA authentication

## 📝 Order Management

### Order Statuses

The plugin automatically updates order statuses:
- **Pending**: Payment initiated
- **Processing**: Payment successful
- **Failed**: Payment failed
- **Cancelled**: Payment cancelled by customer
- **On Hold**: Awaiting payment confirmation

### Admin Features

- View GMPays payment details in order admin
- Access transaction IDs and payment status
- Debug information available in meta box
- Comprehensive logging for troubleshooting

## 🌐 Localization

The plugin includes translations for:
- 🇬🇧 English
- 🇪🇸 Spanish (Latin America)

## 🐛 Debugging

### Enable Debug Logging

1. In plugin settings, check **Enable logging**
2. View logs at **WooCommerce → Status → Logs**
3. Select log file starting with `gmpays`

### Common Issues

#### Gateway Not Appearing at Checkout
- Ensure plugin is activated
- Verify Project ID and RSA Private Key are configured
- Check that WooCommerce Multi Currency is installed (if using non-USD currency)

#### Payment Failures
- Verify webhook URLs are correctly configured in GMPays control panel
- Check debug logs for specific error messages
- Ensure SSL certificate is valid
- Verify RSA keys are properly generated and uploaded

#### RSA Key Issues
- Ensure private key is in correct PEM format (includes BEGIN/END lines)
- Verify public key was uploaded to GMPays Signatures page
- Check that HMAC key was regenerated after uploading public key

#### Currency Conversion Issues
- Install WooCommerce Multi Currency plugin
- Configure exchange rates in the plugin
- Ensure USD is enabled as a currency

## 🔄 Webhook Events

The plugin handles the following webhook events from GMPays:
- Payment success confirmations
- Payment failure notifications
- Payment status updates

All webhooks are verified using RSA signatures for security.

## 📦 Plugin Structure

```
gmpays-woocommerce-gateway/
├── includes/
│   ├── class-wc-gateway-gmpays-credit-card.php  # Main gateway class
│   ├── class-gmpays-api-client.php              # GMPays API wrapper with RSA
│   ├── class-gmpays-webhook-handler.php         # Webhook processing with RSA
│   ├── class-gmpays-currency-manager.php        # Currency conversion
│   ├── class-gmpays-activator.php               # Plugin activation
│   └── class-gmpays-deactivator.php             # Plugin deactivation
├── assets/
│   └── js/
│       └── gmpays-checkout.js                   # Frontend JavaScript
├── languages/                                    # Translation files
├── vendor/                                       # Composer dependencies
├── setup-gmpays.sh                              # RSA key generation script
└── gmpays-woocommerce-gateway.php              # Main plugin file
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 🆘 Support

For issues or questions:
1. Check the [documentation](https://github.com/unclemimo/gmpays-woocommerce-gateway)
2. Review [common issues](#common-issues)
3. Open a [GitHub issue](https://github.com/unclemimo/gmpays-woocommerce-gateway/issues)

## 📚 Resources

- [GMPays API Documentation](https://cp.gmpays.com/apidoc)
- [WooCommerce Payment Gateway API](https://woocommerce.github.io/code-reference/classes/WC-Payment-Gateway.html)
- [WooCommerce Multi Currency Documentation](https://docs.villatheme.com/?item=woocommerce-multi-currency)

## 🏷️ Version History

### v1.2.0 (Latest) - RSA Authentication Update
- **BREAKING CHANGE**: Switched from HMAC to RSA signatures for enhanced security
- Updated API client to use RSA-SHA256 signatures
- Updated webhook handler to verify RSA signatures
- Added RSA key generation script (`setup-gmpays.sh`)
- Improved security with proper certificate verification
- Better error handling and logging for RSA operations

### v1.1.0
- Updated authentication to use Project ID and HMAC Key only
- Removed sandbox mode (not available in GMPays)
- Improved webhook configuration instructions
- Better integration with GMPays SDK

### v1.0.0
- Initial release
- Credit card payment processing
- Multi-currency support
- Webhook handling
- Spanish/English localization

---

**Developed for [ElGrupito.com](https://elgrupito.com)** 🛒