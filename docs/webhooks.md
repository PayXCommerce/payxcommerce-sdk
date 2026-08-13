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
- `payment.succeeded`
- `payment.failed`
- `payment.cancelled`
- `payment.expired`
- `refund.created`
- `refund.requested`
- `refund.approved`
- `refund.success`
- `refund.failed`
- `refund.rejected`
- `payment.refunded`
- `chargeback.created`
- `dispute.created`
- `customer.kyc.session_created`
- `customer.kyc.approved`
- `customer.kyc.declined`
- `customer.kyc.review`
- `customer.kyc.info_requested`
- `customer.kyc.info_submitted`

Legacy aliases may still be received by older integrations:

- `payment.canceled`
- `refund.succeeded`

Customer KYC webhook payloads are merchant-safe. They include PayXCommerce references, customer-safe status, provider key, gateway name, environment, and request references. They do not include raw identity-provider payloads, documents, or internal database IDs.
