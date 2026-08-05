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
- `environment` (`test` or `live`)
- `is_test`

Environment mode is controlled by the credential used for the API call:

- Test API keys or test Developer App tokens create sandbox payment requests.
- Live API keys or live Developer App tokens create live payment requests.
- If `environment` or legacy `is_test` is supplied, it must match the credential mode.
- Sandbox requests are for integration validation and do not post live balances, settlements, commissions, accounting, risk scoring, or standard financial reports.

The response includes `request_number`, `invoice_number`, `checkout_url`, `status`, `environment`, and `is_test`.
