<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model\Webhook;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PayXCommerce\Payment\Model\Config;

class Processor
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Config $config
    ) {
    }

    public function process(array $payload, string $eventId): string
    {
        $orderId = (int) ($payload['metadata']['order_id'] ?? $payload['merchant_order_id'] ?? 0);
        if ($orderId <= 0) {
            return 'Accepted; order not found';
        }

        $order = $this->orderRepository->get($orderId);
        $payment = $order->getPayment();

        if ($eventId !== '' && $payment->getAdditionalInformation('payxcommerce_event_' . $eventId)) {
            return 'Duplicate ignored';
        }

        foreach (['transaction_reference', 'payment_id', 'settlement_status', 'request_number', 'invoice_number'] as $key) {
            if (!empty($payload[$key])) {
                $payment->setAdditionalInformation('payxcommerce_' . $key, (string) $payload[$key]);
            }
        }

        if ($eventId !== '') {
            $payment->setAdditionalInformation('payxcommerce_event_' . $eventId, date('c'));
        }

        $eventType = (string) ($payload['event_type'] ?? '');
        $this->applyEvent($order, $eventType);
        $this->orderRepository->save($order);

        return 'OK';
    }

    private function applyEvent(OrderInterface $order, string $eventType): void
    {
        $storeId = (int) $order->getStoreId();
        $status = match ($eventType) {
            'payment.success', 'payment.succeeded' => $this->config->value('success_status', $storeId) ?: Order::STATE_PROCESSING,
            'payment.failed' => $this->config->value('failed_status', $storeId) ?: Order::STATE_CANCELED,
            'payment.cancelled', 'payment.canceled', 'payment.expired' => $this->config->value('cancelled_status', $storeId) ?: Order::STATE_CANCELED,
            'refund.success', 'refund.succeeded', 'payment.refunded' => $this->config->value('refunded_status', $storeId) ?: Order::STATE_CLOSED,
            'chargeback.created', 'dispute.created' => $this->config->value('chargeback_status', $storeId) ?: Order::STATE_HOLDED,
            default => '',
        };

        if (in_array($eventType, ['payment.success', 'payment.succeeded'], true) && method_exists($order, 'setState')) {
            $order->setState(Order::STATE_PROCESSING);
        }

        if ($status !== '' && method_exists($order, 'setStatus')) {
            $order->setStatus($status);
        }

        $order->addCommentToStatusHistory($this->config->brandName($storeId) . ' event received: ' . $eventType);
    }
}
