<?php

declare(strict_types=1);

require __DIR__ . '/_payxcommerce.php';

$baseUrl = 'https://payxcommerce.com/api/v1';
$clientId = 'YOUR_DEVELOPER_APP_CLIENT_ID';
$clientSecret = 'YOUR_DEVELOPER_APP_CLIENT_SECRET';

$payload = [
    'grant_type' => 'client_credentials',
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'scope' => 'payment_requests.write transactions.read balances.read refunds.write',
];

payx_print_response(payx_json_request('POST', $baseUrl . '/oauth/token', [], $payload));

