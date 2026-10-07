# PayXCommerce Integrations

PayXCommerce SDKs and plugins for hosted checkout, payment requests, API integrations, refunds, balances, transaction lookup, and signed webhooks.

This repository is the public integration home for PayXCommerce. It starts with a PHP SDK and raw PHP examples, then uses the same SDK foundation for ecommerce plugins.

## Packages

- `packages/php-sdk` — PHP 8.1+ SDK for PayXCommerce API v1.
- `packages/python-sdk` — Python 3.9+ SDK for PayXCommerce API v1.
- `packages/node-sdk` — Node.js 18+ SDK for PayXCommerce API v1.
- `examples/raw-php` — copy-paste PHP examples that work without Composer.
- `examples/raw-python` — dependency-free Python examples using the standard library.
- `examples/raw-nodejs` — dependency-free Node.js examples using built-in modules.
- `examples/sdk-php` — examples using the PHP SDK.
- `examples/sdk-python` — examples using the Python SDK.
- `examples/sdk-nodejs` — examples using the Node.js SDK.
- `plugins/woocommerce` — WooCommerce hosted checkout gateway.
- `plugins/opencart3` — OpenCart 3 hosted checkout extension.
- `plugins/opencart4` — OpenCart 4 hosted checkout extension.
- `plugins/magento2` — Magento 2 hosted checkout module.

## API Coverage

- HMAC API key authentication.
- Developer App OAuth client credentials and Bearer token authentication.
- Create hosted checkout payment requests.
- Read merchant balances.
- Lookup transactions.
- Submit refund requests.
- Verify signed webhooks.

## Install PHP SDK

The PHP SDK is not currently published on Packagist. Install it from this repository with a Composer path/VCS repository, pinned to a reviewed commit SHA. For local development, use `packages/php-sdk`.

## Install Python SDK

The Python SDK is not currently published on PyPI. Install the checked-out source (preferably pinned to a reviewed tag/SHA):

```bash
pip install -e packages/python-sdk
```

## Install Node.js SDK

The Node.js SDK is not currently published on npm. Install the checked-out source (preferably pinned to a reviewed tag/SHA):

```bash
npm install ./packages/node-sdk
```

## Raw Server Examples

Use the raw examples when you want to integrate without a packaged SDK:

- `examples/raw-php`
- `examples/raw-python`
- `examples/raw-nodejs`

Each language folder includes HMAC requests, Developer App Bearer requests, OAuth client credentials, refund requests, transaction lookup, balance lookup, and webhook signature verification.

SDK-based examples are available under `examples/sdk-php`, `examples/sdk-python`, and `examples/sdk-nodejs`; each includes HMAC payment request, OAuth/Bearer payment request, and webhook verification examples.

## Quick HMAC Example

```php
use PayXCommerce\Auth\HmacAuth;
use PayXCommerce\Client;
use PayXCommerce\Config;

$client = new Client(new Config(
    baseUrl: 'https://payxcommerce.com/api/v1',
    auth: new HmacAuth('pxc_public_key', 'pxc_secret_key')
));

$response = $client->paymentRequests()->create([
    'amount' => '125.50',
    'currency' => 'USD',
    'purpose' => 'Invoice INV-1001',
    'customer' => [
        'name' => 'Jane Customer',
        'email' => 'customer@example.com',
        'country' => 'United States',
    ],
]);

echo $response['checkout_url'];
```

## Security

Never expose HMAC secret keys, Developer App client secrets, Bearer tokens, or webhook secrets in frontend code. All signing and token creation must happen server-side.

Webhook HMAC verification must use the exact raw HTTP request-body bytes. Do not parse and re-serialize JSON before verification. Non-local API endpoints must use HTTPS.

## Releases and checksums

Public GitHub and package-registry publication are controlled release actions and are not implied by changes to the private development repository. Release operators must tag the reviewed commit, build artifacts with the scripts in `scripts/`, publish SHA-256 checksums, and verify the extracted artifact contents against that exact tag before publication.


## Platform Implementation

See `docs/platform-implementation-notes.md` for the implemented checkout, settings, validation, redirect, and webhook/IPN behavior by platform.
