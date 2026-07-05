<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce\Webhook;

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
        $orderId = $payload['metadata']['order_id'] ?? $payload['merchant_order_id'] ?? null;
        if ($orderId) {
            $order = wc_get_order((int) $orderId);
            if ($order) {
                return $order;
            }
        }

        foreach (['request_number' => Metadata::REQUEST_NUMBER, 'invoice_number' => Metadata::INVOICE_NUMBER, 'transaction_reference' => Metadata::TRANSACTION_REFERENCE] as $payloadKey => $metaKey) {
            $value = (string) ($payload[$payloadKey] ?? '');
            if ($value === '') {
                continue;
            }
            $orders = wc_get_orders(['limit' => 1, 'meta_key' => $metaKey, 'meta_value' => $value]);
            if (!empty($orders)) {
                return $orders[0];
            }
        }

        return null;
    }

    private function applyEvent(WC_Order $order, string $eventType, array $payload): void
    {
        foreach ([Metadata::TRANSACTION_REFERENCE => 'transaction_reference', Metadata::PAYMENT_ID => 'payment_id', Metadata::SETTLEMENT_STATUS => 'settlement_status'] as $metaKey => $payloadKey) {
            $value = (string) ($payload[$payloadKey] ?? '');
            if ($value !== '') {
                $order->update_meta_data($metaKey, sanitize_text_field($value));
            }
        }

        match ($eventType) {
            'payment.success', 'payment.succeeded' => $order->payment_complete((string) ($payload['transaction_reference'] ?? '')),
            'payment.failed' => $order->update_status('failed', __('Payment failed.', 'payxcommerce-gateway')),
            'payment.cancelled', 'payment.canceled', 'payment.expired' => $order->update_status('cancelled', __('Payment cancelled or expired.', 'payxcommerce-gateway')),
            'refund.success', 'refund.succeeded', 'payment.refunded' => $order->add_order_note(__('Refund completed.', 'payxcommerce-gateway')),
            'chargeback.created', 'dispute.created' => $order->update_status('on-hold', __('Dispute or chargeback created.', 'payxcommerce-gateway')),
            default => $order->add_order_note(sprintf(__('Payment event received: %s', 'payxcommerce-gateway'), $eventType)),
        };
    }
}
