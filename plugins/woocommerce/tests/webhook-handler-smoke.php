<?php

declare(strict_types=1);

class WC_Order
{
    public array $meta = [];
    public bool $paid = false;
    public string $completedTransaction = '';

    public function __construct(public int $id, private string $total = '100.00', private string $currency = 'USD')
    {
    }

    public function update_meta_data(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }

    public function get_meta(string $key): mixed
    {
        return $this->meta[$key] ?? '';
    }

    public function payment_complete(string $transactionId = ''): void
    {
        $this->paid = true;
        $this->completedTransaction = $transactionId;
    }

    public function update_status(string $status, string $note = ''): void
    {
        $this->meta['status'] = $status;
        $this->meta['status_note'] = $note;
    }

    public function add_order_note(string $note): void
    {
        $this->meta['note'] = $note;
    }

    public function get_total(): string
    {
        return $this->total;
    }

    public function get_currency(): string
    {
        return $this->currency;
    }
}

$ordersById = [];
$ordersByMeta = [];

function wc_get_order(int $orderId): ?WC_Order
{
    global $ordersById;
    return $ordersById[$orderId] ?? null;
}

function wc_get_orders(array $args): array
{
    global $ordersByMeta;
    $key = ($args['meta_key'] ?? '') . ':' . ($args['meta_value'] ?? '');
    return isset($ordersByMeta[$key]) ? [$ordersByMeta[$key]] : [];
}

function sanitize_text_field(mixed $value): string
{
    return trim((string) $value);
}

function __(string $text, string $domain = ''): string
{
    return $text;
}

require_once __DIR__ . '/../sdk/payxcommerce-php/src/Webhooks/EventTypes.php';
require_once __DIR__ . '/../includes/Order/Metadata.php';
require_once __DIR__ . '/../includes/Webhook/Handler.php';

use PayXCommerce\WooCommerce\Order\Metadata;
use PayXCommerce\WooCommerce\Webhook\Handler;

function callPrivate(object $object, string $method, mixed ...$args): mixed
{
    $ref = new ReflectionMethod($object, $method);
    $ref->setAccessible(true);
    return $ref->invoke($object, ...$args);
}

function assertTrue(bool $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
}

$handler = (new ReflectionClass(Handler::class))->newInstanceWithoutConstructor();

$orderFromMeta = new WC_Order(42);
$ordersByMeta[Metadata::REQUEST_NUMBER . ':PXRQ-20260715-TEST01'] = $orderFromMeta;
$resolved = callPrivate($handler, 'findOrder', [
    'event_type' => 'payment.success',
    'payment_request_id' => 'PXRQ-20260715-TEST01',
    'transaction_reference' => 'PXTRX-20260715-AAA111',
]);
assertTrue($resolved === $orderFromMeta, 'Top-level payment_request_id should resolve saved request-number metadata.');

callPrivate($handler, 'applyEvent', $orderFromMeta, 'payment.success', [
    'payment_request_id' => 'PXRQ-20260715-TEST01',
    'transaction_reference' => 'PXTRX-20260715-AAA111',
    'payment_id' => 'PXPAY-20260715-BBB222',
    'amount' => '100.0000',
    'currency' => 'usd',
]);
assertTrue($orderFromMeta->paid, 'Successful webhook should complete the WooCommerce payment.');
assertTrue($orderFromMeta->completedTransaction === 'PXTRX-20260715-AAA111', 'Successful webhook should pass PayX transaction reference to WooCommerce.');
assertTrue($orderFromMeta->meta[Metadata::REQUEST_NUMBER] === 'PXRQ-20260715-TEST01', 'Webhook should persist payment_request_id as request metadata.');
assertTrue($orderFromMeta->meta[Metadata::TRANSACTION_REFERENCE] === 'PXTRX-20260715-AAA111', 'Webhook should persist transaction metadata.');

$orderFromNestedMeta = new WC_Order(43);
$ordersByMeta[Metadata::REQUEST_NUMBER . ':PXRQ-20260715-NESTED'] = $orderFromNestedMeta;
$resolvedNested = callPrivate($handler, 'findOrder', [
    'data' => [
        'payment_request_id' => 'PXRQ-20260715-NESTED',
    ],
]);
assertTrue($resolvedNested === $orderFromNestedMeta, 'Nested data.payment_request_id should resolve saved request metadata.');

$orderFromId = new WC_Order(44);
$ordersById[44] = $orderFromId;
$resolvedId = callPrivate($handler, 'findOrder', [
    'metadata' => [
        'order_id' => '44',
    ],
]);
assertTrue($resolvedId === null, 'Unsigned metadata.order_id must not resolve an order without a stored PayX reference.');

$resolvedBound = callPrivate($handler, 'findOrder', [
    'payment_request_reference' => 'PXRQ-20260715-TEST01',
    'metadata' => ['order_id' => '44'],
]);
assertTrue($resolvedBound === $orderFromMeta, 'Stored PayX request binding must win over conflicting payload order metadata.');

$mismatchRejected = false;
try {
    callPrivate($handler, 'applyEvent', new WC_Order(45, '19.99', 'USD'), 'payment.success', [
        'payment_request_reference' => 'PXRQ-MISMATCH',
        'amount' => '20.00',
        'currency' => 'USD',
    ]);
} catch (RuntimeException $exception) {
    $mismatchRejected = str_contains($exception->getMessage(), 'amount');
}
assertTrue($mismatchRejected, 'Payment-success webhook must reject an amount mismatch before completing the order.');

echo "WooCommerce webhook handler smoke passed\n";
