# PayXCommerce Magento 2 Payment Module

Magento 2 module for PayXCommerce hosted checkout.

## Package Name

```text
PayXCommerce_Payment
```

## Structure

```text
Controller/Checkout/Start.php              Hosted checkout redirect controller
Controller/Webhook/Index.php               Signed webhook/IPN endpoint
Model/Api/Client.php                       PayXCommerce API client
Model/Config.php                           Store-scoped config and public text helper
Model/PaymentRequestBuilder.php            Magento order to payment request mapper
Model/Webhook/Verifier.php                 Signature and timestamp verification
Model/Webhook/Processor.php                Order status and metadata updates
Model/Ui/ConfigProvider.php                Checkout JS config provider
view/frontend/web/js/view/payment/         Checkout renderer
```

## Features

- Hosted checkout redirect from Magento orders.
- HMAC API key authentication.
- Developer App Bearer token authentication.
- Store-scoped credential, webhook secret, availability, and status mapping settings.
- Configurable public brand name, title, description, and checkout button text.
- Currency, billing country, minimum order total, and maximum order total availability checks.
- Signed webhook/IPN verification with timestamp tolerance.
- Duplicate webhook protection using order payment metadata.
- Payment, refund, dispute, and chargeback status mapping.
- External webhook route with CSRF bypass for signed callbacks.
- Redacted logging for API and webhook failures.
- Admin enablement guard for required credentials.

## Scope

- Magento 2 only.
- Magento 1 is not supported.
- Hosted checkout only; no embedded card collection.
