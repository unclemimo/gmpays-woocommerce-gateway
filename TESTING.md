# GMPays WooCommerce Gateway Testing Guide

## Pre-Testing Checklist

Before testing, ensure you have:

1. ✅ Plugin activated and configured
2. ✅ GMPays control panel configured with correct URLs
3. ✅ Test payment credentials ready
4. ✅ Debug logging enabled
5. ✅ WooCommerce test mode enabled (if applicable)

## Test Scenarios

### 1. Basic Gateway Functionality

#### Test: Payment Method Display
- **Action**: Go to checkout page
- **Expected**: GMPays Credit Card option appears in payment methods
- **Check**: Payment method is visible and selectable

#### Test: Order Creation
- **Action**: Complete checkout with GMPays
- **Expected**: Order created with "pending payment" status
- **Check**: Order appears in WooCommerce admin

### 2. Return URL Processing

#### Test: Successful Payment Return
- **Action**: Simulate successful payment return
- **URL**: `https://yourdomain.com/?gmpays_success=1&order_id=123`
- **Expected**: 
  - Order status changes to "on-hold"
  - Note added: "Payment received via GMPays - Order placed on hold for confirmation"
  - No WordPress errors
- **Check**: Order status and notes in admin

#### Test: Failed Payment Return
- **Action**: Simulate failed payment return
- **URL**: `https://yourdomain.com/?gmpays_failure=1&order_id=123&reason=insufficient_funds`
- **Expected**:
  - Order status changes to "failed"
  - Note added: "Payment failed via GMPays: insufficient_funds"
  - Cart items restored (if applicable)
- **Check**: Order status, notes, and cart state

#### Test: Cancelled Payment Return
- **Action**: Simulate cancelled payment return
- **URL**: `https://yourdomain.com/?gmpays_cancelled=1&order_id=123`
- **Expected**:
  - Order status changes to "cancelled"
  - Note added: "Payment cancelled by customer via GMPays"
  - Cart items restored (if applicable)
- **Check**: Order status, notes, and cart state

### 3. Webhook Processing

#### Test: Webhook Notification
- **Action**: Send test webhook from GMPays
- **Endpoint**: `https://yourdomain.com/wp-json/gmpays/v1/webhook`
- **Expected**:
  - Webhook received and processed
  - Order status updated accordingly
  - Log entry created
- **Check**: Webhook logs and order status

#### Test: Signature Verification
- **Action**: Send webhook with invalid signature
- **Expected**:
  - Webhook rejected
  - Error logged
  - Order status unchanged
- **Check**: Webhook logs for rejection

### 4. Error Handling

#### Test: Invalid Order ID
- **Action**: Access return URL with non-existent order ID
- **Expected**: Graceful handling, no fatal errors
- **Check**: Error logs and user experience

#### Test: Missing Parameters
- **Action**: Access return URL without required parameters
- **Expected**: Graceful handling, no fatal errors
- **Check**: Error logs and user experience

## Manual Testing Steps

### Step 1: Configure Test Environment

1. **Enable Debug Mode**:
   - Go to WooCommerce > Settings > Payments
   - Click on GMPays Credit Card
   - Check "Enable logging"

2. **Set Minimum Test Amount**:
   - Ensure minimum amount is set to 1.00 EUR

3. **Verify URLs**:
   - Check all return URLs are accessible
   - Verify webhook endpoint responds

### Step 2: Create Test Order

1. **Add Product to Cart**:
   - Add any product meeting minimum amount
   - Proceed to checkout

2. **Select GMPays**:
   - Choose GMPays Credit Card payment method
   - Complete checkout

3. **Verify Order Creation**:
   - Check order status is "pending payment"
   - Note the order ID

### Step 3: Test Return URLs

1. **Test Success Return**:
   - Manually visit: `https://yourdomain.com/?gmpays_success=1&order_id=YOUR_ORDER_ID`
   - Verify order status changes to "on-hold"
   - Check notes are added

2. **Test Failure Return**:
   - Manually visit: `https://yourdomain.com/?gmpays_failure=1&order_id=YOUR_ORDER_ID&reason=test_failure`
   - Verify order status changes to "failed"
   - Check notes are added

3. **Test Cancellation Return**:
   - Manually visit: `https://yourdomain.com/?gmpays_cancelled=1&order_id=YOUR_ORDER_ID`
   - Verify order status changes to "cancelled"
   - Check notes are added

### Step 4: Test Webhooks

1. **Send Test Webhook**:
   - Use GMPays test environment
   - Send webhook to your endpoint
   - Verify processing

2. **Check Logs**:
   - Review webhook logs
   - Verify order updates

## Automated Testing

### Unit Tests

Run the following tests to verify functionality:

```bash
# Test gateway class
php -r "
require_once 'wp-content/plugins/gmpays-woocommerce-gateway/includes/class-wc-gateway-gmpays-credit-card.php';
echo 'Gateway class loaded successfully\n';
"

# Test webhook handler
php -r "
require_once 'wp-content/plugins/gmpays-woocommerce-gateway/includes/class-gmpays-webhook-handler.php';
echo 'Webhook handler loaded successfully\n';
"
```

### Integration Tests

1. **Test Order Flow**:
   - Create order → Process payment → Handle return → Verify status

2. **Test Webhook Flow**:
   - Send webhook → Process notification → Update order → Verify changes

## Debug Information

### Enable Debug Logging

```php
// In wp-config.php (for development only)
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// In plugin settings
// Enable "Enable logging" option
```

### Check Logs

1. **Gateway Logs**: `wp-content/uploads/wc-logs/gmpays-gateway-*.log`
2. **Webhook Logs**: `wp-content/uploads/wc-logs/gmpays-webhook-*.log`
3. **WordPress Debug Log**: `wp-content/debug.log`

### Common Debug Commands

```bash
# Check if plugin is active
wp plugin list | grep gmpays

# Check WooCommerce logs
wp wc log list

# Test webhook endpoint
curl -X POST https://yourdomain.com/wp-json/gmpays/v1/webhook

# Check order status
wp wc order list --limit=5
```

## Expected Results

### Successful Test Run

✅ **No WordPress Fatal Errors**
✅ **Orders Update Correctly**
✅ **Notes Added to Orders**
✅ **Webhooks Processed**
✅ **Logs Generated**
✅ **Cart State Managed**

### Common Issues and Solutions

#### Issue: Orders Not Updating
- **Check**: Return URL configuration in GMPays
- **Verify**: Plugin hooks are working
- **Debug**: Enable logging and check logs

#### Issue: WordPress Errors
- **Check**: Plugin compatibility with WordPress version
- **Verify**: No conflicting plugins
- **Debug**: Check error logs

#### Issue: Webhooks Not Working
- **Check**: Webhook endpoint accessibility
- **Verify**: Signature verification
- **Debug**: Test with simple payload

## Post-Testing

### Cleanup

1. **Delete Test Orders**: Remove test orders from admin
2. **Clear Logs**: Clean up test log files
3. **Reset Settings**: Restore production settings

### Documentation

1. **Update Configuration**: Document any changes needed
2. **Note Issues**: Record any problems encountered
3. **Plan Production**: Schedule production deployment

## Support

If you encounter issues during testing:

1. Check the debug logs first
2. Verify GMPays configuration
3. Test with minimal setup
4. Contact ElGrupito Development Team

## Production Checklist

Before going live:

- [ ] All tests pass
- [ ] Debug mode disabled
- [ ] Production GMPays credentials configured
- [ ] HTTPS enabled
- [ ] Monitoring configured
- [ ] Backup strategy in place
