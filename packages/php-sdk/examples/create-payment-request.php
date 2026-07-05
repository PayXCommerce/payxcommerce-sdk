<?php

declare(strict_types=1);

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Client.php';
require __DIR__ . '/../src/Auth/AuthInterface.php';
require __DIR__ . '/../src/Auth/HmacAuth.php';
require __DIR__ . '/../src/Http/HttpClientInterface.php';
require __DIR__ . '/../src/Http/CurlHttpClient.php';
require __DIR__ . '/../src/Resources/PaymentRequests.php';
require __DIR__ . '/../src/Resources/Balance.php';
require __DIR__ . '/../src/Resources/Transactions.php';
require __DIR__ . '/../src/Resources/Refunds.php';
require __DIR__ . '/../src/Util/Nonce.php';
require __DIR__ . '/../src/Util/Idempotency.php';
require __DIR__ . '/../src/Exceptions/ApiException.php';
require __DIR__ . '/../src/Exceptions/AuthException.php';
require __DIR__ . '/../src/Exceptions/ValidationException.php';
require __DIR__ . '/../src/Exceptions/RateLimitException.php';

use PayXCommerce\Auth\HmacAuth;
use PayXCommerce\Client;
use PayXCommerce\Config;

$client = new Client(new Config(auth: new HmacAuth(
    publicKey: 'YOUR_PUBLIC_KEY',
    secretKey: 'YOUR_SECRET_KEY'
)));

$response = $client->paymentRequests()->create([
    'amount' => 125.50,
    'currency' => 'USD',
    'purpose' => 'SDK example order',
    'customer' => [
        'name' => 'Jane Customer',
        'email' => 'customer@example.com',
        'country' => 'United States',
    ],
    'metadata' => ['source' => 'php-sdk-example'],
    'is_test' => true,
]);

echo $response['checkout_url'] . PHP_EOL;

