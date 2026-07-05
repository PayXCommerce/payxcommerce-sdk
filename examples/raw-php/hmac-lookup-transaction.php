<?php

declare(strict_types=1);

require __DIR__ . '/_payxcommerce.php';

$baseUrl = 'https://payxcommerce.com/api/v1';
$publicKey = 'YOUR_PUBLIC_KEY';
$secretKey = 'YOUR_SECRET_KEY';
$transactionReference = 'PXTRX-YYYYMMDD-XXXXXX';

$headers = payx_hmac_headers($publicKey, $secretKey, '');

payx_print_response(payx_json_request('GET', $baseUrl . '/transactions/' . rawurlencode($transactionReference), $headers));

