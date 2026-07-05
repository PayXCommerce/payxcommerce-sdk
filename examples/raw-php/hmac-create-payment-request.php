<?php

declare(strict_types=1);

require __DIR__ . '/_payxcommerce.php';

$baseUrl = 'https://payxcommerce.com/api/v1';
$publicKey = 'YOUR_PUBLIC_KEY';
$secretKey = 'YOUR_SECRET_KEY';

$payload = [
    'amount' => 125.50,
    'currency' => 'USD',
    'purpose' => 'Invoice INV-1001',
    'customer' => [
        'name' => 'Jane Customer',
        'email' => 'customer@example.com',
        'mobile' => '+15551234567',
        'country' => 'United States',
    ],
    'merchant_reference' => 'CRM-1001',
    'merchant_order_id' => 'ORDER-1001',
    'success_url' => 'https://example.com/payment/success',
    'failed_url' => 'https://example.com/payment/failed',
    'cancel_url' => 'https://example.com/payment/cancel',
    'webhook_url' => 'https://example.com/payxcommerce/webhook',
    'ipn_events' => ['payment.success', 'payment.failed', 'refund.success', 'chargeback.created'],
    'metadata' => ['source' => 'raw-php-example'],
    'is_test' => true,
];

$body = json_encode($payload, JSON_UNESCAPED_SLASHES);
$headers = payx_hmac_headers($publicKey, $secretKey, $body, 'raw-php-order-1001-' . time());

payx_print_response(payx_json_request('POST', $baseUrl . '/payment-requests', $headers, $payload));

