<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use PayXCommerce\Auth\HmacAuth;
use PayXCommerce\Client;
use PayXCommerce\Config;
use PayXCommerce\Exceptions\ApiException;
use PayXCommerce\Webhooks\EventTypes;

$client = new Client(new Config(auth: new HmacAuth(
    publicKey: getenv('PAYX_PUBLIC_KEY') ?: 'YOUR_PUBLIC_KEY',
    secretKey: getenv('PAYX_SECRET_KEY') ?: 'YOUR_SECRET_KEY'
)));

try {
    $response = $client->paymentRequests()->create([
        'amount' => 125.50,
        'currency' => 'USD',
        'purpose' => 'SDK example order',
        'customer' => [
            'name' => 'Jane Customer',
            'email' => 'customer@example.com',
            'country' => 'United States',
        ],
        'merchant_reference' => 'SDK-1001',
        'merchant_order_id' => 'ORDER-1001',
        'success_url' => 'https://example.com/payment/success',
        'failed_url' => 'https://example.com/payment/failed',
        'cancel_url' => 'https://example.com/payment/cancel',
        'webhook_url' => 'https://example.com/payxcommerce/webhook',
        'ipn_events' => EventTypes::defaultSubscriptions(),
        'metadata' => ['source' => 'php-sdk-example'],
        'is_test' => true,
    ]);

    echo $response['checkout_url'] . PHP_EOL;
} catch (ApiException $exception) {
    payx_print_sdk_exception($exception);
}
