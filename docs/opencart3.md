# OpenCart 3 Integration

Plugin path:

```text
plugins/opencart3
```

## Admin Location

Extensions → Extensions → Payments → PayXCommerce

## Setup

1. Upload the `upload/` folder contents into the OpenCart root.
2. Install PayXCommerce under Payments.
3. Enter API base URL, public key, secret key, webhook secret, and status mappings.
4. Enable the extension.
5. Place a test order.

## Webhook URL

```text
https://store.example.com/index.php?route=extension/payment/payxcommerce/webhook
```

## Notes

OpenCart 3 package work should add install/uninstall SQL for extension event logs before production release.

