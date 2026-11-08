<?php

declare(strict_types=1);

namespace common\classes\modules {
    class ModulePayment {}
    class ModuleSortOrder {}
    class ModuleStatus {}
}

namespace {
    require __DIR__ . '/../upload/lib/common/modules/orderPayment/payxcommerce.php';

    use common\modules\orderPayment\lib\PayxClient;
    use common\modules\orderPayment\payxcommerce;

    function check(bool $condition, string $name): void
    {
        if (!$condition) throw new RuntimeException($name);
        echo "PASS $name\n";
    }

    function rejects(callable $operation, string $name): void
    {
        try { $operation(); } catch (Throwable $exception) {
            echo "PASS $name\n";
            return;
        }
        throw new RuntimeException($name . ' was not rejected');
    }

    $module = (new ReflectionClass(payxcommerce::class))->newInstanceWithoutConstructor();
    $configuration = $module->configure_keys();
    check(array_key_first($configuration) === 'MODULE_PAYMENT_PAYXCOMMERCE_EXPRESS_CHECKOUT', 'express toggle first in settings');
    check($configuration['MODULE_PAYMENT_PAYXCOMMERCE_EXPRESS_CHECKOUT']['value'] === 'True', 'new-install express default on');
    check($configuration['MODULE_PAYMENT_PAYXCOMMERCE_SORT_ORDER']['value'] === '1', 'new-install sort default one');
    check($configuration['MODULE_PAYMENT_PAYXCOMMERCE_REFUNDED_STATUS_ID']['set_function'] === 'selectOrderStatus(',
        'status selector includes ungrouped statuses');

    check(payxcommerce::roundDecimal('699.782609', 2) === '699.78', 'currency rounding down');
    check(payxcommerce::roundDecimal('699.785000', 2) === '699.79', 'currency rounding half up');
    check(payxcommerce::roundDecimal('0.005000', 2) === '0.01', 'sub-cent rounding');
    check(payxcommerce::roundDecimal('9999999999999.995000', 2) === '10000000000000.00', 'decimal carry');
    check(payxcommerce::normalizeDecimal('000699.780000') === '699.78', 'canonical comparison');
    rejects(fn () => payxcommerce::roundDecimal('1.000001', 3), 'unsupported currency scale');
    $cents = new ReflectionMethod(payxcommerce::class, 'moneyCents');
    check($cents->invoke(null, '699.780000') === 69978, 'database decimal cents');
    rejects(fn () => $cents->invoke(null, '1.001000'), 'fractional-cent rejection');

    rejects(fn () => new PayxClient(['base_url' => 'http://payxcommerce.com/api/v1']), 'insecure API URL');
    rejects(fn () => new PayxClient(['base_url' => 'https://user@payxcommerce.com/api/v1']), 'credential-bearing API URL');
    $client = new PayxClient(['base_url' => 'https://dev.payxcommerce.com/api/v1', 'webhook_secret' => 'test-only-signing-key']);
    $eventId = 'PXEV-test-1';
    $body = '{"event_type":"payment.success","environment":"test"}';
    $time = (string) time();
    $headers = [
        'HTTP_X_PXC_EVENT_ID' => $eventId,
        'HTTP_X_PXC_TIMESTAMP' => $time,
        'HTTP_X_PXC_SIGNATURE' => hash_hmac('sha256', $eventId . '.' . $body, 'test-only-signing-key'),
    ];
    check($client->verifyWebhook($body, $headers)['event_type'] === 'payment.success', 'signed webhook');
    rejects(fn () => $client->verifyWebhook($body . ' ', $headers), 'tampered webhook');
    rejects(fn () => $client->verifyWebhook($body, array_replace($headers, [
        'HTTP_X_PXC_TIMESTAMP' => (string) (time() - 301),
    ])), 'stale webhook');
    rejects(fn () => $client->verifyWebhook($body, array_replace($headers, [
        'HTTP_X_PXC_EVENT_ID' => 'bad event id',
    ])), 'unsafe event identifier');
}
