<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce\Webhook;

use WC_Order;

final class EventClaimStore
{
    private const STALE_AFTER_SECONDS = 300;

    public function claim(WC_Order $order, string $eventId, string $payloadHash): array
    {
        global $wpdb;

        $eventId = trim($eventId);
        if ($eventId === '') {
            return ['status' => 'conflict', 'token' => '', 'option' => ''];
        }

        $option = 'payxcommerce_event_claim_' . hash('sha256', $order->get_id() . '|' . $eventId);
        $token = bin2hex(random_bytes(16));
        $record = $this->record('processing', $payloadHash, $token);
        $encoded = wp_json_encode($record);
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            $option,
            $encoded
        ));

        if ((int) $inserted === 1) {
            wp_cache_delete($option, 'options');
            return ['status' => 'claimed', 'token' => $token, 'option' => $option];
        }

        $currentRaw = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            $option
        ));
        $current = json_decode($currentRaw, true);
        if (!is_array($current) || !hash_equals((string) ($current['payload_hash'] ?? ''), $payloadHash)) {
            return ['status' => 'conflict', 'token' => '', 'option' => $option];
        }

        $retryable = ($current['status'] ?? '') === 'failed'
            || (($current['status'] ?? '') === 'processing'
                && (int) ($current['claimed_at'] ?? 0) <= time() - self::STALE_AFTER_SECONDS);
        if (!$retryable) {
            return ['status' => 'duplicate', 'token' => '', 'option' => $option];
        }

        $reclaimed = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
            $encoded,
            $option,
            $currentRaw
        ));
        wp_cache_delete($option, 'options');

        return (int) $reclaimed === 1
            ? ['status' => 'claimed', 'token' => $token, 'option' => $option]
            : ['status' => 'duplicate', 'token' => '', 'option' => $option];
    }

    public function processed(array $claim): bool
    {
        return $this->transition($claim, 'processed');
    }

    public function failed(array $claim): bool
    {
        return $this->transition($claim, 'failed');
    }

    private function transition(array $claim, string $status): bool
    {
        global $wpdb;

        $option = (string) ($claim['option'] ?? '');
        $token = (string) ($claim['token'] ?? '');
        if ($option === '' || $token === '') {
            return false;
        }

        $currentRaw = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            $option
        ));
        $current = json_decode($currentRaw, true);
        if (!is_array($current) || !hash_equals((string) ($current['token'] ?? ''), $token)) {
            return false;
        }

        $current['status'] = $status;
        $current['completed_at'] = time();
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
            wp_json_encode($current),
            $option,
            $currentRaw
        ));
        wp_cache_delete($option, 'options');

        return (int) $updated === 1;
    }

    private function record(string $status, string $payloadHash, string $token): array
    {
        return [
            'status' => $status,
            'payload_hash' => $payloadHash,
            'token' => $token,
            'claimed_at' => time(),
            'completed_at' => null,
        ];
    }
}
