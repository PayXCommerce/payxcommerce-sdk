# Webhooks

PayXCommerce sends signed webhooks to merchant endpoints.

Expected headers:

```text
X-PXC-Event-ID
X-PXC-Timestamp
X-PXC-Signature
X-PXC-Schema-Version
```

Verification message:

```text
event_id + "." + canonical_json_body
```

Signature:

```text
hash_hmac("sha256", message, webhook_secret)
```

Webhook handlers must:

- Read the raw request body.
- Verify signature before updating local orders.
- Reject old timestamps.
- Store event IDs and ignore duplicates.
- Return HTTP 200 only after safe processing.

Common event types:

- `payment.success`
- `payment.failed`
- `payment.cancelled`
- `payment.expired`
- `refund.success`
- `chargeback.created`

