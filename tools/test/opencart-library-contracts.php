<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$tests = 0;

function verify(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require_once $root . '/plugins/opencart3/upload/system/library/payxcommerce.php';
require_once $root . '/plugins/opencart4/upload/extension/payxcommerce/system/library/payxcommerce.php';

$settings = [
    'payment_payxcommerce_base_url' => 'https://payxcommerce.com/api/v1',
    'payment_payxcommerce_webhook_secret' => 'contract-secret',
];
$clients = [
    new PayXCommerce($settings),
    new \Opencart\System\Library\Payxcommerce($settings),
];

$eventId = 'PXEVT-CONTRACT';
$rawBody = "{\n  \"event_id\":\"{$eventId}\",\"customer\":\"José / 東京\",\"amount\":1.2300\n}";
$server = [
    'HTTP_X_PXC_EVENT_ID' => $eventId,
    'HTTP_X_PXC_TIMESTAMP' => (string) time(),
    'HTTP_X_PXC_SIGNATURE' => hash_hmac('sha256', $eventId . '.' . $rawBody, 'contract-secret'),
];

foreach ($clients as $client) {
    $payload = $client->verifyWebhook($rawBody, $server);
    verify(($payload['customer'] ?? '') === 'José / 東京', 'OpenCart verifier must authenticate the exact Unicode raw body bytes.');

    try {
        $client->verifyWebhook($rawBody, array_merge($server, ['HTTP_X_PXC_SIGNATURE' => 'invalid']));
        throw new RuntimeException('Invalid OpenCart signature should fail.');
    } catch (RuntimeException $exception) {
        verify(str_contains($exception->getMessage(), 'signature'), 'OpenCart verifier must reject invalid signatures.');
    }
}

foreach ([PayXCommerce::class, \Opencart\System\Library\Payxcommerce::class] as $class) {
    try {
        new $class(['payment_payxcommerce_base_url' => 'http://payments.example.com/api/v1']);
        throw new RuntimeException('Unsafe remote HTTP base URL should fail.');
    } catch (RuntimeException $exception) {
        verify(str_contains($exception->getMessage(), 'HTTPS'), 'OpenCart clients must reject non-local HTTP API endpoints.');
    }

    verify(new $class(['payment_payxcommerce_base_url' => 'http://localhost/api/v1']) instanceof $class, 'OpenCart clients must permit explicit local HTTP test endpoints.');
}

echo "OpenCart library contracts passed ({$tests} assertions).\n";
