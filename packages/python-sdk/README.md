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

Developer preview note: until published, install from this folder:

```bash
pip install -e packages/python-sdk
```

## HMAC Example

```python
from payxcommerce import Client, Config, HmacAuth

client = Client(Config(auth=HmacAuth("YOUR_PUBLIC_KEY", "YOUR_SECRET_KEY")))
response = client.payment_requests().create({"amount": 100, "currency": "USD", "purpose": "Order #1001"})
print(response["checkout_url"])
```

## Webhooks

```python
from payxcommerce.webhooks.verifier import Verifier

payload = Verifier("YOUR_WEBHOOK_SECRET").verify(raw_body, headers)
```
