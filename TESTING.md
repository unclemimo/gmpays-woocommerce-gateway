# GMPays WooCommerce Gateway - Testing Guide

This guide will help you test the new webhook-based architecture to ensure it's working correctly.

## 🧪 Pre-Testing Checklist

Before running tests, ensure:

- [ ] Plugin is activated and configured
- [ ] GMPays webhook URL is set: `https://yoursite.com/wp-json/gmpays/v1/webhook`
- [ ] Debug mode is enabled in plugin settings
- [ ] Test orders are enabled in WooCommerce
- [ ] SSL certificate is valid (for production testing)

## 🔍 Test Scenarios

### 1. Basic Gateway Functionality

#### Test: Gateway Display
1. Go to your store's checkout page
2. Verify GMPays Credit Card appears as payment option
3. Check that gateway title and description display correctly

#### Test: Order Creation
1. Add items to cart
2. Proceed to checkout
3. Select GMPays Credit Card payment method
4. Complete checkout process
5. Verify order is created with "pending" status

**Expected Result**: Order created successfully with pending status

### 2. Payment Processing Flow

#### Test: Redirect to GMPays
1. Complete checkout with GMPays payment method
2. Verify redirect to GMPays payment terminal
3. Check that order ID is passed correctly

**Expected Result**: Customer redirected to GMPays with correct order data

#### Test: Return from GMPays (Success)
1. Complete payment on GMPays (use test card)
2. Return to your store via success URL
3. Check order status and notes

**Expected Result**: Order status updated to "on-hold" with payment note

#### Test: Return from GMPays (Failure)
1. Fail payment on GMPays (use declined card)
2. Return to your store via failure URL
3. Check order status and notes

**Expected Result**: Order status updated to "failed" with failure note

#### Test: Return from GMPays (Cancellation)
1. Cancel payment on GMPays
2. Return to your store via cancel URL
3. Check order status and notes

**Expected Result**: Order status updated to "cancelled" with cancellation note

### 3. Webhook Functionality

#### Test: Webhook Endpoint Accessibility
1. Test webhook URL: `https://yoursite.com/wp-json/gmpays/v1/webhook`
2. Verify endpoint responds (should return 200 OK)
3. Check for any error messages

**Expected Result**: Webhook endpoint accessible and responding

#### Test: Webhook Processing
1. Create a test order
2. Simulate webhook notification from GMPays
3. Check order status update
4. Verify order notes are added

**Expected Result**: Order status updated via webhook with proper notes

### 4. Admin Interface

#### Test: Payment Meta Box
1. Go to WooCommerce → Orders
2. Click on a GMPays order
3. Look for "GMPays Payment Details" meta box
4. Verify payment information displays correctly

**Expected Result**: Meta box shows payment details and status

#### Test: Manual Status Check
1. From order page, click "Check Payment Status" button
2. Verify AJAX request completes
3. Check for status update or error message

**Expected Result**: Button works and provides feedback

### 5. Fallback Mechanisms

#### Test: API Status Polling
1. Disable webhooks temporarily
2. Complete a test payment
3. Return to thank you page
4. Check if status is polled from API

**Expected Result**: Order status updated via API polling

#### Test: Error Handling
1. Test with invalid order ID
2. Test with missing payment data
3. Test with API connection failures
4. Verify error messages are user-friendly

**Expected Result**: Graceful error handling with helpful messages

## 🐛 Debugging Tests

### Enable Debug Mode
1. Go to WooCommerce → Settings → Payments → GMPays Credit Card
2. Check "Enable logging" option
3. Save changes

### Check Logs
1. Go to WooCommerce → Status → Logs
2. Look for `gmpays-gateway` log files
3. Review recent entries for errors or warnings

### Common Log Entries
- `GMPays DEBUG: handle_payment_return called for order ID: X`
- `GMPays DEBUG: Processing success return for order X`
- `GMPays DEBUG: Order status updated to on-hold`
- `GMPays ERROR: Failed to update order status`

## 🔧 Manual Testing Tools

### Test Webhook Endpoint
```bash
curl -X POST https://yoursite.com/wp-json/gmpays/v1/webhook \
  -H "Content-Type: application/json" \
  -d '{"test": "webhook"}'
```

### Test API Connection
1. Use admin "Check Payment Status" button
2. Check browser developer tools for AJAX requests
3. Verify response format and content

### Test Order Status Updates
1. Manually change order status in admin
2. Check if hooks are triggered
3. Verify order notes are added

## ✅ Success Criteria

A successful test run should demonstrate:

- [ ] Gateway displays correctly on checkout
- [ ] Orders are created with proper status
- [ ] Redirects to GMPays work correctly
- [ ] Returns from GMPays update order status
- [ ] Webhooks process notifications
- [ ] Admin interface shows payment details
- [ ] Manual status checking works
- [ ] Error handling is graceful
- [ ] Logging provides useful information

## 🚨 Common Issues

### Orders Not Updating
- Check webhook URL configuration
- Verify GMPays is sending notifications
- Check server logs for errors
- Test webhook endpoint accessibility

### Gateway Not Displaying
- Verify plugin is activated
- Check WooCommerce settings
- Ensure minimum amount requirements are met
- Check for JavaScript errors

### Webhook Failures
- Verify SSL certificate is valid
- Check server firewall settings
- Ensure webhook endpoint is accessible
- Verify signature verification is working

## 📞 Support

If tests fail or you encounter issues:

1. **Check debug logs** for specific error messages
2. **Verify configuration** matches documentation
3. **Test webhook endpoint** accessibility
4. **Contact support** with specific error details

## 🔄 Post-Testing

After successful testing:

1. **Disable test mode** if using test environment
2. **Review logs** for any warnings or errors
3. **Document any issues** found during testing
4. **Update configuration** if needed
5. **Monitor production** for any issues

---

**Note**: This testing guide covers the new webhook-based architecture. Previous URL parameter-based testing methods are no longer applicable.
