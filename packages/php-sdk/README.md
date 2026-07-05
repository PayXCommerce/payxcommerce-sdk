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
]);
```

## Run Tests

```bash
php tests/run.php
```

