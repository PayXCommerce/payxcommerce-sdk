# PayXCommerce OpenCart 4 Payment Extension

OpenCart 4 extension loaders changed from OpenCart 3, so this folder keeps a separate package target instead of forcing one shared package.

## Features

- Shared PayXCommerce package library for HMAC, Developer App Bearer token, hosted checkout creation, and signed webhook verification.
- Credential validation before enabling saved settings.
- Configurable public brand name, payment title, description, and checkout button text.
- Currency, billing country, geo-zone, minimum total, and maximum total availability checks.
- Standalone checkout details modal for collecting phone/country when OpenCart checkout does not require them.
- Hosted checkout redirect from OpenCart orders.
- Idempotent payment request creation.
- Per-order request-level webhook URL in each hosted checkout request.
- Webhook/IPN duplicate protection and event processing log.
- Payment, refund, dispute, and chargeback status mapping.
- Redacted debug logging.

## Package Structure

The installable OpenCart 4 ZIP must be named `payxcommerce.ocmod.zip`. OpenCart 4 uses the uploaded ZIP basename as the extension code, so versioned filenames such as `payxcommerce-opencart4-gateway-0.3.3.ocmod.zip` can install into the wrong extension directory.

The ZIP root must contain `install.json` directly. Do not zip the parent `extension/` folder. The correct ZIP root layout is:

```text
install.json
admin/
catalog/
system/
```

Build and validate the package with:

```bash
scripts/build-opencart4-package.sh
```

Source files live under:

```text
plugins/opencart4/upload/extension/payxcommerce/
├── admin/controller/payment/payxcommerce.php
├── admin/view/template/payment/payxcommerce.twig
├── catalog/controller/payment/payxcommerce.php
├── catalog/model/payment/payxcommerce.php
├── catalog/view/template/payment/payxcommerce.twig
└── system/library/payxcommerce.php
```

## Webhook Routing

The extension sends the store callback endpoint as `webhook_url` when it creates each PayXCommerce payment request. That lets PayXCommerce route payment, refund, dispute, and chargeback events back to the exact OpenCart store/order flow. Configure the same webhook URL in PayXCommerce merchant settings as a fallback for manually created requests that do not include a request-level webhook URL.
