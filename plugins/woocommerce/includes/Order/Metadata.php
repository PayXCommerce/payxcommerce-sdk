<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce\Order;

use WC_Order;

final class Metadata
{
    public const REQUEST_NUMBER = '_payxcommerce_request_number';
    public const INVOICE_NUMBER = '_payxcommerce_invoice_number';
    public const CHECKOUT_URL = '_payxcommerce_checkout_url';
    public const TRANSACTION_REFERENCE = '_payxcommerce_transaction_reference';
    public const PAYMENT_ID = '_payxcommerce_payment_id';
    public const SETTLEMENT_STATUS = '_payxcommerce_settlement_status';
    public const ENVIRONMENT = '_payxcommerce_environment';
    public const ATTEMPT_ID = '_payxcommerce_checkout_attempt_id';
    public const ATTEMPT_KEY = '_payxcommerce_checkout_attempt_key';
    public const ATTEMPT_FINGERPRINT = '_payxcommerce_checkout_attempt_fingerprint';
    public const ATTEMPT_STATE = '_payxcommerce_checkout_attempt_state';
    public const ATTEMPT_EXPIRES_AT = '_payxcommerce_checkout_attempt_expires_at';
    public const ATTEMPT_STARTED_AT = '_payxcommerce_checkout_attempt_started_at';
    public const RETRY_AT = '_payxcommerce_retry_at';
    public const ATTEMPT_RETURN_URLS = '_payxcommerce_checkout_return_urls';
    private const REFUND_ATTEMPT_PREFIX = '_payxcommerce_refund_attempt_';
    private const REFUND_ACTIVE_PREFIX = '_payxcommerce_refund_active_';

    public function saveCheckout(WC_Order $order, array $response, string $environment): void
    {
        $expiresAt = trim((string) ($response['expires_at'] ?? ''));
        if ($expiresAt === '' || (strtotime($expiresAt) ?: 0) <= 0) {
            $expiresAt = gmdate('c', time() + DAY_IN_SECONDS);
        }

        $order->update_meta_data(self::REQUEST_NUMBER, sanitize_text_field((string) ($response['request_number'] ?? '')));
        $order->update_meta_data(self::INVOICE_NUMBER, sanitize_text_field((string) ($response['invoice_number'] ?? '')));
        $order->update_meta_data(self::CHECKOUT_URL, esc_url_raw((string) ($response['checkout_url'] ?? '')));
        $order->update_meta_data(self::ENVIRONMENT, sanitize_text_field($environment));
        $order->update_meta_data(self::ATTEMPT_STATE, 'pending');
        $order->update_meta_data(self::ATTEMPT_EXPIRES_AT, sanitize_text_field($expiresAt));
    }

    public function preserveAttemptReturnUrls(WC_Order $order, array $payload): array
    {
        // WooCommerce cancel URLs contain a session-specific nonce. A new guest
        // session must not change an unresolved API operation's signed body.
        $fields = ['success_url', 'failed_url', 'cancel_url'];
        $saved = $order->get_meta(self::ATTEMPT_RETURN_URLS);
        if (in_array((string) $order->get_meta(self::ATTEMPT_STATE), ['creating', 'pending'], true)
            && is_array($saved) && count($saved) === count($fields)) {
            foreach ($fields as $field) {
                if (!isset($saved[$field]) || !is_string($saved[$field])) throw new \DomainException('Invalid persisted checkout return URLs.');
                $payload[$field] = $saved[$field];
            }
        } else {
            $order->update_meta_data(self::ATTEMPT_RETURN_URLS, array_intersect_key($payload, array_flip($fields)));
        }
        return $payload;
    }

    public function prepareCheckoutAttempt(WC_Order $order, string $environment, string $fingerprint): array
    {
        $state = (string) $order->get_meta(self::ATTEMPT_STATE);
        $matches = hash_equals((string) $order->get_meta(self::ATTEMPT_FINGERPRINT), $fingerprint)
            && hash_equals((string) $order->get_meta(self::ENVIRONMENT), $environment);

        if ($state === '' && (string) $order->get_meta(self::CHECKOUT_URL) !== '') {
            if ((string) $order->get_meta(self::ENVIRONMENT) !== $environment) throw new \DomainException('Existing checkout mode differs.');
            return ['id'=>'','key'=>'','checkout_url'=>(string)$order->get_meta(self::CHECKOUT_URL),'reused'=>true];
        }

        if (in_array($state, ['creating', 'pending'], true)) {
            // Local expiry, a lost response or a changed payload is not proof
            // that a prior financial operation can be replaced with a new key.
            if (!$matches) throw new \DomainException('An unresolved checkout has different order details or mode.');
            $checkoutUrl = (string) $order->get_meta(self::CHECKOUT_URL);
            $started = (int) $order->get_meta(self::ATTEMPT_STARTED_AT);
            if ($checkoutUrl === '' && (!$started || $started < time() - 23 * HOUR_IN_SECONDS || (string)$order->get_meta(self::ATTEMPT_KEY) === '')) {
                throw new \DomainException('Unresolved checkout is outside the safe idempotency retry window.');
            }
            return [
                'id' => (string) $order->get_meta(self::ATTEMPT_ID),
                'key' => (string) $order->get_meta(self::ATTEMPT_KEY),
                'checkout_url' => (string) $order->get_meta(self::CHECKOUT_URL),
                'reused' => true,
            ];
        }

        $attemptId = bin2hex(random_bytes(16));
        $key = 'woocommerce-order-' . $order->get_id() . '-' . $environment . '-' . $attemptId;
        $order->update_meta_data(self::ATTEMPT_ID, $attemptId);
        $order->update_meta_data(self::ATTEMPT_KEY, $key);
        $order->update_meta_data(self::ATTEMPT_FINGERPRINT, $fingerprint);
        $order->update_meta_data(self::ATTEMPT_STATE, 'creating');
        $order->update_meta_data(self::ATTEMPT_EXPIRES_AT, '');
        $order->update_meta_data(self::ATTEMPT_STARTED_AT, time());
        $order->update_meta_data(self::RETRY_AT, 0);
        $order->update_meta_data(self::CHECKOUT_URL, '');
        $order->update_meta_data(self::ENVIRONMENT, $environment);

        return ['id' => $attemptId, 'key' => $key, 'checkout_url' => '', 'reused' => false];
    }

