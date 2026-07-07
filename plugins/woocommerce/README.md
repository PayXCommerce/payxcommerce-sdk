# PayXCommerce Gateway for WooCommerce

WooCommerce payment gateway for PayXCommerce hosted checkout.

The plugin bundles the PayXCommerce PHP SDK so it can be installed through the normal WordPress plugin upload flow without requiring Composer on the merchant site.

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
sdk/payxcommerce-php/src/       Bundled PayXCommerce PHP SDK
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
- Per-order request-level webhook URL in each hosted checkout request.
- Signed webhook/IPN verification.
- Duplicate webhook protection using order metadata.
- Payment success, failure, cancellation, expiry, refund, dispute, and chargeback order updates.
- Refund request support from WooCommerce admin.
- Redacted debug logging.
- HPOS compatibility declaration.

## Installation

1. Upload the `plugins/woocommerce` directory as a WordPress plugin.
2. If installing from source, keep the bundled `sdk/payxcommerce-php/src` directory in place, or run Composer in the plugin directory.
3. Activate the plugin in WordPress Admin.
4. Go to WooCommerce → Settings → Payments → PayXCommerce.
5. Enter API credentials and webhook secret.
6. Copy the webhook URL into PayXCommerce merchant settings as a fallback endpoint.
7. Run a test order.

## SDK Loading

The plugin loads the bundled PayXCommerce PHP SDK first, then falls back to Composer, then to the monorepo development SDK path.

## Webhook URL

```text
https://your-store.example.com/?wc-api=payxcommerce
```

The plugin also sends this URL as `webhook_url` on every payment request, so PayXCommerce can route callbacks for that order directly back to the WooCommerce store. The dashboard webhook URL remains useful as a merchant-level fallback when a request is created without its own webhook URL.
