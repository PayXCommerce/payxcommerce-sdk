# PayXCommerce Magento 2 Payment Module

Magento 2 module for PayXCommerce hosted checkout.

## Package Name

```text
PayXCommerce_Payment
```

## Installable ZIP Formats

Magento does not provide a standard admin ZIP upload flow like WordPress/OpenCart. The PayXCommerce Magento module is distributed in two formats for different Magento deployment workflows:

- Manual app/code package: `payxcommerce-magento2-payment-app-code-0.3.4.zip`
  - Contains `app/code/PayXCommerce/Payment`.
  - Unzip at the Magento root.
  - Run `bin/magento module:enable PayXCommerce_Payment`, `bin/magento setup:upgrade`, and `bin/magento cache:flush`.

- Composer artifact package: `payxcommerce-magento2-payment-composer-0.3.4.zip`
  - Contains module files at package root with `composer.json`.
  - Use as a local/private Composer artifact and require `payxcommerce/magento2-payment`.
  - Run Magento setup and cache commands after Composer installs the package.

Both ZIPs install the same module. Choose one installation method per Magento site.

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
- Per-order request-level webhook URL in each hosted checkout request.
- Signed webhook/IPN verification with timestamp tolerance.
- Duplicate webhook protection using order payment metadata.
- Payment, refund, dispute, and chargeback status mapping.
- External webhook route with CSRF bypass for signed callbacks.
- Redacted logging for API and webhook failures.
- Admin enablement guard for required credentials.
- Searchable Allowed Countries selector for large country lists.

## Scope

- Magento 2 only.
- Magento 1 is not supported.
- Hosted checkout only; no embedded card collection.

## Webhook Routing

The module sends the Magento webhook controller URL as `webhook_url` when it creates each PayXCommerce payment request. That gives every Magento order a request-level callback destination while keeping the merchant dashboard webhook URL available as a fallback for requests created outside Magento.
