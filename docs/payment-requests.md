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

Recommended customer fields:

- `customer.mobile`
- `customer.address`
- `customer.city`

Some PayXCommerce gateways may require customer identity verification before payment can continue. The hosted checkout page decides this from the selected gateway, amount threshold, currency, merchant policy, and KYC provider settings. SDKs and plugins should send complete customer data and redirect the customer to `checkout_url`; do not try to perform provider KYC inside the ecommerce plugin unless PayXCommerce publishes a provider-specific extension flow.

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

The response includes `request_number`, `invoice_number`, `checkout_url`, `status`, `environment`, and `is_test`. If customer KYC is required, that status is shown and enforced on hosted checkout before the final gateway payment starts.
