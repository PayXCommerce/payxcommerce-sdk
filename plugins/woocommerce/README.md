# PayXCommerce Gateway for WooCommerce

Developer-preview WooCommerce payment gateway for PayXCommerce hosted checkout.

## Features

- Hosted checkout redirect.
- HMAC API key authentication.
- Developer App Bearer token authentication.
- Test/live mode.
- Payment request creation from WooCommerce orders.
- Webhook verification and order status sync.
- Refund request support from WooCommerce admin.
- Redacted debug logging.

## Installation

1. Copy `payxcommerce-gateway.php` into `wp-content/plugins/payxcommerce-gateway/`.
2. Activate the plugin in WordPress Admin.
3. Go to WooCommerce → Settings → Payments → PayXCommerce.
4. Enter API credentials and webhook secret.
5. Set webhook URL in PayXCommerce merchant settings.
6. Run a test order.

## Webhook URL

WooCommerce displays the webhook URL in the plugin settings. It follows this format:

```text
https://your-store.example.com/?wc-api=payxcommerce
```

## Status Mapping

- `payment.success` → payment complete.
- `payment.failed` → failed.
- `payment.cancelled` / `payment.expired` → cancelled.
- `refund.success` → order note.
- `chargeback.created` → on-hold.

