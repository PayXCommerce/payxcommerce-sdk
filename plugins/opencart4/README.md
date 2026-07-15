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
- Signed success webhooks force the configured paid status with OpenCart 4 history override so paid orders do not stay pending or canceled in admin.
- Redacted debug logging.
- Reinstall-safe uninstall cleanup for stale `extension/payxcommerce/` files before uploading a fresh package.
- OpenCart 4 installer-safe package layout that extracts into `extension/payxcommerce/`.

## Package Structure

The installable OpenCart 4 ZIP must be named `payxcommerce.ocmod.zip`. OpenCart 4 uses the uploaded ZIP basename as the extension code and then prepends that code while extracting files. Do not upload versioned filenames such as `payxcommerce-opencart4-gateway-0.3.9.ocmod.zip`.

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

## Reinstall / Upgrade Notes

OpenCart 4 refuses to upload a package when `extension/payxcommerce/` already exists from an earlier install. Use Extensions → Extensions → Payments → PayXCommerce → Uninstall before uploading a new package; this extension removes its stale extension folder during uninstall so the next upload can proceed. If a previous broken install never reached the Payments list, remove `extension/payxcommerce/` from the OpenCart root manually, then upload `payxcommerce.ocmod.zip` again.

Source files live under:

```text
plugins/opencart4/upload/extension/payxcommerce/
├── admin/controller/payment/payxcommerce.php
├── admin/view/template/payment/payxcommerce.twig
├── catalog/controller/payment/payxcommerce.php
├── catalog/model/payment/payxcommerce.php
├── catalog/view/template/payment/payxcommerce.twig
└── system/library/payxcommerce.php

The generated `payxcommerce.ocmod.zip` contains `install.json`, `admin/`, `catalog/`, and `system/` at the ZIP root. The OpenCart 4 installer then extracts those paths under `extension/payxcommerce/`, resulting in `extension/payxcommerce/admin/...`, `extension/payxcommerce/catalog/...`, and `extension/payxcommerce/system/library/payxcommerce.php`.
```

## Webhook Routing

The extension sends the store callback endpoint as `webhook_url` when it creates each PayXCommerce payment request. That lets PayXCommerce route payment, refund, dispute, and chargeback events back to the exact OpenCart store/order flow. Configure the same webhook URL in PayXCommerce merchant settings as a fallback for manually created requests that do not include a request-level webhook URL.
