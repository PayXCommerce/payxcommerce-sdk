<?php

declare(strict_types=1);

final class ClaimQueryResult
{
    public int $num_rows;
    public array $row;

    public function __construct(array $row = [])
    {
        $this->row = $row;
        $this->num_rows = $row === [] ? 0 : 1;
    }
}

final class ClaimDatabase
{
    public static array $events = [];
    public static array $locks = [];
    public static array $acquiredLockNames = [];
    public static array $releasedLockNames = [];
    private int $affected = 0;

    public function __construct(private readonly string $connection)
    {
    }

    public function escape(string $value): string
    {
        return addslashes($value);
    }

    public function countAffected(): int
    {
        return $this->affected;
    }

    public function query(string $sql): ClaimQueryResult
    {
        $this->affected = 0;
        if (str_starts_with($sql, 'SHOW COLUMNS')) {
            return new ClaimQueryResult(['Field' => 'claim_token']);
        }
        if (preg_match("/GET_LOCK\('([^']+)'/", $sql, $match)) {
            $lockName = stripslashes($match[1]);
            if ($lockName === '' || strlen($lockName) > 64) {
                throw new RuntimeException('MySQL advisory lock names must contain 1 to 64 bytes.');
            }
            self::$acquiredLockNames[] = $lockName;
            $owner = self::$locks[$lockName] ?? null;
            if ($owner !== null && $owner !== $this->connection) {
                return new ClaimQueryResult(['acquired' => 0]);
            }
            self::$locks[$lockName] = $this->connection;
            return new ClaimQueryResult(['acquired' => 1]);
        }
        if (preg_match("/RELEASE_LOCK\('([^']+)'/", $sql, $match)) {
            $lockName = stripslashes($match[1]);
            if ($lockName === '' || strlen($lockName) > 64) {
                throw new RuntimeException('MySQL advisory lock names must contain 1 to 64 bytes.');
            }
            self::$releasedLockNames[] = $lockName;
            if ((self::$locks[$lockName] ?? null) === $this->connection) {
                unset(self::$locks[$lockName]);
            }
            return new ClaimQueryResult(['released' => 1]);
        }
        if (str_starts_with($sql, 'INSERT INTO') && str_contains($sql, 'payxcommerce_webhook_event')) {
            $eventId = $this->capture($sql, "/event_id = '([^']+)'/");
            $payloadHash = $this->capture($sql, "/payload_hash = '([^']+)'/");
            $token = $this->capture($sql, "/claim_token = '([^']+)'/");
            $current = self::$events[$eventId] ?? null;
            if ($current === null || (($current['status'] === 'failed' || ($current['status'] === 'processing' && $current['stale'])) && $current['payload_hash'] === $payloadHash)) {
                self::$events[$eventId] = ['payload_hash' => $payloadHash, 'token' => $token, 'status' => 'processing', 'stale' => false];
                $this->affected = 1;
            }
            return new ClaimQueryResult();
        }
        if (str_starts_with($sql, 'UPDATE') && str_contains($sql, 'SET created_at = NOW()')) {
            [$eventId, $token] = $this->eventAndToken($sql);
            if ($this->owns($eventId, $token)) {
                self::$events[$eventId]['stale'] = false;
                $this->affected = 1;
            }
            return new ClaimQueryResult();
        }
        if (str_starts_with($sql, 'SELECT event_id FROM')) {
            [$eventId, $token] = $this->eventAndToken($sql);
            return new ClaimQueryResult($this->owns($eventId, $token) ? ['event_id' => $eventId] : []);
        }
        if (str_starts_with($sql, 'UPDATE') && str_contains($sql, 'SET processing_status =')) {
            [$eventId, $token] = $this->eventAndToken($sql);
            if ($this->owns($eventId, $token)) {
                self::$events[$eventId]['status'] = $this->capture($sql, "/processing_status = '([^']+)'/");
                $this->affected = 1;
            }
            return new ClaimQueryResult();
        }

        throw new RuntimeException('Unexpected claim SQL: ' . $sql);
    }

