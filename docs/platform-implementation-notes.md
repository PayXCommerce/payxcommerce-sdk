# Platform Implementation Notes

This repository follows each platform's payment-extension conventions as closely as possible without bundling a full test installation inside the repo.

## WooCommerce

Implemented in `plugins/woocommerce/payxcommerce-gateway.php`.

- Uses a modular plugin structure with bootstrap, autoloader, settings, SDK factory, gateway, order helpers, webhook handler, and Blocks integration files.
- Loads the PayXCommerce PHP SDK through Composer or the local monorepo SDK during development.
- Uses SDK event catalog and redaction utilities to avoid duplicated webhook names and unsafe debug logging.
- Registers a `WC_Payment_Gateway` payment method and WooCommerce Blocks payment method.
- Uses WC-API callback route `?wc-api=payxcommerce` for PayXCommerce IPN/webhooks.
- Validates HMAC or Developer App Bearer credentials when settings are saved and the gateway is enabled.
- Keeps password fields when left blank during settings updates.
- Uses configurable public brand name, checkout title, description, and button text.
- Hides the method at checkout when currency, billing country, min amount, max amount, or missing setup makes it unavailable.
- Creates PayXCommerce hosted checkout payment requests and redirects customers to hosted checkout.
- Sends complete customer name, email, phone, and country where available so gateway-level customer KYC can run on hosted checkout when required.
- Verifies webhook signatures before updating WooCommerce orders.
- Stores PayXCommerce request, invoice, transaction, payment, settlement, and event metadata on the order.
- Supports admin refund requests using the PayXCommerce refund API.

## OpenCart 3

Implemented under `plugins/opencart3/upload`.

- Adds admin settings, secret preservation, install tables, and credential validation.
- Uses a shared OpenCart API client library for HMAC, Developer App Bearer token, hosted checkout creation, and signed webhook verification.
- Adds payment availability model with currency, country, geo zone, and amount checks.
- Creates PayXCommerce hosted checkout requests from OpenCart orders.
- Collects missing phone/country details before request creation when the OpenCart checkout did not require them, so hosted checkout KYC has the minimum customer details needed.
- Stores PayXCommerce references in `oc_payxcommerce_order`.
- Verifies IPN/webhook signatures before order updates.
- Stores processed webhook IDs in `oc_payxcommerce_webhook_event` to avoid duplicate processing.
- Maps payment/refund/chargeback events to configurable OpenCart order statuses.
- Uses configurable public brand name, title, description, and checkout button text.

## OpenCart 4

Implemented under `plugins/opencart4/upload/extension/payxcommerce`.

- Uses OpenCart 4 namespaced extension structure and `install.json` metadata.
- Uses a package-local API client library for HMAC, Developer App Bearer token, hosted checkout creation, credential validation, and signed webhook verification.
- Adds admin settings, install tables, payment availability model, hosted checkout creation, and webhook signature verification.
- Collects missing phone/country details before request creation when the OpenCart checkout did not require them, so hosted checkout KYC has the minimum customer details needed.
- Uses configurable public brand name, title, description, and checkout button text.
- Keeps OpenCart 4 separate from OpenCart 3 because extension structures differ.

## Magento 2

Implemented under `plugins/magento2` as module `PayXCommerce_Payment`.

- Declares Magento Sales, Payment, and Checkout module dependencies.
- Adds admin configuration under Stores → Configuration → Sales → Payment Methods.
- Provides encrypted credential fields for API keys, Developer App credentials, and webhook secret.
- Uses dedicated config, API client, request builder, webhook verifier, webhook processor, and redacted logger services.
- Uses configurable public brand name, title, description, and checkout button text.
- Prevents enabling the method when required credentials are missing.
- Adds frontend checkout renderer that places the order and redirects to PayXCommerce hosted checkout creation.
- Sends Magento customer/order contact details to PayXCommerce so hosted checkout can display customer KYC only when the selected PayXCommerce gateway requires it.
- Adds `payxcommerce/checkout/start` controller to create hosted checkout requests.
- Adds CSRF-aware `payxcommerce/webhook/index` controller to verify signed PayXCommerce webhooks and update orders.
- Adds availability checks for active status, currency, billing country, min amount, and max amount.

## Platform Acceptance Checklist

Before enabling live processing for a merchant store, run acceptance checks in the target platform environment:

- Confirm admin settings save correctly and preserve existing secrets when password fields are left blank.
- Confirm checkout availability rules for currency, billing country, minimum amount, and maximum amount.
- Confirm hosted checkout redirect creation from a real platform order.
- Confirm orders with customer phone/country create hosted checkout requests cleanly; if KYC is required by a PayXCommerce gateway, the customer completes it on hosted checkout before payment.
- Confirm signed webhook/IPN delivery updates the local order exactly once.
- Confirm failure, cancellation, refund, dispute, and chargeback status mappings match merchant operations.
- Confirm redacted logging does not expose credentials, webhook signatures, or customer-sensitive data.
