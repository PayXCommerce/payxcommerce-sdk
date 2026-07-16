<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model\Webhook;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Transaction as DbTransaction;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;
use PayXCommerce\Payment\Model\Config;

class Processor
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Config $config,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly InvoiceService $invoiceService,
        private readonly DbTransaction $dbTransaction
    ) {
    }

    public function process(array $payload, string $eventId): string
    {
        $order = $this->findOrder($payload);
        if (!$order || !$order->getEntityId()) {
            return 'Accepted; order not found';
        }

        $payment = $order->getPayment();
        if ($eventId !== '' && $payment->getAdditionalInformation('payxcommerce_event_' . $eventId)) {
            return 'Duplicate ignored';
        }

        foreach ([
            'request_number' => ['request_number', 'payment_request_id', 'payment_request_number', 'reference'],
            'invoice_number' => ['invoice_number'],
            'transaction_reference' => ['transaction_reference', 'gateway_transaction_id'],
            'payment_id' => ['payment_id'],
            'settlement_status' => ['settlement_status'],
        ] as $infoKey => $paths) {
            $value = $this->firstPayloadValue($payload, $paths);
            if ($value !== '') {
                $payment->setAdditionalInformation('payxcommerce_' . $infoKey, $value);
            }
        }

        if ($eventId !== '') {
            $payment->setAdditionalInformation('payxcommerce_event_' . $eventId, date('c'));
        }

        $eventType = (string) ($payload['event_type'] ?? '');
        $this->applyEvent($order, $eventType, $payload);
        $this->orderRepository->save($order);

        return 'OK';
    }

    private function findOrder(array $payload): ?OrderInterface
    {
        foreach ([
            'metadata.order_id',
            'metadata.magento_order_id',
            'data.metadata.order_id',
            'data.metadata.magento_order_id',
            'payload.metadata.order_id',
            'payload.metadata.magento_order_id',
            'resource.metadata.order_id',
            'resource.metadata.magento_order_id',
            'merchant_order_id',
            'data.merchant_order_id',
            'payload.merchant_order_id',
            'resource.merchant_order_id',
        ] as $path) {
            $orderId = $this->payloadValue($payload, $path);
            if ($orderId === null || (string) $orderId === '' || (int) $orderId <= 0) {
                continue;
            }

            try {
                return $this->orderRepository->get((int) $orderId);
            } catch (\Throwable) {
            }
        }

        foreach ([
            'metadata.increment_id',
            'metadata.order_increment_id',
            'data.metadata.increment_id',
            'payload.metadata.increment_id',
            'resource.metadata.increment_id',
            'merchant_reference',
            'data.merchant_reference',
            'payload.merchant_reference',
            'resource.merchant_reference',
        ] as $path) {
            $value = (string) ($this->payloadValue($payload, $path) ?? '');
            $incrementId = preg_replace('/^M2-/i', '', $value) ?: '';
            if ($incrementId === '') {
                continue;
            }

            $order = $this->orderCollectionFactory->create()
                ->addFieldToFilter('increment_id', $incrementId)
                ->setPageSize(1)
                ->getFirstItem();
            if ($order && $order->getEntityId()) {
                return $order;
            }
        }

        foreach ([
            'request_number' => ['request_number', 'payment_request_id', 'payment_request_number', 'reference'],
            'invoice_number' => ['invoice_number'],
            'transaction_reference' => ['transaction_reference', 'gateway_transaction_id'],
        ] as $infoKey => $paths) {
            $value = $this->firstPayloadValue($payload, $paths);
            if ($value === '') {
                continue;
            }

            $orderId = $this->findOrderIdByPaymentInfo('payxcommerce_' . $infoKey, $value);
            if ($orderId > 0) {
                try {
                    return $this->orderRepository->get($orderId);
                } catch (\Throwable) {
                }
            }
        }

        return null;
    }

    private function findOrderIdByPaymentInfo(string $key, string $value): int
    {
        $connection = $this->resourceConnection->getConnection();
        $paymentTable = $this->resourceConnection->getTableName('sales_order_payment');
        $escapedKey = addcslashes($key, '%_\\');
        $escapedValue = addcslashes($value, '%_\\');
        $escapedJsonKey = addcslashes('\"' . $key . '\"', '%_\\');
        $patterns = [
            '%' . $escapedKey . '%' . $escapedValue . '%',
            '%' . $escapedJsonKey . '%' . $escapedValue . '%',
        ];

        foreach ($patterns as $pattern) {
            $select = $connection->select()
                ->from($paymentTable, ['parent_id'])
                ->where('additional_information LIKE ?', $pattern)
                ->limit(1);
            $orderId = (int) $connection->fetchOne($select);
            if ($orderId > 0) {
                return $orderId;
            }
        }

        return 0;
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

    /**
     * @param string[] $paths
     */
    private function firstPayloadValue(array $payload, array $paths): string
    {
        foreach ($paths as $path) {
            $value = $this->payloadValue($payload, $path)
                ?? $this->payloadValue($payload, 'data.' . $path)
                ?? $this->payloadValue($payload, 'payload.' . $path)
                ?? $this->payloadValue($payload, 'resource.' . $path);
            if ($value !== null && (string) $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    private function applyEvent(OrderInterface $order, string $eventType, array $payload): void
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

        if (in_array($eventType, ['payment.success', 'payment.succeeded'], true)) {
            $this->registerSuccessfulPayment($order, $payload);
            if (method_exists($order, 'setState')) {
                $order->setState(Order::STATE_PROCESSING);
            }
        }

        if ($status !== '' && method_exists($order, 'setStatus')) {
            $order->setStatus($status);
        }

        $order->addCommentToStatusHistory($this->config->brandName($storeId) . ' event received: ' . $eventType);
    }

    private function registerSuccessfulPayment(OrderInterface $order, array $payload): void
    {
        if (!$order instanceof Order) {
            return;
        }

        $payment = $order->getPayment();
        $transactionReference = $this->firstPayloadValue($payload, ['transaction_reference', 'gateway_transaction_id']);
        if ($transactionReference === '') {
            $transactionReference = 'payxcommerce-' . $order->getIncrementId();
        }

        $payment->setTransactionId($transactionReference);
        $payment->setLastTransId($transactionReference);
        $payment->setIsTransactionClosed(true);

        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            if ($invoice && (float) $invoice->getGrandTotal() > 0) {
                $invoice->setTransactionId($transactionReference);
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                $invoice->register();
                $invoice->pay();
                $invoice->getOrder()->setIsInProcess(true);
                $this->dbTransaction->addObject($invoice)->addObject($invoice->getOrder())->save();
            }
        }

        if (!$payment->getTransaction($transactionReference)) {
            $payment->addTransaction(PaymentTransaction::TYPE_CAPTURE, null, true);
        }
    }
}
