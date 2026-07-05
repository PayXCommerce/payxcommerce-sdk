# Refunds

Submit a refund request using the original PayXCommerce transaction reference.

Endpoint:

```text
POST /api/v1/refunds
```

Request:

```json
{
  "transaction_reference": "PXTRX-YYYYMMDD-XXXXXX",
  "amount": 25.00,
  "reason": "Customer requested partial refund"
}
```

Refunds may complete automatically when the merchant is permitted for auto-refunds. Otherwise, the request goes to admin review.

