# PayXCommerce for osCommerce 4

Hosted-checkout payment module tested against osCommerce 4.14 build 63493. The store never handles card details. This module is separate from the OpenCart extensions.

## Package and installation

Run `bash scripts/build-oscommerce4-package.sh` from the integrations repository. The generated `dist/plugins/payxcommerce-oscommerce4.zip` contains the two module files under `lib/common/modules/orderPayment/`. Back up the store, extract the ZIP at the osCommerce 4 application root, then use Admin → Modules → Payment → PayXCommerce → Install for the relevant storefront/platform. This is a file-upload package, not an osCommerce administrator ZIP installer. Do not extract it under `admin/` or under another `lib/` directory.

Keep the two module files together. The module creates `payxcommerce_order` and `payxcommerce_event` tables on installation. Do not drop these tables while orders or callback retries still depend on them. Before upgrading, back up the store database and module files. Replacing the two PHP files does not change existing credentials or order mappings; review schema changes in the release before an upgrade.

## Configuration

In the payment module settings, select `test` or `live`, the matching PayX API URL, and either HMAC public/secret keys or Developer App client ID/secret. Set the webhook signing secret for the same mode. API and webhook secrets use osCommerce's encrypted module configuration storage. Set distinct, valid order statuses for pending, paid, failed, cancelled, partially refunded, refunded, and chargeback. The module stays unavailable at checkout until all required credentials and statuses are configured. Test keys cannot create live-mode requests.

The module sends the store's `callback/webhooks.payment.payxcommerce` endpoint on each request. This route must be reachable over HTTPS by PayX without a store login or HTTP Basic Auth, but should have normal perimeter rate limits. The module authenticates each callback with the `X-PXC-Event-ID`, `X-PXC-Timestamp`, and `X-PXC-Signature` headers and the raw request body. Configure the same callback URL in PayX merchant webhook settings as a fallback. Never use a Production merchant key on a non-Production osCommerce installation.

## Payment lifecycle

After osCommerce saves the order, the module creates one PayX payment request using a persistent idempotency key, saves the returned request reference and HTTPS checkout URL, creates one pending osCommerce payment ledger row, clears the completed cart, and redirects to PayX. Browser returns only display a pending message; they never mark an order paid. A signed success callback must bind to the stored request, environment, request amount and currency before marking the order and debit ledger paid. Replayed event IDs and repeated success events cannot duplicate the financial transition. A late failure/cancellation cannot undo a settled payment.

Signed refunds create separate credit ledger rows keyed by refund reference. Partial refunds change the order to the configured partial-refund status; only cumulative refunds equal to the original payment produce full-refund status. Chargeback/dispute-loss events change to the configured chargeback status. Invalid, unmatched, or out-of-order callbacks return a retryable error rather than accepting an unrecorded financial change. All callback writes use a database transaction.

The selected PayX merchant must permit the order's currency, customer country, amount and configured gateway. If PayX rejects a request, the order remains pending and the checkout does not claim success. Diagnose the explicit API error in PayX API logs and the osCommerce application log; do not weaken merchant limits or routing to force checkout through.

## Validation

Run `php plugins/oscommerce4/tests/standalone.php` for deterministic decimal, URL, and webhook-signature checks. On a separate non-Production osCommerce 4 store, verify admin settings, guest checkout visibility, request creation and redirect, order/ledger persistence, signed success/failure/refund callbacks, mismatched amount rejection, duplicate replay, and a late failure after success. Do not complete an actual gateway payment merely to test the connector; use an approved Sandbox fixture.
