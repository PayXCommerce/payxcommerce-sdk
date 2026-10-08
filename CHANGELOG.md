# Changelog

## 2026-10-08

- Fenced OpenCart 3 and OpenCart 4 webhook processing with connection-scoped event locks and rotating owner tokens so a stale worker cannot apply or finalize a reclaimed event.
- Replaced binary floating-point eligibility and invoice checks with exact decimal comparisons across WooCommerce, Magento 2, OpenCart 3, and OpenCart 4.
- Added durable atomic webhook claim lifecycles for WooCommerce and Magento with failed/stale recovery and owner-token compare-and-set transitions.
- Added persisted, payload-bound checkout attempt lifecycles, bounded fallback expiry, and order/environment locks across WooCommerce, Magento, OpenCart 3, and OpenCart 4.
- Added a persisted WooCommerce refund-attempt lifecycle that reuses the same key after transport uncertainty while allowing a new key for a distinct local refund.
- Aligned plugin subscriptions and order handling with the dispute and chargeback events emitted by PayXCommerce, including restoring the normal successful status after `dispute.won`.
- Made Magento app/code and Composer package builds version-driven and deterministic.

## 0.1.0 - Initial Integration Release

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
- Added PHP SDK webhook event catalog and log redaction utilities, refreshed raw PHP examples to current event names, and wired WooCommerce to use shared SDK event/redaction helpers.
- Added dependency-free raw Python and Node.js examples for HMAC, Bearer token, OAuth client credentials, payment requests, balances, transaction lookup, refunds, and webhook verification.
- Added Python and Node.js SDK packages with auth, OAuth, resources, webhook verification, event helpers, redaction helpers, package tests, and SDK-based examples.
- Added top-level PHP SDK examples for HMAC payment requests, OAuth/Bearer payment requests, and webhook verification.
- Aligned package-local and repository-level SDK examples across PHP, Python, and Node.js.
