# WooCommerce M-Pesa Tanzania (Vodacom)

A simple, production-ready WooCommerce payment gateway for accepting Vodacom M-Pesa payments in Tanzania.

## Features

- ✅ Vodacom M-Pesa STK Push (Lipa na M-Pesa Online)
- ✅ Real-time payment confirmation via webhooks
- ✅ Secure API key storage (environment variables)
- ✅ Automatic order status updates
- ✅ Customer payment confirmation emails
- ✅ Complete transaction logging
- ✅ Support for both Sandbox and Production
- ✅ Simple, developer-friendly codebase

## Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- Vodacom Developer Account with M-Pesa credentials

## Installation

1. Download and extract the plugin to `/wp-content/plugins/wc-mpesa-tanzania/`
2. Activate the plugin in WordPress Admin > Plugins
3. Configure credentials in WordPress Admin > WooCommerce > Settings > Payments > M-Pesa (Vodacom - Tanzania)

## Configuration

### Step 1: Get Vodacom Credentials

1. Sign up at [Vodacom Developer Portal](https://developer.vodacom.co.tz/)
2. Create a new app to get:
   - **API Key** (Consumer Key)
   - **API Secret** (Consumer Secret)
3. Request M-Pesa integration and get:
   - **Business Shortcode** (e.g., 012345)
   - **M-Pesa Passkey**

### Step 2: Add Credentials to wp-config.php (Recommended)

For better security, add your credentials to `wp-config.php`:

```php
// Vodacom M-Pesa Tanzania Configuration
define('WCMPESA_TZ_ENV', 'production'); // or 'sandbox' for testing

// These will be used automatically if saved in WooCommerce settings
// But can also be set here for enhanced security