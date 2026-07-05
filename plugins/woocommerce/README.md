# PayXCommerce Gateway for WooCommerce

WooCommerce payment gateway for PayXCommerce hosted checkout.

## Structure

```text
payxcommerce-gateway.php        Plugin bootstrap
api/order-functions.php         Public order helper functions
includes/Admin/Settings.php     Gateway settings schema
includes/Api/SdkFactory.php     PayXCommerce PHP SDK client factory
includes/Gateway/Gateway.php    WooCommerce gateway implementation
includes/Order/                 Payload and order metadata helpers
includes/Webhook/Handler.php    Signed webhook/IPN processor
includes/Blocks/                WooCommerce Blocks payment method support
assets/                         Admin and checkout assets
```

## Features

- Hosted checkout redirect.
- PayXCommerce PHP SDK integration.
- HMAC API key authentication.
- Developer App Bearer token authentication.
- Credential validation on settings save.
- Configurable customer-facing brand name, title, description, and checkout labels.
- Test/live mode.
- Checkout availability checks for currency, billing country, minimum amount, and maximum amount.
- Classic checkout and WooCommerce Blocks support.
- Signed webhook/IPN verification.
- Duplicate webhook protection using order metadata.
- Payment success, failure, cancellation, expiry, refund, dispute, and chargeback order updates.
- Refund request support from WooCommerce admin.
- Redacted debug logging.
- HPOS compatibility declaration.

## Installation

1. Upload the `plugins/woocommerce` directory as a WordPress plugin.
2. If installing from source, run Composer in the plugin directory or package the SDK with the plugin.
3. Activate the plugin in WordPress Admin.
4. Go to WooCommerce → Settings → Payments → PayXCommerce.
5. Enter API credentials and webhook secret.
6. Copy the webhook URL into PayXCommerce merchant settings.
7. Run a test order.

## SDK Loading

The plugin loads the PayXCommerce PHP SDK from Composer when packaged. In this monorepo, it also supports local development loading from `packages/php-sdk/src`.

## Webhook URL

```text
https://your-store.example.com/?wc-api=payxcommerce
```
