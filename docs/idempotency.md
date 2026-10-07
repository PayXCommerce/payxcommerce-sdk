# Idempotency

POST endpoints require idempotency keys so retries do not create duplicate payment requests or refund requests.

Recommended keys:

```text
woocommerce-order-{order_id}-{environment}
opencart-3-order-{order_id}
opencart-4-order-{order_id}
magento2-order-{order_id}-{environment}
woocommerce-refund-{order_id}-{operation_fingerprint}
```

If an idempotency key is reused with a different payload, PayXCommerce returns an idempotency conflict.

Persist/reuse the operation key and returned checkout reference after transport uncertainty. Never use the current time as the retry identity. Create a new key only after the earlier operation is conclusively cancelled/expired or an operator/customer explicitly starts a new attempt.
