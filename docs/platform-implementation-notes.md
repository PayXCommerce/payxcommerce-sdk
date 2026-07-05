# Platform Implementation Notes

This repository follows each platform's payment-extension conventions as closely as possible without bundling a full test installation inside the repo.

## WooCommerce

Implemented in `plugins/woocommerce/payxcommerce-gateway.php`.

- Uses a modular plugin structure with bootstrap, autoloader, settings, SDK factory, gateway, order helpers, webhook handler, and Blocks integration files.
- Loads the PayXCommerce PHP SDK through Composer or the local monorepo SDK during development.
- Registers a `WC_Payment_Gateway` payment method and WooCommerce Blocks payment method.
- Uses WC-API callback route `?wc-api=payxcommerce` for PayXCommerce IPN/webhooks.
- Validates HMAC or Developer App Bearer credentials when settings are saved and the gateway is enabled.
- Keeps password fields when left blank during settings updates.
- Uses configurable public brand name, checkout title, description, and button text.
- Hides the method at checkout when currency, billing country, min amount, max amount, or missing setup makes it unavailable.
- Creates PayXCommerce hosted checkout payment requests and redirects customers to hosted checkout.
- Verifies webhook signatures before updating WooCommerce orders.
- Stores PayXCommerce request, invoice, transaction, payment, settlement, and event metadata on the order.
- Supports admin refund requests using the PayXCommerce refund API.

## OpenCart 3

Implemented under `plugins/opencart3/upload`.

- Adds admin settings, secret preservation, install tables, and credential validation.
- Adds payment availability model with currency, country, geo zone, and amount checks.
- Creates PayXCommerce hosted checkout requests from OpenCart orders.
- Stores PayXCommerce references in `oc_payxcommerce_order`.
- Verifies IPN/webhook signatures before order updates.
- Stores processed webhook IDs in `oc_payxcommerce_webhook_event` to avoid duplicate processing.
- Maps payment/refund/chargeback events to configurable OpenCart order statuses.

## OpenCart 4

Implemented under `plugins/opencart4/upload/extension/payxcommerce`.

- Uses OpenCart 4 namespaced extension structure and `install.json` metadata.
- Adds admin settings, install tables, payment availability model, hosted checkout creation, and webhook signature verification.
- Keeps OpenCart 4 separate from OpenCart 3 because extension structures differ.

## Magento 2

Implemented under `plugins/magento2` as module `PayXCommerce_Payment`.

- Declares Magento Sales, Payment, and Checkout module dependencies.
- Adds admin configuration under Stores → Configuration → Sales → Payment Methods.
- Provides encrypted credential fields for API keys, Developer App credentials, and webhook secret.
- Adds frontend checkout renderer that places the order and redirects to PayXCommerce hosted checkout creation.
- Adds `payxcommerce/checkout/start` controller to create hosted checkout requests.
- Adds `payxcommerce/webhook/index` controller to verify PayXCommerce webhooks and update orders.
- Adds availability checks for active status, currency, billing country, min amount, and max amount.

## Remaining External Validation

Code-level checks pass in this repository. Production certification still requires installing each plugin in real platform test stores:

- WordPress/WooCommerce test store.
- OpenCart 3 test store.
- OpenCart 4 test store matching the exact target minor version.
- Magento Open Source / Adobe Commerce 2.4.x test store.

Those environments are required to verify admin UI rendering, checkout JS behavior, platform-specific route loading, and real end-to-end hosted checkout/webhook flows.
