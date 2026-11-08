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

    $buttonRenderer = new ReflectionMethod(payxcommerce::class, 'expressButtonHtml');
    $button = $buttonRenderer->invoke(null, '/store/checkout?payxcommerce_express=1&guest=1',
        '/store/images/payxcommerce/logo-icon-dark-64.png');
    check(str_contains($button, '<style>') && str_contains($button, '</style>')
        && str_contains($button, 'a.payxcommerce-express-button{'), 'button styles stay with scoped markup');
    check(str_contains($button, 'height:50px') && str_contains($button, 'border:none')
        && str_contains($button, 'width:100%'), 'PayPal-sized button without border');
    check(str_contains($button, 'a.payxcommerce-express-button .payxcommerce-text{position:relative;z-index:1;letter-spacing:-.2px;color:#ffffff}'),
        'button text is explicitly white');
    check(str_contains($button, 'href="/store/checkout?payxcommerce_express=1&amp;guest=1"')
        && str_contains($button, 'Pay with PayXCommerce'), 'express checkout destination and text retained');
    check(str_contains($button, 'src="/store/images/payxcommerce/logo-icon-dark-64.png"')
        && !str_contains($button, 'https://payxcommerce.com'), 'bundled relative icon URL');
    check(str_contains($button, 'aria-hidden="true"') && str_contains($button, 'prefers-reduced-motion'),
        'decorative wave and reduced-motion support');
    $escapedButton = $buttonRenderer->invoke(null, '/checkout?next=" onmouseover="bad', '/images/icon" onerror="bad');
    check(!str_contains($escapedButton, 'onmouseover="bad') && !str_contains($escapedButton, 'onerror="bad'),
        'express button attributes escaped');
    check(is_file(__DIR__ . '/../upload/images/payxcommerce/logo-icon-dark-64.png'), 'bundled express icon exists');

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
