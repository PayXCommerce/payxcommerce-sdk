# Payment Requests

Create a payment request to generate a PayXCommerce hosted checkout URL.

Endpoint:

```text
POST /api/v1/payment-requests
```

Required fields:

- `amount`
- `currency`
- `purpose`
- `customer.name`
- `customer.email`
- `customer.country`

Useful optional fields:

- `merchant_reference`
- `merchant_order_id`
- `success_url`
- `failed_url`
- `cancel_url`
- `webhook_url`
- `ipn_events`
- `metadata`
- `is_test`

The response includes `request_number`, `invoice_number`, `checkout_url`, and `status`.

