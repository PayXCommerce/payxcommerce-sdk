<?php

declare(strict_types=1);

namespace Magento\Sales\Api\Data {
    interface OrderInterface
    {
    }
}

namespace Magento\Framework\App {
    final class FakeSelect
    {
        public string $key = '';

        public function from(string $table): self
        {
            return $this;
        }

        public function where(string $condition, mixed $value): self
        {
            if (str_contains($condition, 'claim_key')) {
                $this->key = (string) $value;
            }
            return $this;
        }

        public function limit(int $limit): self
        {
            return $this;
        }
    }

    final class FakeConnection
    {
        public array $rows = [];
        public bool $lockHeld = false;

        public function insert(string $table, array $row): void
        {
            if (isset($this->rows[$row['claim_key']])) {
                throw new \RuntimeException('duplicate');
            }
            $this->rows[$row['claim_key']] = $row;
        }

        public function select(): FakeSelect
        {
            return new FakeSelect();
        }

        public function fetchRow(FakeSelect $select): array|false
        {
            return $this->rows[$select->key] ?? false;
        }

        public function update(string $table, array $values, array $where): int
        {
            $key = (string) ($this->whereValue($where, 'claim_key') ?? '');
            if (!isset($this->rows[$key])) {
                return 0;
            }
            foreach (['claim_token', 'payload_hash'] as $column) {
                $expected = $this->whereValue($where, $column);
                if ($expected !== null && !hash_equals((string) $this->rows[$key][$column], (string) $expected)) {
                    return 0;
                }
            }
            $this->rows[$key] = array_merge($this->rows[$key], $values);
            return 1;
        }

        public function fetchOne(string $sql, array $bind = []): int
        {
            if (str_contains($sql, 'GET_LOCK')) {
                if ($this->lockHeld) {
                    return 0;
                }
                $this->lockHeld = true;
                return 1;
            }
            if (str_contains($sql, 'RELEASE_LOCK')) {
                $this->lockHeld = false;
                return 1;
            }
            return 0;
        }

        private function whereValue(array $where, string $column): mixed
        {
            foreach ($where as $condition => $value) {
                if (str_starts_with((string) $condition, $column . ' ')) {
                    return $value;
                }
            }
            return null;
        }
    }

    class ResourceConnection
    {
        public function __construct(public readonly FakeConnection $connection)
        {
        }

        public function getConnection(): FakeConnection
        {
            return $this->connection;
        }

        public function getTableName(string $name): string
        {
            return $name;
        }
    }
}

namespace {
    final class FakePayment
    {
        private array $data = [];

        public function getAdditionalInformation(string $key): mixed
        {
            return $this->data[$key] ?? '';
        }

        public function setAdditionalInformation(string $key, mixed $value): void
        {
            $this->data[$key] = $value;
        }
    }

    final class FakeOrder implements \Magento\Sales\Api\Data\OrderInterface
    {
        public readonly FakePayment $payment;

        public function __construct(private readonly int $id)
        {
            $this->payment = new FakePayment();
        }

        public function getEntityId(): int
        {
            return $this->id;
        }

        public function getPayment(): FakePayment
        {
            return $this->payment;
        }
    }

    function lifecycleAssert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    require_once __DIR__ . '/../../plugins/magento2/Model/Webhook/EventClaimStore.php';
    require_once __DIR__ . '/../../plugins/magento2/Model/CheckoutAttemptManager.php';

    $connection = new \Magento\Framework\App\FakeConnection();
    $resource = new \Magento\Framework\App\ResourceConnection($connection);
    $claims = new \PayXCommerce\Payment\Model\Webhook\EventClaimStore($resource);
    $hash = hash('sha256', 'payload');

    $first = $claims->claim('evt-1', 10, 2, $hash);
    lifecycleAssert($first['status'] === 'claimed', 'Magento must claim the first delivery.');
    lifecycleAssert($claims->claim('evt-1', 10, 2, $hash)['status'] === 'duplicate', 'Magento must reject an in-flight duplicate.');
    lifecycleAssert($claims->claim('evt-1', 10, 2, hash('sha256', 'different'))['status'] === 'conflict', 'Magento must reject event ID payload conflicts.');
    lifecycleAssert($claims->failed($first, 'transient failure'), 'Magento must persist a failed event.');
    $retry = $claims->claim('evt-1', 10, 2, $hash);
    lifecycleAssert($retry['status'] === 'claimed' && $retry['token'] !== $first['token'], 'Magento failed event must be reclaimable.');
    lifecycleAssert(!$claims->processed($first), 'Magento old worker token must not finalize a reclaimed event.');
    lifecycleAssert($claims->processed($retry), 'Magento retry owner must finalize the event.');

    $attempts = new \PayXCommerce\Payment\Model\CheckoutAttemptManager($resource);
    $order = new FakeOrder(10);
    $payload = ['amount' => '10.00', 'currency' => 'USD'];
    $attempt = $attempts->prepare($order, $payload, 'test');
    $same = $attempts->prepare($order, $payload, 'test');
    lifecycleAssert($same['reused'] && $same['key'] === $attempt['key'], 'Magento transport retry must reuse the persisted attempt key.');
    $attempts->completed($order, ['expires_at' => gmdate('c', time() + 600)]);
    $pending = $attempts->prepare($order, $payload, 'test');
    lifecycleAssert($pending['key'] === $attempt['key'], 'Magento pending request must reuse its stable key.');
    $changed = $attempts->prepare($order, ['amount' => '11.00', 'currency' => 'USD'], 'test');
    lifecycleAssert(!$changed['reused'] && $changed['key'] !== $attempt['key'], 'Magento changed payload must create a new attempt.');
    $attempts->terminal($order, 'payment.expired');
    $afterTerminal = $attempts->prepare($order, ['amount' => '11.00', 'currency' => 'USD'], 'test');
    lifecycleAssert(!$afterTerminal['reused'] && $afterTerminal['key'] !== $changed['key'], 'Magento terminal request must permit a new attempt.');

    $locked = $attempts->withOrderLock(10, 'test', fn () => 'locked');
    lifecycleAssert($locked === 'locked' && !$connection->lockHeld, 'Magento advisory checkout lock must release after the operation.');

    echo "Magento webhook and checkout lifecycle passed\n";
}
