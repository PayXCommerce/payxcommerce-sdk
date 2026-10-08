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
use PayXCommerce\Exceptions\ValidationException;
use PayXCommerce\Config;
use PayXCommerce\Http\CurlHttpClient;
use PayXCommerce\Util\Redactor;
use PayXCommerce\Util\Environment;
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
assertTrueValue(EventTypes::isSuccessfulPayment('payment.success'), 'Canonical successful payment event should be recognized.');
$eventContract = json_decode((string) file_get_contents(__DIR__ . '/../../../contracts/webhook-events.json'), true, 512, JSON_THROW_ON_ERROR);
assertSameValue($eventContract['plugin_default_subscriptions'], EventTypes::defaultSubscriptions(), 'Default webhook subscriptions should match the authoritative contract.');
assertTrueValue(EventTypes::isDisputeOrChargeback('dispute.opened'), 'Emitted dispute.opened must be handled as a dispute event.');
assertTrueValue(EventTypes::isDisputeOrChargeback('dispute.won'), 'Emitted dispute.won must be handled as a dispute event.');
assertTrueValue(EventTypes::isDisputeOrChargeback('dispute.lost'), 'Emitted dispute.lost must be handled as a dispute event.');
$unicodeBody = "{\n  \"event_id\":\"{$eventId}\",\"customer\":\"José / 東京\",\"amount\":1.2300\n}";
$unicodeExpected = hash_hmac('sha256', $eventId . '.' . $unicodeBody, 'webhook_secret');
assertSameValue($unicodeExpected, Verifier::signature($eventId, $unicodeBody, 'webhook_secret'), 'Webhook signature must preserve exact raw JSON bytes.');
$escapedBody = '{"event_id":"PXEVT-TEST","path":"https:\\/\\/example.test\\/a","message":"line\\nvalue","amount":1e2}';
$escapedSignature = Verifier::signature($eventId, $escapedBody, 'webhook_secret');
$escapedDecoded = $verifier->verify($escapedBody, [
    'X-PXC-Event-ID' => $eventId,
    'X-PXC-Timestamp' => (string) time(),
    'X-PXC-Signature' => $escapedSignature,
]);
assertSameValue(100.0, $escapedDecoded['amount'], 'Escaped JSON and exponent-form numbers should verify from their exact raw representation.');
assertSameValue('secret=[redacted]', Redactor::text('secret=abc123'), 'Redactor should hide secrets in log text.');
assertSameValue('[redacted]', Redactor::context(['client_secret' => 'abc123'])['client_secret'], 'Redactor should hide secret context values.');
assertSameValue('test', Environment::normalize('sandbox'), 'Unknown environment labels should normalize to test.');
assertSameValue('live', Environment::credentialEnvironment('pk_live_abc'), 'Live HMAC public key prefix should be detected.');
assertSameValue('test', Environment::credentialEnvironment('pk_test_abc'), 'Test HMAC public key prefix should be detected.');

try {
    Environment::assertHmacCredentialMatches('pk_test_abc', 'live');
    throw new RuntimeException('Mismatched HMAC credential mode should fail before API request.');
} catch (InvalidArgumentException $exception) {
    assertTrueValue(str_contains($exception->getMessage(), 'Live mode requires live API keys'), 'Mismatched mode error should explain the expected credential type.');
}

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

foreach ([
    ['body' => '{invalid', 'timestamp' => (string) time(), 'message' => 'Invalid JSON should fail.'],
    ['body' => $rawBody, 'timestamp' => (string) (time() - 1000), 'message' => 'Stale timestamp should fail.'],
] as $invalidWebhook) {
    try {
        $verifier->verify($invalidWebhook['body'], [
            'X-PXC-Event-ID' => $eventId,
            'X-PXC-Timestamp' => $invalidWebhook['timestamp'],
            'X-PXC-Signature' => Verifier::signature($eventId, $invalidWebhook['body'], 'webhook_secret'),
        ]);
        throw new RuntimeException($invalidWebhook['message']);
    } catch (WebhookVerificationException) {
        assertTrueValue(true, $invalidWebhook['message']);
    }
}

$httpClient = new CurlHttpClient(new Config());
$throwForStatus = new ReflectionMethod($httpClient, 'throwForStatus');
$throwForStatus->setAccessible(true);
try {
    $throwForStatus->invoke($httpClient, 422, [
        'message' => 'The given data was invalid.',
        'errors' => ['customer.country' => ['The customer.country field is required.']],
    ], '{"message":"The given data was invalid."}');
    throw new RuntimeException('Validation response should throw a ValidationException.');
} catch (ReflectionException $exception) {
    throw $exception;
} catch (ValidationException $exception) {
    assertTrueValue(str_contains($exception->getMessage(), 'customer.country: The customer.country field is required.'), 'Validation exception should include field-level API errors.');
    assertSameValue(['customer.country' => ['The customer.country field is required.']], $exception->errors(), 'Validation exception should expose structured errors.');
}

echo "PayXCommerce PHP SDK tests passed ({$tests} assertions).\n";
