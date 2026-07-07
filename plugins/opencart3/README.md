# PayXCommerce OpenCart 3 Payment Extension

OpenCart 3 hosted checkout extension.

## Target

- OpenCart 3.x
- Payment extension location: Extensions → Extensions → Payments → PayXCommerce

## Package Structure

```text
upload/
├── admin/controller/extension/payment/payxcommerce.php
├── admin/language/en-gb/extension/payment/payxcommerce.php
├── admin/view/template/extension/payment/payxcommerce.twig
├── catalog/controller/extension/payment/payxcommerce.php
├── catalog/language/en-gb/extension/payment/payxcommerce.php
├── catalog/model/extension/payment/payxcommerce.php
├── catalog/view/theme/default/template/extension/payment/payxcommerce.twig
└── system/library/payxcommerce.php
```

## Features

- Shared PayXCommerce API client library for HMAC, Developer App Bearer token, hosted checkout creation, and signed webhook verification.
- Credential validation before enabling saved settings.
- Configurable public brand name, payment title, description, and checkout button text.
- Currency, billing country, geo-zone, minimum total, and maximum total availability checks.
- Hosted checkout redirect from OpenCart orders.
- Idempotent payment request creation.
- Per-order request-level webhook URL in each hosted checkout request.
- Webhook/IPN duplicate protection and event processing log.
- Payment, refund, dispute, and chargeback status mapping.
- Redacted debug logging.

## Webhook Routing

The extension sends the store callback endpoint as `webhook_url` when it creates each PayXCommerce payment request. That lets PayXCommerce route payment, refund, dispute, and chargeback events back to the exact OpenCart store/order flow. Configure the same webhook URL in PayXCommerce merchant settings as a fallback for manually created requests that do not include a request-level webhook URL.
