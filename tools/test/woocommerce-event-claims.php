<?php

declare(strict_types=1);

class WC_Order
{
    public function __construct(private readonly int $id)
    {
    }

    public function get_id(): int
    {
        return $this->id;
    }
}

class FakeWpdb
{
    public string $options = 'wp_options';
    public array $rows = [];

    public function prepare(string $sql, mixed ...$args): array
    {
        return ['sql' => $sql, 'args' => $args];
    }

    public function query(array $statement): int
    {
        if (str_starts_with(ltrim($statement['sql']), 'INSERT IGNORE')) {
            $option = (string) ($statement['args'][0] ?? '');
            if (array_key_exists($option, $this->rows)) {
                return 0;
            }
            $this->rows[$option] = (string) ($statement['args'][1] ?? '');
            return 1;
        }

        $newValue = (string) ($statement['args'][0] ?? '');
        $option = (string) ($statement['args'][1] ?? '');
        $expected = (string) ($statement['args'][2] ?? '');
        if (!array_key_exists($option, $this->rows) || !hash_equals($this->rows[$option], $expected)) {
            return 0;
        }
        $this->rows[$option] = $newValue;
        return 1;
    }

    public function get_var(array $statement): string
    {
        return (string) ($this->rows[(string) ($statement['args'][0] ?? '')] ?? '');
    }
}

function wp_json_encode(mixed $value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR);
}

function wp_cache_delete(string $key, string $group): void
{
}

function claimAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require_once __DIR__ . '/../../plugins/woocommerce/includes/Webhook/EventClaimStore.php';

use PayXCommerce\WooCommerce\Webhook\EventClaimStore;

$wpdb = new FakeWpdb();
$store = new EventClaimStore();
$order = new WC_Order(42);
$hash = hash('sha256', '{"event":"payment.success"}');

$first = $store->claim($order, 'evt-1', $hash);
claimAssert($first['status'] === 'claimed', 'First delivery must atomically claim the event.');
claimAssert($store->claim($order, 'evt-1', $hash)['status'] === 'duplicate', 'Concurrent/in-flight duplicate must not claim.');
claimAssert($store->claim($order, 'evt-1', hash('sha256', 'different'))['status'] === 'conflict', 'Reused event ID with a different payload must conflict.');
claimAssert($store->processed($first), 'Claim owner must finalize the event.');
claimAssert($store->claim($order, 'evt-1', $hash)['status'] === 'duplicate', 'Processed event must remain idempotent.');

$failed = $store->claim($order, 'evt-2', $hash);
claimAssert($store->failed($failed), 'Claim owner must persist a failed attempt.');
$retried = $store->claim($order, 'evt-2', $hash);
claimAssert($retried['status'] === 'claimed' && $retried['token'] !== $failed['token'], 'Failed event must be recoverable with a new owner token.');
claimAssert(!$store->processed($failed), 'Prior failed worker must not finalize a reclaimed event.');
claimAssert($store->processed($retried), 'Retry owner must finalize the recovered event.');

$stale = $store->claim($order, 'evt-3', $hash);
$record = json_decode($wpdb->rows[$stale['option']], true, 512, JSON_THROW_ON_ERROR);
$record['claimed_at'] = time() - 301;
$wpdb->rows[$stale['option']] = json_encode($record, JSON_THROW_ON_ERROR);
$recovered = $store->claim($order, 'evt-3', $hash);
claimAssert($recovered['status'] === 'claimed', 'Stale processing claim must be recoverable.');
claimAssert(!$store->failed($stale), 'Stale worker must not overwrite the recovered owner state.');

echo "WooCommerce event claim lifecycle passed\n";
