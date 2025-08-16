# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2024-12-19

### 🚨 BREAKING CHANGES
- **Authentication Method Changed**: Switched from HMAC to RSA signatures for enhanced security
- **Configuration Update Required**: Users must regenerate RSA keys and update plugin settings
- **PHP Version Requirement**: Increased minimum PHP version from 7.2 to 7.4

### ✨ Added
- RSA-SHA256 signature generation for API requests
- RSA signature verification for webhook notifications
- `setup-gmpays.sh` script for automatic RSA key generation
- Enhanced security with proper certificate verification
- Better error handling and logging for RSA operations

### 🔧 Changed
- Updated `class-gmpays-api-client.php` to use RSA signatures instead of HMAC
- Updated `class-gmpays-webhook-handler.php` to verify RSA signatures
- Improved signature generation algorithm to match GMPays specification
- Enhanced webhook security with RSA verification

### 🐛 Fixed
- Inconsistent authentication methods between API client and webhook handler
- Security vulnerabilities in HMAC-based signature verification
- Missing proper error handling for cryptographic operations

### 📚 Updated
- README.md with new RSA configuration instructions
- Installation guide with RSA key generation steps
- Security documentation to reflect RSA implementation
- Version requirements and compatibility information

### 🔒 Security
- Replaced HMAC with RSA signatures for stronger security
- Added proper certificate verification for GMPays communications
- Enhanced webhook signature validation
- Improved cryptographic key management

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
