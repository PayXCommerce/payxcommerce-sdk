<?php

declare(strict_types=1);

require __DIR__ . '/_payxcommerce.php';

$baseUrl = 'https://payxcommerce.com/api/v1';
$publicKey = 'YOUR_PUBLIC_KEY';
$secretKey = 'YOUR_SECRET_KEY';

$payload = [
    'transaction_reference' => 'PXTRX-YYYYMMDD-XXXXXX',
    'amount' => 25.00,
    'reason' => 'Customer requested partial refund',
];

$body = json_encode($payload, JSON_UNESCAPED_SLASHES);
$headers = payx_hmac_headers($publicKey, $secretKey, $body, 'raw-php-refund-' . time());

payx_print_response(payx_json_request('POST', $baseUrl . '/refunds', $headers, $payload));

