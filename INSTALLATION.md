# GMPays WooCommerce Gateway - Installation Guide

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Installation Methods](#installation-methods)
3. [Initial Configuration](#initial-configuration)
4. [GMPays Account Setup](#gmpays-account-setup)
5. [Testing Your Integration](#testing-your-integration)
6. [Troubleshooting](#troubleshooting)

## Prerequisites

Before installing the GMPays WooCommerce Gateway, ensure you have:

- ✅ WordPress 5.0 or higher
- ✅ WooCommerce 4.0 or higher
- ✅ PHP 7.2 or higher
- ✅ SSL certificate installed (required for payment processing)
- ✅ GMPays merchant account ([Sign up at gmpays.com](https://gmpays.com))

### Recommended Plugins

For stores using currencies other than USD:
- [WooCommerce Multi Currency](https://wordpress.org/plugins/woocommerce-multi-currency/) - For automatic USD conversion

## Installation Methods

### Method 1: Install from GitHub Release (Recommended)

1. **Download the Plugin**
   - Go to [GitHub Releases](https://github.com/unclemimo/gmpays-woocommerce-gateway/releases)
   - Download the latest `gmpays-woocommerce-gateway-vX.X.X.zip` file

2. **Upload to WordPress**
   - Log in to your WordPress admin panel
   - Navigate to **Plugins → Add New**
   - Click **Upload Plugin**
   - Choose the downloaded ZIP file
   - Click **Install Now**

3. **Activate the Plugin**
   - After installation, click **Activate Plugin**
   - Or go to **Plugins → Installed Plugins** and activate it there

### Method 2: Manual Installation via FTP

1. **Download and Extract**
   - Download the plugin from GitHub
   - Extract the ZIP file on your computer

2. **Upload via FTP**
   - Connect to your server using FTP client
   - Navigate to `/wp-content/plugins/`
   - Upload the entire `gmpays-woocommerce-gateway` folder

3. **Activate in WordPress**
   - Log in to WordPress admin
   - Go to **Plugins → Installed Plugins**
   - Find "GMPays WooCommerce Gateway" and click **Activate**

### Method 3: Install from Source (For Developers)

1. **Clone the Repository**
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/unclemimo/gmpays-woocommerce-gateway.git
   ```

2. **Install Dependencies**
   ```bash
   cd gmpays-woocommerce-gateway
   composer install --no-dev --optimize-autoloader
   ```

3. **Activate the Plugin**
   - Go to WordPress admin → **Plugins**
   - Activate "GMPays WooCommerce Gateway"

## Initial Configuration

### Step 1: Access Plugin Settings

1. Navigate to **WooCommerce → Settings**
2. Click on the **Payments** tab
3. Find **Credit Card (GMPays)** in the list
4. Click **Manage** to configure

### Step 2: Basic Settings

Configure these essential settings:

| Setting | Description | Example |
|---------|-------------|---------|
| **Enable/Disable** | Enable the payment gateway | ✅ Checked |
| **Title** | Name shown to customers at checkout | "Credit Card (GMPays)" |
| **Description** | Payment method description | "Pay securely with your credit card" |

### Step 3: Enable Debug Mode (Optional)

During initial setup, enable debug logging:
- Check **Enable logging**
- Logs will be available at **WooCommerce → Status → Logs → gmpays**

## GMPays Account Setup

### Step 1: Access Your GMPays Control Panel

1. Log in to [cp.gmpays.com](https://cp.gmpays.com)
2. Navigate to your project settings

### Step 2: Obtain Your Credentials

In your GMPays control panel, locate:

1. **Project ID**
   - Found under "ID IN PROJECT"
   - Example: `603`
   - This is your unique project identifier

2. **HMAC Key**
   - Click "Regenerate HMAC Key" button to generate
   - Copy the generated key
   - Keep this secure - it's used for signature verification

### Step 3: Configure Webhook URLs

In the GMPays control panel, configure these URLs (replace `yourdomain.com` with your actual domain):

#### Success URL
```
https://yourdomain.com/checkout/order-received/
```
*URL перенаправления пользователя в случае успешной оплаты*

#### Failure URL
```
https://yourdomain.com/checkout/
```
*URL перенаправления пользователя в случае неуспешной оплаты*

#### Notification URL (Webhook)
```
https://yourdomain.com/wp-json/gmpays/v1/webhook
```
*URL для оповещений о выплатах*

### Step 4: Enter Credentials in WooCommerce

Back in WooCommerce GMPays settings:

1. **Project ID**: Enter your GMPays Project ID (e.g., `603`)
2. **HMAC Key**: Enter your GMPays HMAC Key
3. **Webhook Secret**: Leave blank (uses HMAC Key by default)
4. Click **Save changes**

## Multi-Currency Setup

If your store uses currencies other than USD:

### Step 1: Install WooCommerce Multi Currency

1. Go to **Plugins → Add New**
2. Search for "WooCommerce Multi Currency"
3. Install and activate the plugin

### Step 2: Configure Currency Settings

1. Go to **WooCommerce → Multi Currency**
2. Enable USD as a currency
3. Set up exchange rates for your local currencies
4. Configure display options

### Step 3: Verify Integration

The GMPays gateway will automatically:
- Display prices in customer's selected currency
- Convert amounts to USD for payment processing
- Update order totals with converted amounts

## Testing Your Integration

### Step 1: Create a Test Order

1. Add a product to your cart
2. Proceed to checkout
3. Select "Credit Card (GMPays)" as payment method
4. You should see the GMPays payment form

### Step 2: Process a Test Payment

GMPays processes all payments in production mode. To test:

1. Use a real credit card for a small amount
2. Complete the payment process
3. Verify the order status updates correctly

### Step 3: Verify Webhook Communication

1. Check that order status updates automatically
2. Review debug logs for webhook notifications
3. Confirm payment details appear in order admin

### Step 4: Test Order Management

1. View the order in **WooCommerce → Orders**
2. Check GMPays payment details in the order meta box
3. Verify transaction ID is recorded

## Troubleshooting

### Gateway Not Appearing at Checkout

**Possible Causes:**
- Plugin not activated
- Gateway not enabled in settings
- Missing credentials

**Solutions:**
1. Verify plugin is activated
2. Check gateway is enabled in settings
3. Ensure Project ID and HMAC Key are entered
4. Clear WordPress cache

### Payment Failures

**Error: "Payment gateway configuration error"**
- Verify Project ID is correct
- Regenerate and update HMAC Key
- Check debug logs for specific errors

**Error: "Currency not supported"**
- Install WooCommerce Multi Currency plugin
- Ensure USD is enabled
- Check currency conversion settings

### Webhook Issues

**Orders Not Updating Automatically:**
1. Verify webhook URL in GMPays control panel:
   ```
   https://yourdomain.com/wp-json/gmpays/v1/webhook
   ```
2. Check SSL certificate is valid
3. Review webhook logs in debug mode
4. Ensure WordPress REST API is enabled

**Signature Verification Failures:**
- Regenerate HMAC Key in GMPays
- Update key in WooCommerce settings
- Ensure no extra spaces in credentials

### Debug Information

Enable debug logging to troubleshoot:

1. **Enable Debug Mode**
   - Go to gateway settings
   - Check "Enable logging"
   - Save changes

2. **View Logs**
   - Navigate to **WooCommerce → Status → Logs**
   - Select log file starting with `gmpays`
   - Review for error messages

3. **Common Log Entries**
   - `SDK initialized successfully` - Good connection
   - `Missing required credentials` - Check Project ID/HMAC Key
   - `Signature verification failed` - HMAC Key mismatch
   - `Invoice created successfully` - Payment initiated

## Security Best Practices

1. **Keep Credentials Secure**
   - Never share HMAC Key
   - Regenerate keys if compromised
   - Use strong passwords for GMPays account

2. **SSL Certificate**
   - Ensure SSL is properly configured
   - Use HTTPS for all pages
   - Keep certificate up to date

3. **Regular Updates**
   - Keep WordPress updated
   - Update WooCommerce regularly
   - Monitor for plugin updates

4. **Monitor Transactions**
   - Review orders regularly
   - Check for unusual patterns
   - Enable admin notifications

## Getting Help

If you encounter issues:

1. **Check Documentation**
   - Review this installation guide
   - Read the [README](README.md) file
   - Check [GMPays API docs](https://cp.gmpays.com/apidoc)

2. **Debug Mode**
   - Enable logging in settings
   - Review error messages
   - Check webhook responses

3. **Support Channels**
   - Open [GitHub issue](https://github.com/unclemimo/gmpays-woocommerce-gateway/issues)
   - Contact GMPays support for account issues
   - WooCommerce community forums

## Next Steps

After successful installation:

1. **Process Test Transaction**
   - Make a small test purchase
   - Verify payment completes
   - Check order status updates

2. **Configure Additional Settings**
   - Set up email notifications
   - Customize checkout messages
   - Configure order statuses

3. **Monitor Performance**
   - Review transaction logs
   - Check conversion rates
   - Monitor for errors

---

**Need Help?** Open an issue on [GitHub](https://github.com/unclemimo/gmpays-woocommerce-gateway/issues)