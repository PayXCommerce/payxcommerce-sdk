# PayXCommerce OpenCart 4 Payment Extension

OpenCart 4 extension loaders changed from OpenCart 3, so this folder keeps a separate package target instead of forcing one shared package.

## Features

- Shared PayXCommerce package library for HMAC, Developer App Bearer token, hosted checkout creation, and signed webhook verification.
- Credential validation before enabling saved settings.
- Configurable public brand name, payment title, description, and checkout button text.
- Currency, billing country, geo-zone, minimum total, and maximum total availability checks.
- Hosted checkout redirect from OpenCart orders.
- Idempotent payment request creation.
- Webhook/IPN duplicate protection and event processing log.
- Payment, refund, dispute, and chargeback status mapping.
- Redacted debug logging.

## Package Structure

```text
upload/extension/payxcommerce/
├── admin/controller/payment/payxcommerce.php
├── admin/view/template/payment/payxcommerce.twig
├── catalog/controller/payment/payxcommerce.php
├── catalog/model/payment/payxcommerce.php
├── catalog/view/template/payment/payxcommerce.twig
└── system/library/payxcommerce.php
```
