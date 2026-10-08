<?php

declare(strict_types=1);

namespace Opencart\System\Engine {
    class Model
    {
        public \ClaimDatabase4 $db;
    }
}

namespace {
    final class ClaimQueryResult4
    {
        public int $num_rows;
        public array $row;

        public function __construct(array $row = [])
        {
            $this->row = $row;
            $this->num_rows = $row === [] ? 0 : 1;
        }
    }

    final class ClaimDatabase4
    {
        public static array $events = [];
        public static array $locks = [];
        private int $affected = 0;

        public function __construct(private readonly string $connection)
        {
        }

        public function escape(string $value): string { return addslashes($value); }
        public function countAffected(): int { return $this->affected; }

        public function query(string $sql): ClaimQueryResult4
        {
            $this->affected = 0;
            if (str_starts_with($sql, 'SHOW COLUMNS')) return new ClaimQueryResult4(['Field' => 'claim_token']);
            if (preg_match("/GET_LOCK\('([^']+)'/", $sql, $match)) {
                $owner = self::$locks[$match[1]] ?? null;
                if ($owner !== null && $owner !== $this->connection) return new ClaimQueryResult4(['acquired' => 0]);
                self::$locks[$match[1]] = $this->connection;
                return new ClaimQueryResult4(['acquired' => 1]);
            }
            if (preg_match("/RELEASE_LOCK\('([^']+)'/", $sql, $match)) {
                if ((self::$locks[$match[1]] ?? null) === $this->connection) unset(self::$locks[$match[1]]);
                return new ClaimQueryResult4(['released' => 1]);
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
                return new ClaimQueryResult4();
            }
            if (str_starts_with($sql, 'UPDATE') && str_contains($sql, 'SET created_at = NOW()')) {
                [$eventId, $token] = $this->eventAndToken($sql);
                if ($this->owns($eventId, $token)) { self::$events[$eventId]['stale'] = false; $this->affected = 1; }
                return new ClaimQueryResult4();
            }
            if (str_starts_with($sql, 'SELECT event_id FROM')) {
                [$eventId, $token] = $this->eventAndToken($sql);
                return new ClaimQueryResult4($this->owns($eventId, $token) ? ['event_id' => $eventId] : []);
            }
            if (str_starts_with($sql, 'UPDATE') && str_contains($sql, 'SET processing_status =')) {
                [$eventId, $token] = $this->eventAndToken($sql);
                if ($this->owns($eventId, $token)) {
                    self::$events[$eventId]['status'] = $this->capture($sql, "/processing_status = '([^']+)'/");
                    $this->affected = 1;
                }
                return new ClaimQueryResult4();
            }
            throw new \RuntimeException('Unexpected claim SQL: ' . $sql);
        }

        private function owns(string $eventId, string $token): bool
        {
            $event = self::$events[$eventId] ?? null;
            return $event !== null && $event['token'] === $token && $event['status'] === 'processing';
        }
        private function eventAndToken(string $sql): array
        {
            return [$this->capture($sql, "/event_id = '([^']+)'/"), $this->capture($sql, "/claim_token = '([^']+)'/")];
        }
        private function capture(string $sql, string $pattern): string
        {
            if (!preg_match($pattern, $sql, $match)) throw new \RuntimeException('Unable to parse claim SQL: ' . $sql);
            return stripslashes($match[1]);
        }
    }

    define('DIR_EXTENSION', dirname(__DIR__, 2) . '/plugins/opencart4/upload/extension/');
    define('DB_PREFIX', 'oc_');
    require_once dirname(__DIR__, 2) . '/plugins/opencart4/upload/extension/payxcommerce/catalog/model/payment/payxcommerce.php';

    function expectClaim4(bool $condition, string $message): void
    {
        if (!$condition) throw new RuntimeException($message);
    }

    $class = \Opencart\Catalog\Model\Extension\Payxcommerce\Payment\Payxcommerce::class;
    $first = new $class();
    $first->db = new ClaimDatabase4('connection-a');
    $second = new $class();
    $second->db = new ClaimDatabase4('connection-b');

    $tokenOne = $first->claimWebhookEvent('evt-1', 11, 'payment.success', '{"amount":"0.1"}');
    expectClaim4(is_string($tokenOne) && $tokenOne !== '', 'First OpenCart 4 owner must receive a token.');
    expectClaim4($second->claimWebhookEvent('evt-1', 11, 'payment.success', '{"amount":"0.1"}') === null, 'Concurrent OpenCart 4 worker must be fenced by the event lock.');
    $first->releaseWebhookEventLock('evt-1');

    ClaimDatabase4::$events['evt-1']['stale'] = true;
    $tokenTwo = $second->claimWebhookEvent('evt-1', 11, 'payment.success', '{"amount":"0.1"}');
    expectClaim4(is_string($tokenTwo) && $tokenTwo !== $tokenOne, 'A stale OpenCart 4 claim must rotate ownership.');
    expectClaim4(!$first->renewWebhookEventClaim('evt-1', $tokenOne), 'Superseded OpenCart 4 owner must not renew.');
    expectClaim4(!$first->completeWebhookEvent('evt-1', $tokenOne), 'Superseded OpenCart 4 owner must not finalize.');
    expectClaim4($second->renewWebhookEventClaim('evt-1', $tokenTwo), 'Current OpenCart 4 owner must renew.');
    expectClaim4($second->completeWebhookEvent('evt-1', $tokenTwo), 'Current OpenCart 4 owner must finalize.');
    $second->releaseWebhookEventLock('evt-1');

    echo "OpenCart 4 webhook owner fencing passed (7 assertions).\n";
}
