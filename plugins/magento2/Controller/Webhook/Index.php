<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Controller\Webhook;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use PayXCommerce\Payment\Model\Api\Client;

class Index implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Client $client
    ) {
    }

    public function execute()
    {
        $result = $this->rawFactory->create();
        $rawBody = (string) $this->request->getContent();
        $eventId = (string) $this->request->getHeader('X-PXC-Event-ID');
        $timestamp = (string) $this->request->getHeader('X-PXC-Timestamp');
        $signature = (string) $this->request->getHeader('X-PXC-Signature');
        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            return $result->setHttpResponseCode(400)->setContents('Invalid JSON');
        }

        $storeId = isset($payload['metadata']['store_id']) ? (int) $payload['metadata']['store_id'] : null;
        if (!$this->client->verifyWebhook($eventId, $timestamp, $signature, $rawBody, $storeId)) {
            return $result->setHttpResponseCode(401)->setContents('Invalid signature');
        }

        $orderId = (int) ($payload['metadata']['order_id'] ?? $payload['merchant_order_id'] ?? 0);
        if ($orderId <= 0) {
            return $result->setHttpResponseCode(202)->setContents('Accepted; order not found');
        }

        try {
            $order = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();
            if ($eventId && $payment->getAdditionalInformation('payxcommerce_event_' . $eventId)) {
                return $result->setContents('Duplicate ignored');
            }

            foreach (['transaction_reference', 'payment_id', 'settlement_status'] as $key) {
                if (!empty($payload[$key])) {
                    $payment->setAdditionalInformation('payxcommerce_' . $key, (string) $payload[$key]);
                }
            }
            if ($eventId) {
                $payment->setAdditionalInformation('payxcommerce_event_' . $eventId, date('c'));
            }

            $eventType = (string) ($payload['event_type'] ?? '');
            $this->applyEvent($order, $eventType, $payload);
            $this->orderRepository->save($order);
            return $result->setContents('OK');
        } catch (\Throwable $exception) {
            return $result->setHttpResponseCode(500)->setContents('Processing failed');
        }
    }

    private function applyEvent($order, string $eventType, array $payload): void
    {
        $status = match ($eventType) {
            'payment.success' => $this->client->config('success_status', (int) $order->getStoreId()) ?: 'processing',
            'payment.failed' => $this->client->config('failed_status', (int) $order->getStoreId()) ?: 'canceled',
            'payment.cancelled', 'payment.expired' => $this->client->config('cancelled_status', (int) $order->getStoreId()) ?: 'canceled',
            'refund.success', 'payment.refunded' => $this->client->config('refunded_status', (int) $order->getStoreId()) ?: 'closed',
            'chargeback.created', 'dispute.created' => $this->client->config('chargeback_status', (int) $order->getStoreId()) ?: 'holded',
            default => '',
        };

        if ($eventType === 'payment.success' && method_exists($order, 'setState')) {
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
        }
        if ($status !== '') {
            $order->setStatus($status);
        }
        $order->addCommentToStatusHistory('PayXCommerce event received: ' . $eventType);
    }
}
