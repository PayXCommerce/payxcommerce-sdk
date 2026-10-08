<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce\Webhook;

use PayXCommerce\Webhooks\EventTypes;
use PayXCommerce\Webhooks\Verifier;
use PayXCommerce\WooCommerce\Order\Metadata;
use PayXCommerce\WooCommerce\Support\Logger;
use WC_Order;

final class Handler
{
    public function __construct(
        private readonly string $webhookSecret,
        private readonly Metadata $metadata,
        private readonly Logger $logger,
        private readonly ?EventClaimStore $eventClaims = null
    ) {
    }

    public function handle(): void
    {
        $rawBody = file_get_contents('php://input') ?: '';
        $headers = [
            'X-PXC-Event-ID' => sanitize_text_field($_SERVER['HTTP_X_PXC_EVENT_ID'] ?? ''),
            'X-PXC-Timestamp' => sanitize_text_field($_SERVER['HTTP_X_PXC_TIMESTAMP'] ?? ''),
            'X-PXC-Signature' => sanitize_text_field($_SERVER['HTTP_X_PXC_SIGNATURE'] ?? ''),
        ];

        try {
            $payload = (new Verifier($this->webhookSecret))->verify($rawBody, $headers);
        } catch (\Throwable $exception) {
            $this->logger->info('Webhook verification failed: ' . $exception->getMessage());
            status_header(401);
            echo 'Invalid webhook signature';
            exit;
        }

        $order = $this->findOrder($payload);
        if (!$order) {
            $this->logger->info('Webhook accepted but order not found.');
            status_header(202);
            echo 'Accepted; order not found';
            exit;
        }

        $eventId = (string) ($payload['event_id'] ?? $headers['X-PXC-Event-ID']);
        $claimStore = $this->eventClaims ?? new EventClaimStore();
        $claim = $claimStore->claim($order, $eventId, hash('sha256', $rawBody));
        if (($claim['status'] ?? '') !== 'claimed') {
            status_header(($claim['status'] ?? '') === 'conflict' ? 409 : 200);
            echo ($claim['status'] ?? '') === 'conflict' ? 'Event ID conflict' : 'Duplicate event ignored';
            exit;
        }

        try {
            $eventType = sanitize_text_field((string) ($payload['event_type'] ?? ''));
            $this->applyEvent($order, $eventType, $payload);
            if (EventTypes::isSuccessfulPayment($eventType)
                || EventTypes::isFailedPayment($eventType)
                || EventTypes::isCancelledPayment($eventType)) {
                $this->metadata->markCheckoutAttemptTerminal($order, $eventType);
            }
            if ($eventId !== '') {
                $this->metadata->markEvent($order, $eventId);
            }
            $order->save();
            if (!$claimStore->processed($claim)) {
                throw new \RuntimeException('Webhook event claim could not be finalized.');
            }
        } catch (\Throwable $exception) {
            $claimStore->failed($claim);
            $this->logger->info('Webhook processing failed: ' . $exception->getMessage());
            status_header(500);
            echo 'Processing failed';
            exit;
        }

        status_header(200);
        echo 'OK';
        exit;
    }

    private function findOrder(array $payload): ?WC_Order
    {
        $metaLookups = [
            Metadata::REQUEST_NUMBER => [
                'request_number',
                'payment_request_reference',
                'payment_request_id',
                'payment_request_number',
                'reference',
                'data.request_number',
                'data.payment_request_id',
                'data.payment_request_number',
                'data.reference',
                'payload.request_number',
                'payload.payment_request_id',
                'payload.payment_request_number',
                'payload.reference',
                'resource.request_number',
                'resource.payment_request_id',
                'resource.payment_request_number',
                'resource.reference',
            ],
            Metadata::INVOICE_NUMBER => [
                'invoice_number',
                'data.invoice_number',
                'payload.invoice_number',
                'resource.invoice_number',
            ],
            Metadata::TRANSACTION_REFERENCE => [
                'transaction_reference',
                'gateway_transaction_id',
                'data.transaction_reference',
                'data.gateway_transaction_id',
                'payload.transaction_reference',
                'payload.gateway_transaction_id',
                'resource.transaction_reference',
                'resource.gateway_transaction_id',
            ],
        ];

        foreach ($metaLookups as $metaKey => $paths) {
            foreach ($paths as $path) {
                $value = (string) ($this->payloadValue($payload, $path) ?? '');
                if ($value === '') {
                    continue;
                }

                $orders = wc_get_orders(['limit' => 1, 'meta_key' => $metaKey, 'meta_value' => $value]);
                if (!empty($orders)) {
                    return $orders[0];
                }
            }
        }

        return null;
    }

    private function payloadValue(array $payload, string $path): mixed
    {
        $value = $payload;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return is_scalar($value) || $value === null ? $value : null;
    }

