# OpenCart 4 Integration

Plugin path:

```text
plugins/opencart4
```

OpenCart 4 uses a different extension structure from OpenCart 3, so it is maintained as a separate package.

## Expected Webhook Route

```text
index.php?route=extension/payxcommerce/payment/payxcommerce.webhook
```

The final route must be verified against the specific OpenCart 4 test version before production packaging.

