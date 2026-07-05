<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use PayXCommerce\Exceptions\WebhookVerificationException;
use PayXCommerce\Webhooks\EventTypes;
use PayXCommerce\Webhooks\Verifier;

$rawBody = file_get_contents('php://input') ?: '';
$headers = function_exists('getallheaders') ? getallheaders() : $_SERVER;
$secret = getenv('PAYX_WEBHOOK_SECRET') ?: 'YOUR_WEBHOOK_SECRET';

try {
    $payload = (new Verifier($secret))->verify($rawBody, $headers);
    $eventType = (string) ($payload['event_type'] ?? '');

    if (EventTypes::isSuccessfulPayment($eventType)) {
        // Mark the local order as paid using your stored reference.
    }

    http_response_code(200);
    echo 'ok';
} catch (WebhookVerificationException $exception) {
    http_response_code(400);
    echo 'invalid webhook';
}