    private function applyEvent(WC_Order $order, string $eventType, array $payload): void
    {
        if (EventTypes::isSuccessfulPayment($eventType)) {
            $this->assertSuccessfulPaymentMatchesOrder($order, $payload);
        }

        foreach ([
            Metadata::REQUEST_NUMBER => ['request_number', 'payment_request_id', 'payment_request_number', 'reference'],
            Metadata::INVOICE_NUMBER => ['invoice_number'],
            Metadata::TRANSACTION_REFERENCE => ['transaction_reference', 'gateway_transaction_id'],
            Metadata::PAYMENT_ID => ['payment_id'],
            Metadata::SETTLEMENT_STATUS => ['settlement_status'],
        ] as $metaKey => $payloadKeys) {
            $value = '';
            foreach ($payloadKeys as $payloadKey) {
                $value = (string) ($this->payloadValue($payload, $payloadKey) ?? $this->payloadValue($payload, 'data.' . $payloadKey) ?? $this->payloadValue($payload, 'payload.' . $payloadKey) ?? $this->payloadValue($payload, 'resource.' . $payloadKey) ?? '');
                if ($value !== '') {
                    break;
                }
            }

            if ($value !== '') {
                $order->update_meta_data($metaKey, sanitize_text_field($value));
            }
        }

        $transactionReference = (string) ($this->payloadValue($payload, 'transaction_reference') ?? $this->payloadValue($payload, 'data.transaction_reference') ?? $this->payloadValue($payload, 'payload.transaction_reference') ?? $this->payloadValue($payload, 'resource.transaction_reference') ?? '');

        match (true) {
            EventTypes::isSuccessfulPayment($eventType) => $order->payment_complete($transactionReference),
            EventTypes::isFailedPayment($eventType) => $order->update_status('failed', __('Payment failed.', 'payxcommerce-gateway')),
            EventTypes::isCancelledPayment($eventType) => $order->update_status('cancelled', __('Payment cancelled or expired.', 'payxcommerce-gateway')),
            EventTypes::isRefundCompleted($eventType) => $order->add_order_note(__('Refund completed.', 'payxcommerce-gateway')),
            $eventType === EventTypes::DISPUTE_WON => $order->update_status(
                $order->needs_processing() ? 'processing' : 'completed',
                __('Dispute resolved in the merchant’s favour.', 'payxcommerce-gateway')
            ),
            in_array($eventType, [EventTypes::DISPUTE_CLOSED, EventTypes::CHARGEBACK_CLOSED], true) => $order->add_order_note(__('Dispute or chargeback closed.', 'payxcommerce-gateway')),
            EventTypes::isDisputeOrChargeback($eventType) => $order->update_status('on-hold', __('Dispute or chargeback created.', 'payxcommerce-gateway')),
            default => $order->add_order_note(sprintf(__('Payment event received: %s', 'payxcommerce-gateway'), $eventType)),
        };
    }

    private function assertSuccessfulPaymentMatchesOrder(WC_Order $order, array $payload): void
    {
        $amount = (string) ($this->payloadValue($payload, 'amount')
            ?? $this->payloadValue($payload, 'request_amount')
            ?? $this->payloadValue($payload, 'data.amount')
            ?? $this->payloadValue($payload, 'resource.amount')
            ?? '');
        if ($amount !== '' && !$this->decimalEquals((string) $order->get_total(), $amount)) {
            throw new \RuntimeException('Webhook payment amount does not match the bound WooCommerce order.');
        }

        $currency = strtoupper((string) ($this->payloadValue($payload, 'currency')
            ?? $this->payloadValue($payload, 'request_currency')
            ?? $this->payloadValue($payload, 'data.currency')
            ?? $this->payloadValue($payload, 'resource.currency')
            ?? ''));
        if ($currency !== '' && $currency !== strtoupper((string) $order->get_currency())) {
            throw new \RuntimeException('Webhook payment currency does not match the bound WooCommerce order.');
        }

        $environment = strtolower((string) ($this->payloadValue($payload, 'environment') ?? ''));
        $expectedEnvironment = strtolower((string) $order->get_meta(Metadata::ENVIRONMENT));
        if ($environment !== '' && $expectedEnvironment !== '' && $environment !== $expectedEnvironment) {
            throw new \RuntimeException('Webhook environment does not match the bound WooCommerce order.');
        }
    }

    private function decimalEquals(string $expected, string $actual): bool
    {
        $normalize = static function (string $value): ?string {
            $value = trim($value);
            if (!preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
                return null;
            }
            $integer = ltrim($matches[2], '0');
            $fraction = rtrim($matches[3] ?? '', '0');
            $normalized = ($integer === '' ? '0' : $integer) . ($fraction === '' ? '' : '.' . $fraction);
            return $matches[1] === '-' && $normalized !== '0' ? '-' . $normalized : $normalized;
        };

        $expectedNormalized = $normalize($expected);
        $actualNormalized = $normalize($actual);

        return $expectedNormalized !== null && $expectedNormalized === $actualNormalized;
    }
}
