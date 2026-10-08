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
    contract(str_contains($controller, 'prepareCheckoutAttempt'), $label . ' checkout must use a persisted attempt lifecycle.');
    contract(str_contains($controller, 'acquireCheckoutLock'), $label . ' checkout creation must hold an order/environment lock.');
    contract(str_contains($model, 'checkout_fingerprint'), $label . ' checkout attempt must bind to a normalized payload fingerprint.');
    contract(str_contains($model, "['creating', 'pending']"), $label . ' retries must reuse only active attempts.');
    contract(str_contains($model, 'checkout_expires_at'), $label . ' expired checkout attempts must not be reused.');
    contract(str_contains($controller, "'amount' => (string) \$order['total']"), $label . ' must send monetary amounts as decimal strings.');
    contract(str_contains($controller, 'assertSuccessfulPaymentMatchesOrder'), $label . ' must reject mismatched payment-success amount/currency/environment data.');
}

$wooGateway = source($root . '/plugins/woocommerce/includes/Gateway/Gateway.php');
$wooWebhook = source($root . '/plugins/woocommerce/includes/Webhook/Handler.php');
$wooMetadata = source($root . '/plugins/woocommerce/includes/Order/Metadata.php');
contract(!str_contains($wooGateway, "attempt-' . time()"), 'WooCommerce checkout idempotency must be stable.');
contract(!str_contains($wooGateway, "refund-' . \$order->get_id() . '-' . time()"), 'WooCommerce refund idempotency must be stable.');
contract(str_contains($wooGateway, 'prepareRefundAttempt'), 'WooCommerce must persist refund attempts before the API request.');
contract(str_contains($wooGateway, "markRefundAttempt(\$order, \$attempt, 'uncertain')"), 'WooCommerce refund transport uncertainty must preserve the active attempt.');
contract(str_contains($wooMetadata, 'REFUND_ACTIVE_PREFIX'), 'WooCommerce refund retries must share a durable active-attempt identity.');
contract(str_contains($wooGateway, 'withRefundLock'), 'WooCommerce refund creation must be serialized per order/environment.');
contract(str_contains($wooGateway, 'withCheckoutLock'), 'WooCommerce checkout must serialize attempt creation per order/environment.');
contract(str_contains($wooGateway, 'prepareCheckoutAttempt'), 'WooCommerce checkout must persist its attempt before the API request.');
contract(str_contains($wooWebhook, "'payment_request_reference'"), 'WooCommerce webhooks must bind through a stored PayX request reference.');
contract(!str_contains($wooWebhook, "'metadata.order_id'"), 'WooCommerce must not resolve orders directly from payload metadata.');
$wooClaims = source($root . '/plugins/woocommerce/includes/Webhook/EventClaimStore.php');
contract(str_contains($wooClaims, 'INSERT IGNORE'), 'WooCommerce webhook claim must use the unique option-name insert as an atomic gate.');
contract(str_contains($wooClaims, 'option_value = %s'), 'WooCommerce webhook recovery/finalization must use compare-and-set ownership.');
contract(str_contains($wooWebhook, '->failed($claim)'), 'WooCommerce failed webhook processing must remain retryable.');
contract(str_contains($wooWebhook, '->processed($claim)'), 'WooCommerce event must become processed only after order effects save.');

$magentoWebhook = source($root . '/plugins/magento2/Controller/Webhook/Index.php');
$magentoCheckout = source($root . '/plugins/magento2/Controller/Checkout/Start.php');
$magentoApiClient = source($root . '/plugins/magento2/Model/Api/Client.php');
contract(str_contains($magentoWebhook, 'StoreManagerInterface'), 'Magento webhook verification must use routed store context.');
contract(!str_contains($magentoWebhook, "decoded['metadata']['store_id']"), 'Magento must not choose secrets from unsigned payload metadata.');
contract(!str_contains($magentoCheckout, "time()"), 'Magento checkout idempotency must be stable.');
contract(str_contains($magentoCheckout, 'withOrderLock'), 'Magento checkout must serialize attempt creation per order/environment.');
contract(str_contains($magentoCheckout, '->prepare('), 'Magento checkout must persist its attempt before the API request.');
contract(str_contains(source($root . '/plugins/magento2/Model/Webhook/Processor.php'), 'assertSuccessfulPaymentMatchesOrder'), 'Magento must reject mismatched payment-success amount/currency/environment data.');
$magentoProcessor = source($root . '/plugins/magento2/Model/Webhook/Processor.php');
$magentoClaims = source($root . '/plugins/magento2/Model/Webhook/EventClaimStore.php');
$magentoSchema = source($root . '/plugins/magento2/etc/db_schema.xml');
contract(str_contains($magentoProcessor, 'eventClaims->claim'), 'Magento must claim an event before applying order effects.');
contract(str_contains($magentoProcessor, 'eventClaims->failed'), 'Magento failed event processing must remain retryable.');
contract(str_contains($magentoClaims, "'claim_token = ?'"), 'Magento claim transitions must use an owner-token compare-and-set.');
contract(str_contains($magentoSchema, 'name="claim_key"') && str_contains($magentoSchema, 'referenceId="PRIMARY"'), 'Magento event claims must have a durable unique primary key.');
contract(str_contains($magentoApiClient, "\$eventId . '.' . \$rawBody"), 'Magento API helper must verify signatures against the exact raw request body.');
contract(!str_contains($magentoApiClient, "\$eventId . '.' . json_encode"), 'Magento API helper must not normalize JSON before webhook signature verification.');

foreach ($eventContract['plugin_default_subscriptions'] as $eventType) {
    contract(str_contains($wooWebhook, $eventType) || str_contains(source($root . '/plugins/woocommerce/sdk/payxcommerce-php/src/Webhooks/EventTypes.php'), $eventType), 'WooCommerce must subscribe to/handle ' . $eventType . '.');
    contract(str_contains($magentoProcessor, $eventType) || str_contains(source($root . '/plugins/magento2/Model/PaymentRequestBuilder.php'), $eventType), 'Magento must subscribe to/handle ' . $eventType . '.');
}

echo "PayXCommerce plugin security contracts passed ({$tests} assertions).\n";
