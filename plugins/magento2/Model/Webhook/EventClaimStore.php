<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model\Webhook;

use Magento\Framework\App\ResourceConnection;

class EventClaimStore
{
    private const STALE_AFTER_SECONDS = 300;

    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function claim(string $eventId, int $orderId, int $storeId, string $payloadHash): array
    {
        if (trim($eventId) === '') {
            return ['status' => 'conflict', 'key' => '', 'token' => ''];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('payxcommerce_webhook_event');
        $key = hash('sha256', $storeId . '|' . $orderId . '|' . $eventId);
        $token = bin2hex(random_bytes(16));
        $now = gmdate('Y-m-d H:i:s');
        $row = [
            'claim_key' => $key,
            'event_id' => $eventId,
            'order_id' => $orderId,
            'store_id' => $storeId,
            'payload_hash' => $payloadHash,
            'processing_status' => 'processing',
            'claim_token' => $token,
            'claimed_at' => $now,
            'processed_at' => null,
        ];

        try {
            $connection->insert($table, $row);
            return ['status' => 'claimed', 'key' => $key, 'token' => $token];
        } catch (\Throwable) {
            // The primary key is the atomic claim. Inspect and conditionally
            // recover only failed or stale processing attempts below.
        }

        $existing = $connection->fetchRow(
            $connection->select()->from($table)->where('claim_key = ?', $key)->limit(1)
        );
        if (!is_array($existing) || !hash_equals((string) ($existing['payload_hash'] ?? ''), $payloadHash)) {
            return ['status' => 'conflict', 'key' => $key, 'token' => ''];
        }

        $staleBefore = time() - self::STALE_AFTER_SECONDS;
        $claimedAt = strtotime((string) ($existing['claimed_at'] ?? '')) ?: PHP_INT_MAX;
        $retryable = ($existing['processing_status'] ?? '') === 'failed'
            || (($existing['processing_status'] ?? '') === 'processing' && $claimedAt <= $staleBefore);
        if (!$retryable) {
            return ['status' => 'duplicate', 'key' => $key, 'token' => ''];
        }

        $updated = $connection->update($table, [
            'processing_status' => 'processing',
            'claim_token' => $token,
            'claimed_at' => $now,
            'processed_at' => null,
            'error_message' => null,
        ], [
            'claim_key = ?' => $key,
            'claim_token = ?' => (string) ($existing['claim_token'] ?? ''),
            'payload_hash = ?' => $payloadHash,
        ]);

        return (int) $updated === 1
            ? ['status' => 'claimed', 'key' => $key, 'token' => $token]
            : ['status' => 'duplicate', 'key' => $key, 'token' => ''];
    }

    public function processed(array $claim): bool
    {
        return $this->transition($claim, 'processed', null);
    }

    public function failed(array $claim, string $message): bool
    {
        return $this->transition($claim, 'failed', substr($message, 0, 1000));
    }

    private function transition(array $claim, string $status, ?string $error): bool
    {
        $key = (string) ($claim['key'] ?? '');
        $token = (string) ($claim['token'] ?? '');
        if ($key === '' || $token === '') {
            return false;
        }

        $connection = $this->resourceConnection->getConnection();
        $updated = $connection->update(
            $this->resourceConnection->getTableName('payxcommerce_webhook_event'),
            [
                'processing_status' => $status,
                'processed_at' => gmdate('Y-m-d H:i:s'),
                'error_message' => $error,
            ],
            ['claim_key = ?' => $key, 'claim_token = ?' => $token]
        );

        return (int) $updated === 1;
    }
}
