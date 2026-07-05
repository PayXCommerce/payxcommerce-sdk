# WooCommerce Integration

Plugin path:

```text
plugins/woocommerce
```

## Setup

1. Install the plugin in WordPress.
2. Go to WooCommerce → Settings → Payments → PayXCommerce.
3. Enable the gateway.
4. Select test or live environment.
5. Enter HMAC API keys or Developer App credentials.
6. Enter webhook secret.
7. Copy the webhook URL into PayXCommerce merchant settings.
8. Run a test order.

## Payment Flow

1. Customer selects PayXCommerce.
2. WooCommerce creates a PayXCommerce payment request.
3. Customer redirects to hosted checkout.
4. Webhook updates WooCommerce order status.

## Security

Do not expose secret keys in theme templates, frontend JavaScript, or public logs.

