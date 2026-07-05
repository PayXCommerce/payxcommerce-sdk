# Changelog

## 0.1.0 - Developer Preview

- Added initial monorepo foundation.
- Added PHP SDK package structure.
- Added raw PHP example structure.
- Added ecommerce plugin folders for WooCommerce, OpenCart 3, OpenCart 4, and Magento 2.
- Added HMAC, Bearer token, webhook, idempotency, API method, and plugin documentation.
- Upgraded WooCommerce with a modular SDK-backed architecture, credential validation, configurable public brand text, checkout availability rules, hosted checkout redirects, WooCommerce Blocks support, signed webhook handling, metadata storage, helper functions, and refund requests.
- Upgraded OpenCart 3 and OpenCart 4 with admin settings, availability checks, hosted checkout creation, reference storage, signed webhook handling, and event duplicate protection.
- Refactored OpenCart 3 and OpenCart 4 around reusable PayXCommerce API client libraries with credential validation, configurable public checkout text, current event names, improved order reference lookup, and webhook processing state tracking.
- Upgraded Magento 2 with module config, encrypted settings, checkout renderer, hosted checkout redirect controller, API client, availability checks, and webhook controller.
- Refactored Magento 2 around dedicated config, API client, request builder, webhook verifier, webhook processor, and logger services with configurable public checkout text, required-credential enablement guard, CSRF-aware signed webhook endpoint, current event names, and safer failure logging.
