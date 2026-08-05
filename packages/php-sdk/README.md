# PayXCommerce PHP SDK

PHP 8.1+ SDK for PayXCommerce API v1.

## Supported Methods

- `POST /payment-requests`
- `GET /balance`
- `GET /transactions/{transaction_reference}`
- `POST /refunds`
- `POST /oauth/token`
- `POST /oauth/revoke`
- Webhook signature verification

## HMAC Authentication

```php
use PayXCommerce\Auth\HmacAuth;
use PayXCommerce\Client;
use PayXCommerce\Config;

$client = new Client(new Config(auth: new HmacAuth(
    publicKey: 'YOUR_PUBLIC_KEY',
    secretKey: 'YOUR_SECRET_KEY'
)));
```

## Bearer Token Authentication

```php
use PayXCommerce\Auth\BearerTokenAuth;
use PayXCommerce\Client;
use PayXCommerce\Config;

$client = new Client(new Config(auth: new BearerTokenAuth('YOUR_ACCESS_TOKEN')));
```

## Create Payment Request

```php
$paymentRequest = $client->paymentRequests()->create([
    'amount' => 100.00,
    'currency' => 'USD',
    'purpose' => 'Order #1001',
    'customer' => [
        'name' => 'Jane Customer',
        'email' => 'jane@example.com',
        'country' => 'United States',
    ],
    // Optional clarity flag. The credential mode is authoritative.
    'environment' => 'test',
    'is_test' => true,
    // Optional per-request webhook. If omitted, PayXCommerce uses the merchant default webhook URL.
    'webhook_url' => 'https://example.com/payxcommerce/webhook/order-1001',
    'ipn_events' => \PayXCommerce\Webhooks\EventTypes::defaultSubscriptions(),
]);
```

## Live and Sandbox Mode

Use test credentials for sandbox requests and live credentials for real payments. The API derives the mode from the API key or Developer App token; optional `environment`/`is_test` payload fields are accepted only when they match that credential mode. Sandbox payments remain isolated from live balances, settlements, commissions, accounting, risk scoring, and standard reports.

## Multi-Currency Transaction Fields

Transaction lookup responses include explicit payment currency roles. Prefer `request_amount`/`request_currency` for customer-facing order displays, `gateway_charge_amount`/`gateway_charge_currency` for processor reconciliation, and `ledger_amount`/`ledger_currency` for merchant balance reporting. If checkout conversion was used, the `conversion` object includes the stored quote reference and applied rate.

## Webhook Events

Request-level webhooks are useful when one merchant account powers multiple stores, platforms, or backend services. Pass `webhook_url` when creating a payment request to route only that request's events to a specific endpoint. Leave it out to use the merchant default webhook URL configured in the dashboard.

Use `PayXCommerce\Webhooks\EventTypes` to avoid hard-coding event names. It includes current event names and helper methods for legacy aliases such as `payment.success` and `refund.success`.

```php
use PayXCommerce\Webhooks\EventTypes;

if (EventTypes::isSuccessfulPayment($payload['event_type'] ?? '')) {
    // Mark the local order paid.
}
```

## Redacted Logging

Use `PayXCommerce\Util\Redactor` before writing API errors or webhook details to application logs.

```php
$safeMessage = \PayXCommerce\Util\Redactor::text($exception->getMessage());
```

## Run Tests

```bash
php tests/run.php
```

## Examples

Package-local examples are available in `examples/`:

- `create-payment-request.php` — HMAC API key payment request.
- `oauth-bearer-payment-request.php` — Developer App OAuth client credentials followed by Bearer-token payment request.
- `webhook-verify.php` — Signed webhook verification and event handling.

The repository-level mirror is available in `../../examples/sdk-php`.
