# Idempotency

POST endpoints require idempotency keys so retries do not create duplicate payment requests or refund requests.

Recommended keys:

```text
woocommerce-order-{order_id}-attempt-{timestamp}
opencart-3-order-{order_id}-attempt-{attempt_number}
opencart-4-order-{order_id}-attempt-{attempt_number}
magento2-order-{order_id}-attempt-{attempt_number}
```

If an idempotency key is reused with a different payload, PayXCommerce returns an idempotency conflict.

