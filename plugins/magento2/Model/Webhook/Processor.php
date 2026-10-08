<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model\Webhook;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Transaction as DbTransaction;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Magento\Sales\Model\Service\InvoiceService;
use PayXCommerce\Payment\Model\Config;
use PayXCommerce\Payment\Model\CheckoutAttemptManager;

class Processor
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Config $config,
        private readonly ResourceConnection $resourceConnection,
        private readonly InvoiceService $invoiceService,
        private readonly DbTransaction $dbTransaction,
        private readonly EventClaimStore $eventClaims,
        private readonly CheckoutAttemptManager $checkoutAttempts
    ) {
    }

    public function process(array $payload, string $eventId, int $storeId, string $payloadHash): string
    {
        $order = $this->findBoundOrder($payload, $storeId);
        if (!$order || !$order->getEntityId()) {
            return 'Accepted; order not found';
        }

        $claim = $this->eventClaims->claim($eventId, (int) $order->getEntityId(), $storeId, $payloadHash);
        if (($claim['status'] ?? '') === 'conflict') {
            throw new \RuntimeException('Webhook event ID conflicts with a different payload.');
        }
        if (($claim['status'] ?? '') !== 'claimed') {
            return 'Duplicate ignored';
        }

        try {
            $payment = $order->getPayment();
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
            if (in_array($eventType, ['payment.success', 'payment.succeeded'], true)) {
                $this->assertSuccessfulPaymentMatchesOrder($order, $payload);
            }
            $this->applyEvent($order, $eventType, $payload);
            if (in_array($eventType, ['payment.success', 'payment.succeeded', 'payment.failed', 'payment.cancelled', 'payment.canceled', 'payment.expired'], true)) {
                $this->checkoutAttempts->terminal($order, $eventType);
            }
            $this->orderRepository->save($order);
            if (!$this->eventClaims->processed($claim)) {
                throw new \RuntimeException('Webhook event claim could not be finalized.');
            }
        } catch (\Throwable $exception) {
            $this->eventClaims->failed($claim, $exception->getMessage());
            throw $exception;
        }

        return 'OK';
    }

    private function findBoundOrder(array $payload, int $storeId): ?OrderInterface
    {
        foreach ([
            'request_number' => ['request_number', 'payment_request_reference', 'payment_request_id', 'payment_request_number', 'reference'],
            'invoice_number' => ['invoice_number'],
            'transaction_reference' => ['transaction_reference', 'gateway_transaction_id'],
        ] as $infoKey => $paths) {
            $value = $this->firstPayloadValue($payload, $paths);
            if ($value === '') {
                continue;
            }

            $orderId = $this->findOrderIdByPaymentInfo('payxcommerce_' . $infoKey, $value);
            if ($orderId <= 0) {
                continue;
            }

            try {
                $order = $this->orderRepository->get($orderId);
                if ((int) $order->getStoreId() === $storeId) {
                    return $order;
                }
            } catch (\Throwable) {
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
            'dispute.won' => $this->config->value('success_status', $storeId) ?: Order::STATE_PROCESSING,
            'chargeback.created', 'chargeback.updated',
            'dispute.created', 'dispute.opened', 'dispute.updated', 'dispute.evidence_required',
            'dispute.lost' => $this->config->value('chargeback_status', $storeId) ?: Order::STATE_HOLDED,
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

    private function assertSuccessfulPaymentMatchesOrder(OrderInterface $order, array $payload): void
    {
        $amount = $this->firstPayloadValue($payload, ['amount', 'request_amount']);
        if ($amount !== '' && !$this->decimalEquals((string) $order->getGrandTotal(), $amount)) {
            throw new \RuntimeException('Webhook payment amount does not match the bound Magento order.');
        }

        $currency = strtoupper($this->firstPayloadValue($payload, ['currency', 'request_currency']));
        if ($currency !== '' && $currency !== strtoupper((string) $order->getOrderCurrencyCode())) {
            throw new \RuntimeException('Webhook payment currency does not match the bound Magento order.');
        }

        $environment = strtolower($this->firstPayloadValue($payload, ['environment']));
        $expectedEnvironment = strtolower((string) $order->getPayment()->getAdditionalInformation('payxcommerce_environment'));
        if ($environment !== '' && $expectedEnvironment !== '' && $environment !== $expectedEnvironment) {
            throw new \RuntimeException('Webhook environment does not match the bound Magento order.');
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
            if ($invoice && Decimal::isPositive((string) $invoice->getGrandTotal())) {
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
