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
- Standalone checkout details modal for collecting phone/country when OpenCart checkout does not require them.
- Hosted checkout redirect from OpenCart orders.
- Idempotent payment request creation.
- Per-order request-level webhook URL in each hosted checkout request.
- Webhook/IPN duplicate protection and event processing log.
- Payment, refund, dispute, and chargeback status mapping.
- Redacted debug logging.
- OpenCart 3 compatible country lookup using the supported country list fallback.

## Installer Permission Preflight

If OpenCart fails during the Installer `Copying files` step with PHP `rename()` permission errors, run the bundled preflight helper on the OpenCart 3 root before uploading the ZIP:

```bash
plugins/opencart3/tools/preflight-permissions.sh /path/to/opencart3
```

The following OpenCart paths must be writable by the PHP/web-server user because the installer copies payment controllers, language files, templates, images, and the shared API library into them: `system/library`, `admin/controller/extension/payment`, `admin/language/en-gb/extension/payment`, `admin/view/image/payment`, `admin/view/template/extension/payment`, `catalog/controller/extension/payment`, `catalog/language/en-gb/extension/payment`, `catalog/model/extension/payment`, `catalog/view/theme/default/template/extension/payment`, and `system/storage/upload`.

## Reinstall / Upgrade Notes

OpenCart 3 uploads extension files into the normal `admin/`, `catalog/`, and `system/` folders. If an installer reports that an old path already exists, remove the previous PayXCommerce extension package from Extensions → Installer, refresh modifications, and upload the latest `payxcommerce-opencart3-gateway-*.ocmod.zip` package again. The extension keeps transaction tables and deletes only saved settings when disabled/uninstalled from Payments.

## Webhook Routing

The extension sends the store callback endpoint as `webhook_url` when it creates each PayXCommerce payment request. That lets PayXCommerce route payment, refund, dispute, and chargeback events back to the exact OpenCart store/order flow. Configure the same webhook URL in PayXCommerce merchant settings as a fallback for manually created requests that do not include a request-level webhook URL.
