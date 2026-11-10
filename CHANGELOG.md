# Changelog

## 2026-11-10 — osCommerce 4 plugin 0.1.4 (#1195)

- Charge the order's displayed selected-currency total using its saved currency conversion rate, with decimal-only rounding. Market-priced orders remain unconverted. A stale prepared request with the old amount is refused rather than reused or silently changed.
- Existing paid transactions are preserved; this correction applies to new checkout attempts and does not retroactively charge historical orders.

## 2026-11-09 — osCommerce 4 plugin 0.1.3 (#1189)

- Classify only the API's exact merchant billing-country policy denial and show a clear, safe checkout error with the saved order number and no-payment assurance. Other API errors remain generic and provider response bodies are never logged or shown.

## 2026-11-09 — osCommerce 4 plugin 0.1.2 (#1188)

- Return from hosted checkout now reads only the saved, signed-webhook-confirmed payment state. An owned paid order redirects to native osCommerce success; guest return links can show a protected confirmation page.
- Pending returns show a styled order page with bounded status polling and clear next steps. Failed, cancelled, expired, refunded and disputed states cannot masquerade as fresh payment success.
- Order-bound, short-lived return capability protects guest status access; bare order IDs and other customers cannot view it.

## 2026-11-09 — osCommerce 4 plugin 0.1.1 (#1187)

- Added the osCommerce 4 hosted-checkout module source, installation guide, standalone checks, and committed-source ZIP packaging alongside the existing store plugins.
- Version 0.1.1 accepts a blank customer phone, displays an initiation error safely, and retries the same saved osCommerce order with its existing PayX idempotency key instead of creating a duplicate order.
- The public plugin catalog provides the installable ZIP and SHA-256 checksum; the repository README explains installation and links to the module guide.

## 2026-10-26 — SDK 0.1.1 and plugin updates

- PHP, Node.js, and Python SDKs now expose a bounded, allowlisted `Retry-After` response hint for explicit retry decisions. They do not automatically retry financial requests.
- WooCommerce 0.3.10 preserves checkout return URLs and the original idempotency key across recoverable guest-session and throttling flows.
- Magento 2 0.3.11 and OpenCart 3/4 0.3.15 use the synchronized PHP SDK transport and preserve safe throttling metadata.
- Updated plugin package build tooling creates platform-native installer layouts and verifies package contents against committed source.

## 2026-10-08

- Kept every MySQL advisory lock identifier within the 64-byte platform limit across OpenCart webhook/checkout, WooCommerce checkout/refund, and Magento checkout paths, with executable acquire/release contracts.
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