    public function markCheckoutAttemptTerminal(WC_Order $order, string $state): void
    {
        $order->update_meta_data(self::ATTEMPT_STATE, sanitize_key($state));
    }

    public function withCheckoutLock(int $orderId, string $environment, callable $callback): mixed
    {
        global $wpdb;

        $lockName = 'pxc_wc_co_' . substr(hash('sha256', $orderId . '|' . $environment), 0, 52);
        $acquired = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lockName));
        if ($acquired !== 1) {
            throw new \RuntimeException('Another checkout attempt is already being prepared.');
        }

        try {
            return $callback();
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }
    }

    public function prepareRefundAttempt(WC_Order $order, string $amount, string $reason, string $environment): array
    {
        $baseFingerprint = hash('sha256', $environment . '|' . $amount . '|' . trim($reason));
        $operationReference = $this->refundOperationReference($order);
        $operationKey = self::REFUND_ATTEMPT_PREFIX . hash('sha256', $baseFingerprint . '|' . $operationReference);
        $existing = $this->decodeAttempt((string) $order->get_meta($operationKey));

        if (($existing['state'] ?? '') === 'succeeded') {
            return array_merge($existing, [
                'operation_key' => $operationKey,
                'active_key' => self::REFUND_ACTIVE_PREFIX . $baseFingerprint,
                'completed' => true,
                'reused' => true,
            ]);
        }

        $activeKey = self::REFUND_ACTIVE_PREFIX . $baseFingerprint;
        $active = $this->decodeAttempt((string) $order->get_meta($activeKey));
        $activeExpiresAt = strtotime((string) ($active['expires_at'] ?? '')) ?: 0;
        if (in_array((string) ($active['state'] ?? ''), ['creating', 'uncertain'], true)
            && $activeExpiresAt > time()
            && !empty($active['key'])) {
            $active['operation_reference'] = $operationReference;
            $order->update_meta_data($operationKey, wp_json_encode($active));

            return array_merge($active, [
                'operation_key' => $operationKey,
                'active_key' => $activeKey,
                'completed' => false,
                'reused' => true,
            ]);
        }

        $attemptId = bin2hex(random_bytes(16));
        $attempt = [
            'id' => $attemptId,
            'key' => 'woocommerce-refund-' . $order->get_id() . '-' . $environment . '-' . $attemptId,
            'state' => 'creating',
            'operation_reference' => $operationReference,
            'expires_at' => gmdate('c', time() + DAY_IN_SECONDS),
            'refund_reference' => '',
        ];
        $encoded = wp_json_encode($attempt);
        $order->update_meta_data($operationKey, $encoded);
        $order->update_meta_data($activeKey, $encoded);

        return array_merge($attempt, [
            'operation_key' => $operationKey,
            'active_key' => $activeKey,
            'completed' => false,
            'reused' => false,
        ]);
    }

    public function markRefundAttempt(WC_Order $order, array $attempt, string $state, string $refundReference = ''): void
    {
        $record = [
            'id' => (string) ($attempt['id'] ?? ''),
            'key' => (string) ($attempt['key'] ?? ''),
            'state' => sanitize_key($state),
            'operation_reference' => (string) ($attempt['operation_reference'] ?? ''),
            'expires_at' => (string) ($attempt['expires_at'] ?? gmdate('c', time() + DAY_IN_SECONDS)),
            'refund_reference' => sanitize_text_field($refundReference),
        ];
        $encoded = wp_json_encode($record);
        $order->update_meta_data((string) $attempt['operation_key'], $encoded);
        $order->update_meta_data((string) $attempt['active_key'], $encoded);
    }

    public function withRefundLock(int $orderId, string $environment, callable $callback): mixed
    {
        global $wpdb;

        $lockName = 'pxc_wc_rf_' . substr(hash('sha256', $orderId . '|' . $environment), 0, 52);
        $acquired = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lockName));
        if ($acquired !== 1) {
            throw new \RuntimeException('Another refund attempt is already being prepared.');
        }

        try {
            return $callback();
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }
    }

    private function refundOperationReference(WC_Order $order): string
    {
        $latestId = 0;
        if (method_exists($order, 'get_refunds')) {
            foreach ((array) $order->get_refunds() as $refund) {
                if (is_object($refund) && method_exists($refund, 'get_id')) {
                    $latestId = max($latestId, (int) $refund->get_id());
                }
            }
        }

        return $latestId > 0 ? 'wc-refund-' . $latestId : 'wc-refund-unassigned';
    }

    private function decodeAttempt(string $encoded): array
    {
        $decoded = json_decode($encoded, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function markEvent(WC_Order $order, string $eventId): void
    {
        $order->update_meta_data('_payxcommerce_event_' . sanitize_key($eventId), current_time('mysql'));
    }

    public function hasEvent(WC_Order $order, string $eventId): bool
    {
        return $eventId !== '' && (bool) $order->get_meta('_payxcommerce_event_' . sanitize_key($eventId));
    }
}
