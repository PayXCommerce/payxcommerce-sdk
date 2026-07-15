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
        private readonly Logger $logger
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
        if ($this->metadata->hasEvent($order, $eventId)) {
            status_header(200);
            echo 'Duplicate event ignored';
            exit;
        }

        $this->applyEvent($order, sanitize_text_field((string) ($payload['event_type'] ?? '')), $payload);
        if ($eventId !== '') {
            $this->metadata->markEvent($order, $eventId);
        }
        $order->save();

        status_header(200);
        echo 'OK';
        exit;
    }

    private function findOrder(array $payload): ?WC_Order
    {
        foreach ([
            'metadata.order_id',
            'metadata.woocommerce_order_id',
            'data.metadata.order_id',
            'data.metadata.woocommerce_order_id',
            'payload.metadata.order_id',
            'payload.metadata.woocommerce_order_id',
            'resource.metadata.order_id',
            'resource.metadata.woocommerce_order_id',
            'merchant_order_id',
            'data.merchant_order_id',
            'payload.merchant_order_id',
            'resource.merchant_order_id',
        ] as $path) {
            $orderId = $this->payloadValue($payload, $path);
            if ($orderId === null || (string) $orderId === '') {
                continue;
            }

            $order = wc_get_order((int) $orderId);
            if ($order) {
                return $order;
            }
        }

        $metaLookups = [
            Metadata::REQUEST_NUMBER => [
                'request_number',
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
            EventTypes::isDisputeOrChargeback($eventType) => $order->update_status('on-hold', __('Dispute or chargeback created.', 'payxcommerce-gateway')),
            default => $order->add_order_note(sprintf(__('Payment event received: %s', 'payxcommerce-gateway'), $eventType)),
        };
    }
}
