<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use PayXCommerce\Auth\BearerTokenAuth;
use PayXCommerce\Client;
use PayXCommerce\Config;
use PayXCommerce\OAuth\ClientCredentials;

$oauth = new ClientCredentials(
    clientId: getenv('PAYX_CLIENT_ID') ?: 'YOUR_CLIENT_ID',
    clientSecret: getenv('PAYX_CLIENT_SECRET') ?: 'YOUR_CLIENT_SECRET'
);

$accessToken = $oauth->accessToken('payment_requests.write transactions.read balances.read refunds.write');
$client = new Client(new Config(auth: new BearerTokenAuth($accessToken)));

$response = $client->paymentRequests()->create([
    'amount' => 99.00,
    'currency' => 'USD',
    'purpose' => 'Developer App SDK example order',
    'customer' => [
        'name' => 'Jane Customer',
        'email' => 'customer@example.com',
    ],
    'success_url' => 'https://example.com/payment/success',
    'failed_url' => 'https://example.com/payment/failed',
    'cancel_url' => 'https://example.com/payment/cancel',
    'is_test' => true,
]);

echo $response['checkout_url'] . PHP_EOL;
