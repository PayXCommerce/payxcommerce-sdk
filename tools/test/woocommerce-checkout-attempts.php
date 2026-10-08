<?php

declare(strict_types=1);

class WC_Order
{
    public array $meta = [];
    public array $refunds = [];

    public function __construct(private readonly int $id)
    {
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_meta(string $key): mixed
    {
        return $this->meta[$key] ?? '';
    }

    public function update_meta_data(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }

    public function get_refunds(): array
    {
        return $this->refunds;
    }
}

final class FakeRefund
{
    public function __construct(private readonly int $id)
    {
    }

    public function get_id(): int
    {
        return $this->id;
    }
}

function sanitize_text_field(mixed $value): string
{
    return trim((string) $value);
}

function esc_url_raw(mixed $value): string
{
    return trim((string) $value);
}

function sanitize_key(mixed $value): string
{
    return preg_replace('/[^a-z0-9_\-.]/', '', strtolower((string) $value)) ?: '';
}

function wp_json_encode(mixed $value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

function attemptAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require_once __DIR__ . '/../../plugins/woocommerce/includes/Order/Metadata.php';

use PayXCommerce\WooCommerce\Order\Metadata;

$metadata = new Metadata();
$order = new WC_Order(77);
$first = $metadata->prepareCheckoutAttempt($order, 'test', 'fingerprint-a');
attemptAssert(!$first['reused'] && $first['key'] !== '', 'First checkout must persist a new stable attempt key.');

$timeoutRetry = $metadata->prepareCheckoutAttempt($order, 'test', 'fingerprint-a');
attemptAssert($timeoutRetry['reused'] && $timeoutRetry['key'] === $first['key'], 'Transport retry must reuse the in-flight key.');

$metadata->saveCheckout($order, ['request_number' => 'PXRQ-1', 'checkout_url' => 'https://checkout.test/1'], 'test');
$pendingRetry = $metadata->prepareCheckoutAttempt($order, 'test', 'fingerprint-a');
attemptAssert($pendingRetry['key'] === $first['key'] && $pendingRetry['checkout_url'] === 'https://checkout.test/1', 'Outstanding request must reuse its checkout URL.');

$changedPayload = $metadata->prepareCheckoutAttempt($order, 'test', 'fingerprint-b');
attemptAssert(!$changedPayload['reused'] && $changedPayload['key'] !== $first['key'], 'Changed order payload must create an explicit new attempt.');

$metadata->markCheckoutAttemptTerminal($order, 'payment.expired');
$afterExpiryEvent = $metadata->prepareCheckoutAttempt($order, 'test', 'fingerprint-b');
attemptAssert(!$afterExpiryEvent['reused'] && $afterExpiryEvent['key'] !== $changedPayload['key'], 'Terminal webhook state must permit a distinct new attempt.');

$metadata->saveCheckout($order, ['checkout_url' => 'https://checkout.test/expired', 'expires_at' => gmdate('c', time() - 60)], 'test');
$afterTimestampExpiry = $metadata->prepareCheckoutAttempt($order, 'test', 'fingerprint-b');
attemptAssert(!$afterTimestampExpiry['reused'] && $afterTimestampExpiry['key'] !== $afterExpiryEvent['key'], 'Expired request timestamp must not reuse a stale checkout URL.');

$order->refunds = [new FakeRefund(501)];
$refund = $metadata->prepareRefundAttempt($order, '5.00', 'Customer request', 'test');
attemptAssert(!$refund['reused'] && !$refund['completed'], 'First WooCommerce refund operation must create a persisted attempt.');
$metadata->markRefundAttempt($order, $refund, 'uncertain');
$order->refunds = [new FakeRefund(502)];
$transportRetry = $metadata->prepareRefundAttempt($order, '5.00', 'Customer request', 'test');
attemptAssert($transportRetry['reused'] && $transportRetry['key'] === $refund['key'], 'Refund transport uncertainty must reuse the same financial idempotency key.');
$metadata->markRefundAttempt($order, $transportRetry, 'succeeded', 'PXRF-1');
$completedRetry = $metadata->prepareRefundAttempt($order, '5.00', 'Customer request', 'test');
attemptAssert($completedRetry['completed'] && $completedRetry['key'] === $refund['key'], 'The same local refund operation must not be sent twice after success.');
$order->refunds = [new FakeRefund(502), new FakeRefund(503)];
$secondRefund = $metadata->prepareRefundAttempt($order, '5.00', 'Customer request', 'test');
attemptAssert(!$secondRefund['reused'] && $secondRefund['key'] !== $refund['key'], 'A distinct WooCommerce refund record must receive a new stable attempt key.');

echo "WooCommerce checkout attempt lifecycle passed\n";
