# PayXCommerce Python SDK

Python 3.9+ SDK for PayXCommerce API v1.

## Features

- HMAC API key authentication
- Developer App Bearer token authentication
- OAuth client credentials helper
- Payment requests, balance, transactions, refunds
- Signed webhook verification
- Webhook event constants and helper predicates
- Redaction helpers for safe logs

## Install

```bash
pip install payxcommerce
```

Local development install:

```bash
pip install -e packages/python-sdk
```

## HMAC Example

```python
from payxcommerce import Client, Config, HmacAuth

client = Client(Config(auth=HmacAuth("YOUR_PUBLIC_KEY", "YOUR_SECRET_KEY")))
response = client.payment_requests().create({
    "amount": 100,
    "currency": "USD",
    "purpose": "Order #1001",
    "webhook_url": "https://example.com/payxcommerce/webhook/order-1001",
    "ipn_events": ["payment.succeeded", "payment.failed"],
})
print(response["checkout_url"])
```

## Request-Level Webhooks

Request-level `webhook_url` is optional. Use it when one merchant account powers multiple stores or apps and one payment request needs callbacks delivered to a specific endpoint. If omitted, PayXCommerce uses the merchant default webhook URL from the dashboard.

## Webhooks

```python
from payxcommerce.webhooks.verifier import Verifier

payload = Verifier("YOUR_WEBHOOK_SECRET").verify(raw_body, headers)
```

## Examples

Package-local examples are available in `examples/`:

- `create_payment_request.py` — HMAC API key payment request.
- `oauth_bearer_payment_request.py` — Developer App OAuth client credentials followed by Bearer-token payment request.
- `webhook_verify.py` — Signed webhook verification and event handling.

The repository-level mirror is available in `../../examples/sdk-python`.
