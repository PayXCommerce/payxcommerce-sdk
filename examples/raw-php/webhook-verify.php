<?php

declare(strict_types=1);

$webhookSecret = 'YOUR_WEBHOOK_SECRET';
$toleranceSeconds = 300;

$rawBody = file_get_contents('php://input') ?: '';
$eventId = $_SERVER['HTTP_X_PXC_EVENT_ID'] ?? '';
$timestamp = $_SERVER['HTTP_X_PXC_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_PXC_SIGNATURE'] ?? '';

if ($eventId === '' || $timestamp === '' || $signature === '') {
    http_response_code(400);
    echo 'Missing PayXCommerce webhook headers';
    exit;
}

if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > $toleranceSeconds) {
    http_response_code(400);
    echo 'Invalid or expired PayXCommerce webhook timestamp';
    exit;
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo 'Invalid PayXCommerce webhook JSON';
    exit;
}

$canonicalBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
$expected = hash_hmac('sha256', $eventId . '.' . $canonicalBody, $webhookSecret);

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    echo 'Invalid PayXCommerce webhook signature';
    exit;
}

$eventType = (string) ($payload['event_type'] ?? '');
$successfulPaymentEvents = ['payment.succeeded', 'payment.success'];
$cancelledPaymentEvents = ['payment.cancelled', 'payment.canceled', 'payment.expired'];
$refundEvents = ['refund.succeeded', 'refund.success', 'payment.refunded'];

// Store event_id and skip duplicates before updating local order status.
// Then map event_type to your local lifecycle. Example:
// - payment.succeeded/payment.success: mark paid
// - payment.failed: mark failed
// - payment.cancelled/payment.expired: mark cancelled
// - refund.succeeded/payment.refunded: mark refunded or append refund note
// - chargeback.created/dispute.created: mark under review/on hold

http_response_code(200);
echo 'PayXCommerce webhook verified: ' . ($eventType ?: 'unknown');
