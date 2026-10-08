<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;

class CheckoutAttemptManager
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function withOrderLock(int $orderId, string $environment, callable $callback): mixed
    {
        $connection = $this->resourceConnection->getConnection();
        $lockName = 'payx_m2_checkout_' . hash('sha256', $orderId . '|' . $environment);
        $acquired = (int) $connection->fetchOne('SELECT GET_LOCK(?, 10)', [$lockName]);
        if ($acquired !== 1) {
            throw new \RuntimeException('Another checkout attempt is already being prepared.');
        }

        try {
            return $callback();
        } finally {
            $connection->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    public function prepare(OrderInterface $order, array $payload, string $environment): array
    {
        $payment = $order->getPayment();
        $fingerprint = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $state = (string) $payment->getAdditionalInformation('payxcommerce_attempt_state');
        $expiresAt = (string) $payment->getAdditionalInformation('payxcommerce_attempt_expires_at');
        $expired = $expiresAt !== '' && (strtotime($expiresAt) ?: 0) <= time();
        $matches = hash_equals((string) $payment->getAdditionalInformation('payxcommerce_attempt_fingerprint'), $fingerprint)
            && hash_equals((string) $payment->getAdditionalInformation('payxcommerce_environment'), $environment);

        if ($matches && !$expired && in_array($state, ['creating', 'pending'], true)) {
            return [
                'id' => (string) $payment->getAdditionalInformation('payxcommerce_attempt_id'),
                'key' => (string) $payment->getAdditionalInformation('payxcommerce_attempt_key'),
                'checkout_url' => (string) $payment->getAdditionalInformation('payxcommerce_checkout_url'),
                'reused' => true,
            ];
        }

        $attemptId = bin2hex(random_bytes(16));
        $key = 'magento2-order-' . $order->getEntityId() . '-' . $environment . '-' . $attemptId;
        $payment->setAdditionalInformation('payxcommerce_attempt_id', $attemptId);
        $payment->setAdditionalInformation('payxcommerce_attempt_key', $key);
        $payment->setAdditionalInformation('payxcommerce_attempt_fingerprint', $fingerprint);
        $payment->setAdditionalInformation('payxcommerce_attempt_state', 'creating');
        $payment->setAdditionalInformation('payxcommerce_attempt_expires_at', '');
        $payment->setAdditionalInformation('payxcommerce_checkout_url', '');
        $payment->setAdditionalInformation('payxcommerce_environment', $environment);

        return ['id' => $attemptId, 'key' => $key, 'checkout_url' => '', 'reused' => false];
    }

    public function completed(OrderInterface $order, array $response): void
    {
        $payment = $order->getPayment();
        $expiresAt = trim((string) ($response['expires_at'] ?? ''));
        if ($expiresAt === '' || (strtotime($expiresAt) ?: 0) <= 0) {
            $expiresAt = gmdate('c', time() + 86400);
        }
        $payment->setAdditionalInformation('payxcommerce_attempt_state', 'pending');
        $payment->setAdditionalInformation('payxcommerce_attempt_expires_at', $expiresAt);
    }

    public function terminal(OrderInterface $order, string $eventType): void
    {
        $order->getPayment()->setAdditionalInformation('payxcommerce_attempt_state', $eventType);
    }
}
