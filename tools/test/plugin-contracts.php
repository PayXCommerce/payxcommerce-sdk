<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$tests = 0;

function contract(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function source(string $path): string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $contents;
}

$eventContract = json_decode(source($root . '/contracts/webhook-events.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ([
    'PHP SDK' => $root . '/packages/php-sdk/src/Webhooks/EventTypes.php',
    'embedded WooCommerce SDK' => $root . '/plugins/woocommerce/sdk/payxcommerce-php/src/Webhooks/EventTypes.php',
    'Node SDK' => $root . '/packages/node-sdk/src/webhooks/event-types.js',
    'Python SDK' => $root . '/packages/python-sdk/payxcommerce/webhooks/event_types.py',
] as $label => $path) {
    $catalogSource = source($path);
    foreach (array_merge($eventContract['canonical'], array_keys($eventContract['aliases'])) as $eventType) {
        contract(str_contains($catalogSource, $eventType), $label . ' is missing event catalog entry ' . $eventType . '.');
    }
}

foreach ([
    'OpenCart 3' => $root . '/plugins/opencart3/upload/catalog/controller/extension/payment/payxcommerce.php',
    'OpenCart 4' => $root . '/plugins/opencart4/upload/extension/payxcommerce/catalog/controller/payment/payxcommerce.php',
] as $label => $path) {
    $controller = source($path);
    preg_match('/public function success\([^)]*\).*?\n    }/s', $controller, $match);
    $success = $match[0] ?? '';
    contract($success !== '', $label . ' success handler must exist.');
    contract(!str_contains($success, 'markReturnSuccess'), $label . ' browser return must not mutate payment state.');
    contract(!str_contains($success, 'success_status_id'), $label . ' browser return must not mark the order successful.');
    contract(str_contains($controller, 'claimWebhookEvent'), $label . ' must atomically claim retryable webhook events.');
    $modelPath = str_contains($label, '3')
        ? $root . '/plugins/opencart3/upload/catalog/model/extension/payment/payxcommerce.php'
        : $root . '/plugins/opencart4/upload/extension/payxcommerce/catalog/model/payment/payxcommerce.php';
    $model = source($modelPath);
    contract(str_contains($model, "processing_status = 'failed'"), $label . ' failed webhook delivery must be retryable.');
    contract(str_contains($model, 'DATE_SUB(NOW(), INTERVAL 5 MINUTE)'), $label . ' stale processing claims must be recoverable.');
    contract(str_contains($model, 'payload_hash = VALUES(payload_hash)'), $label . ' must not retry an event ID with a changed payload.');
    contract(!str_contains($controller, ". '-' . time()"), $label . ' checkout idempotency key must not contain time().');
    contract(str_contains($controller, "'amount' => (string) \$order['total']"), $label . ' must send monetary amounts as decimal strings.');
    contract(str_contains($controller, 'assertSuccessfulPaymentMatchesOrder'), $label . ' must reject mismatched payment-success amount/currency/environment data.');
}

$wooGateway = source($root . '/plugins/woocommerce/includes/Gateway/Gateway.php');
$wooWebhook = source($root . '/plugins/woocommerce/includes/Webhook/Handler.php');
contract(!str_contains($wooGateway, "attempt-' . time()"), 'WooCommerce checkout idempotency must be stable.');
contract(!str_contains($wooGateway, "refund-' . \$order->get_id() . '-' . time()"), 'WooCommerce refund idempotency must be stable.');
contract(str_contains($wooGateway, '_payxcommerce_refund_idempotency_'), 'WooCommerce must persist refund idempotency keys before the API request.');
contract(str_contains($wooWebhook, "'payment_request_reference'"), 'WooCommerce webhooks must bind through a stored PayX request reference.');
contract(!str_contains($wooWebhook, "'metadata.order_id'"), 'WooCommerce must not resolve orders directly from payload metadata.');

$magentoWebhook = source($root . '/plugins/magento2/Controller/Webhook/Index.php');
$magentoCheckout = source($root . '/plugins/magento2/Controller/Checkout/Start.php');
$magentoApiClient = source($root . '/plugins/magento2/Model/Api/Client.php');
contract(str_contains($magentoWebhook, 'StoreManagerInterface'), 'Magento webhook verification must use routed store context.');
contract(!str_contains($magentoWebhook, "decoded['metadata']['store_id']"), 'Magento must not choose secrets from unsigned payload metadata.');
contract(!str_contains($magentoCheckout, "time()"), 'Magento checkout idempotency must be stable.');
contract(str_contains(source($root . '/plugins/magento2/Model/Webhook/Processor.php'), 'assertSuccessfulPaymentMatchesOrder'), 'Magento must reject mismatched payment-success amount/currency/environment data.');
contract(str_contains($magentoApiClient, "\$eventId . '.' . \$rawBody"), 'Magento API helper must verify signatures against the exact raw request body.');
contract(!str_contains($magentoApiClient, "\$eventId . '.' . json_encode"), 'Magento API helper must not normalize JSON before webhook signature verification.');

echo "PayXCommerce plugin security contracts passed ({$tests} assertions).\n";
