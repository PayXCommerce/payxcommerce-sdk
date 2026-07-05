<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'PayXCommerce\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

use PayXCommerce\Auth\HmacAuth;
use PayXCommerce\Exceptions\WebhookVerificationException;
use PayXCommerce\Util\Redactor;
use PayXCommerce\Webhooks\EventTypes;
use PayXCommerce\Webhooks\Verifier;

$tests = 0;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    global $tests;
    $tests++;
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

function assertTrueValue(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$body = '{"amount":100,"currency":"USD"}';
$expectedSignature = hash_hmac('sha256', '1710000000.nonce123.' . $body, 'secret123');
assertSameValue($expectedSignature, HmacAuth::sign('1710000000', 'nonce123', $body, 'secret123'), 'HMAC signature should match expected hash.');

$eventId = 'PXEVT-TEST';
$payload = ['event_id' => $eventId, 'event_type' => EventTypes::PAYMENT_SUCCEEDED, 'amount' => 100];
$rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
$signature = Verifier::signature($eventId, $rawBody, 'webhook_secret');
$verifier = new Verifier('webhook_secret');
$decoded = $verifier->verify($rawBody, [
    'X-PXC-Event-ID' => $eventId,
    'X-PXC-Timestamp' => (string) time(),
    'X-PXC-Signature' => $signature,
]);
assertSameValue(EventTypes::PAYMENT_SUCCEEDED, $decoded['event_type'], 'Webhook verifier should return decoded payload.');
assertTrueValue(EventTypes::isSuccessfulPayment('payment.success'), 'Legacy successful payment event should be recognized.');
assertSameValue(['payment.succeeded', 'payment.failed', 'payment.cancelled', 'payment.expired', 'refund.succeeded', 'payment.refunded', 'chargeback.created', 'dispute.created'], EventTypes::defaultSubscriptions(), 'Default webhook subscriptions should use current event names.');
assertSameValue('secret=[redacted]', Redactor::text('secret=abc123'), 'Redactor should hide secrets in log text.');
assertSameValue('[redacted]', Redactor::context(['client_secret' => 'abc123'])['client_secret'], 'Redactor should hide secret context values.');

try {
    $verifier->verify($rawBody, [
        'X-PXC-Event-ID' => $eventId,
        'X-PXC-Timestamp' => (string) time(),
        'X-PXC-Signature' => 'invalid',
    ]);
    throw new RuntimeException('Invalid webhook signature should fail.');
} catch (WebhookVerificationException) {
    assertTrueValue(true, 'Invalid webhook signature failed as expected.');
}

echo "PayXCommerce PHP SDK tests passed ({$tests} assertions).\n";
