# PayXCommerce OpenCart 3 Payment Extension

Developer-preview OpenCart 3 hosted checkout extension.

## Target

- OpenCart 3.x
- Payment extension location: Extensions → Extensions → Payments → PayXCommerce

## Planned Package Structure

```text
upload/
├── admin/controller/extension/payment/payxcommerce.php
├── admin/language/en-gb/extension/payment/payxcommerce.php
├── admin/view/template/extension/payment/payxcommerce.twig
├── catalog/controller/extension/payment/payxcommerce.php
├── catalog/language/en-gb/extension/payment/payxcommerce.php
├── catalog/model/extension/payment/payxcommerce.php
├── catalog/view/theme/default/template/extension/payment/payxcommerce.twig
└── system/library/payxcommerce/
```

## First Release Flow

1. Customer selects PayXCommerce.
2. Extension creates a PayXCommerce payment request.
3. Customer redirects to hosted checkout.
4. Webhook verifies signature.
5. OpenCart order status updates from PayXCommerce event.