    private function owns(string $eventId, string $token): bool
    {
        $event = self::$events[$eventId] ?? null;
        return $event !== null && $event['token'] === $token && $event['status'] === 'processing';
    }

    private function eventAndToken(string $sql): array
    {
        return [
            $this->capture($sql, "/event_id = '([^']+)'/"),
            $this->capture($sql, "/claim_token = '([^']+)'/"),
        ];
    }

    private function capture(string $sql, string $pattern): string
    {
        if (!preg_match($pattern, $sql, $match)) {
            throw new RuntimeException('Unable to parse claim SQL: ' . $sql);
        }
        return stripslashes($match[1]);
    }
}

class Model
{
    public ClaimDatabase $db;
}

define('DIR_SYSTEM', dirname(__DIR__, 2) . '/plugins/opencart3/upload/system/');
define('DB_PREFIX', 'oc_');
require_once dirname(__DIR__, 2) . '/plugins/opencart3/upload/catalog/model/extension/payment/payxcommerce.php';

function expectClaim(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$first = new ModelExtensionPaymentPayXCommerce();
$first->db = new ClaimDatabase('connection-a');
$second = new ModelExtensionPaymentPayXCommerce();
$second->db = new ClaimDatabase('connection-b');

$tokenOne = $first->claimWebhookEvent('evt-1', 11, 'payment.success', '{"amount":"0.1"}');
expectClaim(is_string($tokenOne) && $tokenOne !== '', 'First OpenCart 3 owner must receive a token.');
expectClaim($second->claimWebhookEvent('evt-1', 11, 'payment.success', '{"amount":"0.1"}') === null, 'Concurrent OpenCart 3 worker must be fenced by the event lock.');
$first->releaseWebhookEventLock('evt-1');

ClaimDatabase::$events['evt-1']['stale'] = true;
$tokenTwo = $second->claimWebhookEvent('evt-1', 11, 'payment.success', '{"amount":"0.1"}');
expectClaim(is_string($tokenTwo) && $tokenTwo !== $tokenOne, 'A stale OpenCart 3 claim must rotate ownership.');
expectClaim(!$first->renewWebhookEventClaim('evt-1', $tokenOne), 'Superseded OpenCart 3 owner must not renew.');
expectClaim(!$first->completeWebhookEvent('evt-1', $tokenOne), 'Superseded OpenCart 3 owner must not finalize.');
expectClaim($second->renewWebhookEventClaim('evt-1', $tokenTwo), 'Current OpenCart 3 owner must renew.');
expectClaim($second->completeWebhookEvent('evt-1', $tokenTwo), 'Current OpenCart 3 owner must finalize.');
$second->releaseWebhookEventLock('evt-1');

$thirdToken = $first->claimWebhookEvent('evt-2', 12, 'payment.failed', '{"amount":"0.2"}');
expectClaim(is_string($thirdToken) && $thirdToken !== '', 'A different OpenCart 3 event must be claimable.');
$first->releaseWebhookEventLock('evt-2');
expectClaim($first->acquireCheckoutLock(11, 'test'), 'OpenCart 3 checkout lock must be acquirable with a MySQL-compatible name.');
$first->releaseCheckoutLock(11, 'test');

$acquiredNames = array_values(array_unique(ClaimDatabase::$acquiredLockNames));
$releasedNames = array_values(array_unique(ClaimDatabase::$releasedLockNames));
expectClaim(count($acquiredNames) === 3, 'OpenCart 3 must derive stable, scope-specific lock names.');
expectClaim($acquiredNames === $releasedNames, 'OpenCart 3 must release the exact lock name it acquired.');
expectClaim(count(array_filter($acquiredNames, static fn (string $name): bool => $name !== '' && strlen($name) <= 64)) === 3, 'OpenCart 3 lock names must fit the MySQL 64-byte limit.');

echo "OpenCart 3 webhook and checkout locking passed (12 assertions).\n";
