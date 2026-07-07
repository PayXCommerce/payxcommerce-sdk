# PayXCommerce Node.js SDK

Node.js 18+ SDK for PayXCommerce API v1.

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
npm install @payxcommerce/payxcommerce
```

Local development install:

```bash
npm install ./packages/node-sdk
```

## HMAC Example

```js
const { Client, Config, HmacAuth } = require('@payxcommerce/payxcommerce');

const client = new Client(new Config({ auth: new HmacAuth('YOUR_PUBLIC_KEY', 'YOUR_SECRET_KEY') }));
const response = await client.paymentRequests().create({
  amount: 100,
  currency: 'USD',
  purpose: 'Order #1001',
  webhook_url: 'https://example.com/payxcommerce/webhook/order-1001',
  ipn_events: ['payment.succeeded', 'payment.failed']
});
console.log(response.checkout_url);
```

## Request-Level Webhooks

Request-level `webhook_url` is optional. Use it when one merchant account powers multiple stores or apps and one payment request needs callbacks delivered to a specific endpoint. If omitted, PayXCommerce uses the merchant default webhook URL from the dashboard.

## Examples

Package-local examples are available in `examples/`:

- `create-payment-request.js` — HMAC API key payment request.
- `oauth-bearer-payment-request.js` — Developer App OAuth client credentials followed by Bearer-token payment request.
- `webhook-verify.js` — Signed webhook verification and event handling.

The repository-level mirror is available in `../../examples/sdk-nodejs`.
