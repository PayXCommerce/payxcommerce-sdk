<?php

declare(strict_types=1);

require __DIR__ . '/_payxcommerce.php';

$baseUrl = 'https://payxcommerce.com/api/v1';
$accessToken = 'YOUR_DEVELOPER_APP_ACCESS_TOKEN';

$payload = [
    'amount' => 125.50,
    'currency' => 'USD',
    'purpose' => 'Invoice INV-1002',
    'customer' => [
        'name' => 'Jane Customer',
        'email' => 'customer@example.com',
        'country' => 'United States',
    ],
    'metadata' => ['source' => 'raw-php-bearer-example'],
    'is_test' => true,
];

$headers = [
    'Authorization' => 'Bearer ' . $accessToken,
    'Idempotency-Key' => 'raw-php-bearer-order-1002-' . time(),
];

payx_print_response(payx_json_request('POST', $baseUrl . '/payment-requests', $headers, $payload));

