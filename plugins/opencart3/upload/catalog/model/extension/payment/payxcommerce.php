<?php

require_once DIR_SYSTEM . 'library/payxcommerce_decimal.php';

class ModelExtensionPaymentPayXCommerce extends Model
{
    public function getMethod($address, $total)
    {
        if (!$this->config->get('payment_payxcommerce_status') || !$this->isConfigured()) {
            return [];
        }

        $geo_zone_id = (int) $this->config->get('payment_payxcommerce_geo_zone_id');
        if ($geo_zone_id > 0) {
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . $geo_zone_id . "' AND country_id = '" . (int) ($address['country_id'] ?? 0) . "' AND (zone_id = '" . (int) ($address['zone_id'] ?? 0) . "' OR zone_id = '0')");
            if (!$query->num_rows) {
                return [];
            }
        }

        $currency = strtoupper((string) ($this->session->data['currency'] ?? $this->config->get('config_currency')));
        $allowed_currencies = $this->csvConfig('payment_payxcommerce_allowed_currencies');
        if ($allowed_currencies && !in_array($currency, $allowed_currencies, true)) {
            return [];
        }

        $country = strtoupper((string) ($address['iso_code_2'] ?? ''));
        $allowed_countries = $this->csvConfig('payment_payxcommerce_allowed_countries');
        if ($country !== '' && $allowed_countries && !in_array($country, $allowed_countries, true)) {
            return [];
        }

        $total = (string) $total;
        $min_total = (string) ($this->config->get('payment_payxcommerce_min_total') ?: '0');
        $max_total = (string) ($this->config->get('payment_payxcommerce_max_total') ?: '0');
        if (!PayXCommerceDecimal::isValid($total) || !PayXCommerceDecimal::isValid($min_total) || !PayXCommerceDecimal::isValid($max_total)) {
            return [];
        }
        if (PayXCommerceDecimal::isPositive($min_total) && PayXCommerceDecimal::compare($total, $min_total) === -1) {
            return [];
        }
        if (PayXCommerceDecimal::isPositive($max_total) && PayXCommerceDecimal::compare($total, $max_total) === 1) {
            return [];
        }

        $title = $this->publicText('payment_payxcommerce_title', 'Pay securely with {brand}');
        $icon = '<img src="catalog/view/theme/default/image/payxcommerce/logo-icon-dark-64.png" alt="' . htmlspecialchars($this->brandName(), ENT_QUOTES, 'UTF-8') . '" style="height:24px;width:24px;border-radius:5px;margin-right:8px;vertical-align:middle;">';

        return [
            'code' => 'payxcommerce',
            'title' => $icon . $title,
            'terms' => '',
            'sort_order' => (int) $this->config->get('payment_payxcommerce_sort_order'),
        ];
    }

    public function savePayxOrder(int $order_id, array $response, string $merchant_reference): void
    {
        $this->ensureCheckoutAttemptSchema();
        $expires_at = !empty($response['expires_at']) && strtotime((string) $response['expires_at'])
            ? strtotime((string) $response['expires_at'])
            : time() + 86400;
        $expires_sql = "'" . $this->db->escape(date('Y-m-d H:i:s', $expires_at)) . "'";
        $this->db->query("INSERT INTO `" . DB_PREFIX . "payxcommerce_order` SET order_id = '" . (int) $order_id . "', payx_request_number = '" . $this->db->escape((string) ($response['request_number'] ?? '')) . "', payx_invoice_number = '" . $this->db->escape((string) ($response['invoice_number'] ?? '')) . "', merchant_reference = '" . $this->db->escape($merchant_reference) . "', checkout_url = '" . $this->db->escape((string) ($response['checkout_url'] ?? '')) . "', checkout_state = 'pending', checkout_expires_at = " . $expires_sql . ", payment_status = 'created', created_at = NOW(), updated_at = NOW() ON DUPLICATE KEY UPDATE payx_request_number = VALUES(payx_request_number), payx_invoice_number = VALUES(payx_invoice_number), merchant_reference = VALUES(merchant_reference), checkout_url = VALUES(checkout_url), checkout_state = VALUES(checkout_state), checkout_expires_at = VALUES(checkout_expires_at), payment_status = VALUES(payment_status), updated_at = NOW()");
    }

    public function acquireCheckoutLock(int $order_id, string $environment): bool
    {
        $name = 'payx_oc3_checkout_' . hash('sha256', $order_id . '|' . $environment);
        $query = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($name) . "', 10) AS acquired");
        return (int) ($query->row['acquired'] ?? 0) === 1;
    }

    public function releaseCheckoutLock(int $order_id, string $environment): void
    {
        $name = 'payx_oc3_checkout_' . hash('sha256', $order_id . '|' . $environment);
        $this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($name) . "')");
    }

    public function prepareCheckoutAttempt(int $order_id, string $environment, string $fingerprint, string $merchant_reference): array
    {
        $this->ensureCheckoutAttemptSchema();
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "payxcommerce_order` WHERE order_id = '" . (int) $order_id . "' LIMIT 1");
        $row = $query->num_rows ? $query->row : [];
        $expires_at = (string) ($row['checkout_expires_at'] ?? '');
        $expired = $expires_at !== '' && (strtotime($expires_at) ?: 0) <= time();
        $matches = hash_equals((string) ($row['checkout_fingerprint'] ?? ''), $fingerprint)
            && hash_equals((string) ($row['checkout_environment'] ?? ''), $environment);

        if ($matches && !$expired && in_array((string) ($row['checkout_state'] ?? ''), ['creating', 'pending'], true)) {
            return [
                'id' => (string) ($row['checkout_attempt_id'] ?? ''),
                'key' => (string) ($row['checkout_attempt_key'] ?? ''),
                'checkout_url' => (string) ($row['checkout_url'] ?? ''),
                'reused' => true,
            ];
        }

        $attempt_id = bin2hex(random_bytes(16));
        $key = 'opencart-3-order-' . $order_id . '-' . $environment . '-' . $attempt_id;
        $this->db->query("INSERT INTO `" . DB_PREFIX . "payxcommerce_order` SET order_id = '" . (int) $order_id . "', merchant_reference = '" . $this->db->escape($merchant_reference) . "', checkout_attempt_id = '" . $attempt_id . "', checkout_attempt_key = '" . $this->db->escape($key) . "', checkout_fingerprint = '" . $this->db->escape($fingerprint) . "', checkout_environment = '" . $this->db->escape($environment) . "', checkout_state = 'creating', created_at = NOW(), updated_at = NOW() ON DUPLICATE KEY UPDATE payx_request_number = NULL, payx_invoice_number = NULL, payx_payment_id = NULL, payx_transaction_reference = NULL, merchant_reference = VALUES(merchant_reference), checkout_url = NULL, checkout_attempt_id = VALUES(checkout_attempt_id), checkout_attempt_key = VALUES(checkout_attempt_key), checkout_fingerprint = VALUES(checkout_fingerprint), checkout_environment = VALUES(checkout_environment), checkout_state = 'creating', checkout_expires_at = NULL, payment_status = 'creating', updated_at = NOW()");

        return ['id' => $attempt_id, 'key' => $key, 'checkout_url' => '', 'reused' => false];
    }

    public function findOrderId(array $payload): int
    {
        foreach ([
            'request_number' => 'payx_request_number',
            'payment_request_reference' => 'payx_request_number',
            'payment_request_id' => 'payx_request_number',
            'payment_request_number' => 'payx_request_number',
            'reference' => 'payx_request_number',
            'invoice_number' => 'payx_invoice_number',
            'transaction_reference' => 'payx_transaction_reference',
            'gateway_transaction_id' => 'payx_transaction_reference',
            'merchant_reference' => 'merchant_reference',
        ] as $payload_key => $column) {
            foreach ($this->payloadCandidates($payload, $payload_key) as $candidate) {
                if ($candidate === '') {
                    continue;
                }
                $query = $this->db->query("SELECT order_id FROM `" . DB_PREFIX . "payxcommerce_order` WHERE `" . $column . "` = '" . $this->db->escape($candidate) . "' LIMIT 1");
                if ($query->num_rows) {
                    return (int) $query->row['order_id'];
                }
            }
        }

        return 0;
    }

    public function claimWebhookEvent(string $event_id, int $order_id, string $event_type, string $raw_body): ?string
    {
        $this->ensureWebhookClaimSchema();
        if (!$this->acquireWebhookEventLock($event_id)) {
            return null;
        }

        $payload_hash = hash('sha256', $raw_body);
        $claim_token = bin2hex(random_bytes(16));
        $retryable = "((processing_status = 'failed' OR (processing_status = 'processing' AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))) AND payload_hash = VALUES(payload_hash))";
        try {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "payxcommerce_webhook_event` SET event_id = '" . $this->db->escape($event_id) . "', order_id = '" . (int) $order_id . "', event_type = '" . $this->db->escape($event_type) . "', payload_hash = '" . $payload_hash . "', claim_token = '" . $claim_token . "', processing_status = 'processing', created_at = NOW() ON DUPLICATE KEY UPDATE order_id = IF(" . $retryable . ", VALUES(order_id), order_id), event_type = IF(" . $retryable . ", VALUES(event_type), event_type), claim_token = IF(" . $retryable . ", VALUES(claim_token), claim_token), error_message = IF(" . $retryable . ", NULL, error_message), processed_at = IF(" . $retryable . ", NULL, processed_at), created_at = IF(" . $retryable . ", NOW(), created_at), processing_status = IF(" . $retryable . ", 'processing', processing_status)");
        } catch (Throwable $exception) {
            $this->releaseWebhookEventLock($event_id);
            throw $exception;
        }

        if ($this->db->countAffected() <= 0) {
            $this->releaseWebhookEventLock($event_id);
            return null;
        }

        return $claim_token;
    }

    public function renewWebhookEventClaim(string $event_id, string $claim_token): bool
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "payxcommerce_webhook_event` SET created_at = NOW() WHERE event_id = '" . $this->db->escape($event_id) . "' AND claim_token = '" . $this->db->escape($claim_token) . "' AND processing_status = 'processing'");
        $query = $this->db->query("SELECT event_id FROM `" . DB_PREFIX . "payxcommerce_webhook_event` WHERE event_id = '" . $this->db->escape($event_id) . "' AND claim_token = '" . $this->db->escape($claim_token) . "' AND processing_status = 'processing' LIMIT 1");

        return (bool) $query->num_rows;
    }

    public function completeWebhookEvent(string $event_id, string $claim_token, string $status = 'processed', string $error = ''): bool
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "payxcommerce_webhook_event` SET processing_status = '" . $this->db->escape($status) . "', error_message = '" . $this->db->escape($error) . "', processed_at = NOW() WHERE event_id = '" . $this->db->escape($event_id) . "' AND claim_token = '" . $this->db->escape($claim_token) . "' AND processing_status = 'processing'");

        return $this->db->countAffected() > 0;
    }

    public function releaseWebhookEventLock(string $event_id): void
    {
        $this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($this->webhookEventLockName($event_id)) . "')");
    }

    public function updatePayxOrder(int $order_id, array $payload): void
    {
        $payment_id = $this->firstPayloadValue($payload, ['payment_id', 'data.payment_id', 'payload.payment_id', 'resource.payment_id']);
        $transaction_reference = $this->firstPayloadValue($payload, ['transaction_reference', 'gateway_transaction_id', 'data.transaction_reference', 'data.gateway_transaction_id', 'payload.transaction_reference', 'payload.gateway_transaction_id', 'resource.transaction_reference', 'resource.gateway_transaction_id']);
        $settlement_status = $this->firstPayloadValue($payload, ['settlement_status', 'data.settlement_status', 'payload.settlement_status', 'resource.settlement_status']);
        $event_type = (string) ($payload['event_type'] ?? '');

        $terminal = in_array($event_type, ['payment.success', 'payment.succeeded', 'payment.failed', 'payment.cancelled', 'payment.canceled', 'payment.expired'], true);
        $this->db->query("UPDATE `" . DB_PREFIX . "payxcommerce_order` SET payx_payment_id = IF('" . $this->db->escape((string) $payment_id) . "' = '', payx_payment_id, '" . $this->db->escape((string) $payment_id) . "'), payx_transaction_reference = IF('" . $this->db->escape((string) $transaction_reference) . "' = '', payx_transaction_reference, '" . $this->db->escape((string) $transaction_reference) . "'), payment_status = IF('" . $this->db->escape($event_type) . "' = '', payment_status, '" . $this->db->escape($event_type) . "'), checkout_state = " . ($terminal ? "'" . $this->db->escape($event_type) . "'" : 'checkout_state') . ", settlement_status = IF('" . $this->db->escape((string) $settlement_status) . "' = '', settlement_status, '" . $this->db->escape((string) $settlement_status) . "'), updated_at = NOW() WHERE order_id = '" . (int) $order_id . "'");
    }

    private function ensureCheckoutAttemptSchema(): void
    {
        $columns = [
            'checkout_attempt_id' => 'VARCHAR(64) DEFAULT NULL',
            'checkout_attempt_key' => 'VARCHAR(191) DEFAULT NULL',
            'checkout_fingerprint' => 'VARCHAR(64) DEFAULT NULL',
            'checkout_environment' => 'VARCHAR(8) DEFAULT NULL',
            'checkout_state' => 'VARCHAR(32) DEFAULT NULL',
            'checkout_expires_at' => 'DATETIME DEFAULT NULL',
        ];
        foreach ($columns as $column => $definition) {
            $exists = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "payxcommerce_order` LIKE '" . $this->db->escape($column) . "'");
            if (!$exists->num_rows) {
                $this->db->query("ALTER TABLE `" . DB_PREFIX . "payxcommerce_order` ADD `" . $column . "` " . $definition);
            }
        }
    }

    private function ensureWebhookClaimSchema(): void
    {
        $exists = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "payxcommerce_webhook_event` LIKE 'claim_token'");
        if (!$exists->num_rows) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "payxcommerce_webhook_event` ADD `claim_token` VARCHAR(64) DEFAULT NULL AFTER `payload_hash`");
        }
    }

    private function acquireWebhookEventLock(string $event_id): bool
    {
        $query = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($this->webhookEventLockName($event_id)) . "', 0) AS acquired");

        return (int) ($query->row['acquired'] ?? 0) === 1;
    }

    private function webhookEventLockName(string $event_id): string
    {
        return 'payx_oc3_webhook_' . hash('sha256', $event_id);
    }

    private function payloadCandidates(array $payload, string $key): array
    {
        $values = [];
        foreach ([$key, 'data.' . $key, 'payload.' . $key, 'resource.' . $key] as $path) {
            $value = $this->payloadValue($payload, $path);
            if ($value !== null && $value !== '') {
                $values[] = (string) $value;
            }
        }

        return array_values(array_unique($values));
    }

    private function firstPayloadValue(array $payload, array $paths)
    {
        foreach ($paths as $path) {
            $value = $this->payloadValue($payload, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function payloadValue(array $payload, string $path)
    {
        $value = $payload;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function updateCheckoutContact(array $order, string $phone, array $country): void
    {
        $order_id = (int) ($order['order_id'] ?? 0);
        $country_id = (int) ($country['country_id'] ?? 0);
        $country_name = (string) ($country['name'] ?? '');
        $country_iso = strtoupper((string) ($country['iso_code_2'] ?? ''));

        if ($order_id > 0) {
            $updates = ["telephone = '" . $this->db->escape($phone) . "'"];
            if ($country_iso !== '') {
                $updates[] = "payment_country = '" . $this->db->escape($country_name ?: $country_iso) . "'";
                if ($this->orderColumnExists('payment_country_id')) {
                    $updates[] = "payment_country_id = '" . $country_id . "'";
                }
                if ($this->orderColumnExists('payment_iso_code_2')) {
                    $updates[] = "payment_iso_code_2 = '" . $this->db->escape($country_iso) . "'";
                }
                if ($this->orderColumnExists('shipping_country')) {
                    $updates[] = "shipping_country = IF(shipping_country = '', '" . $this->db->escape($country_name ?: $country_iso) . "', shipping_country)";
                }
                if ($this->orderColumnExists('shipping_country_id')) {
                    $updates[] = "shipping_country_id = IF(shipping_country_id = '0', '" . $country_id . "', shipping_country_id)";
                }
                if ($this->orderColumnExists('shipping_iso_code_2')) {
                    $updates[] = "shipping_iso_code_2 = IF(shipping_iso_code_2 = '', '" . $this->db->escape($country_iso) . "', shipping_iso_code_2)";
                }
            }
            $this->db->query("UPDATE `" . DB_PREFIX . "order` SET " . implode(', ', $updates) . " WHERE order_id = '" . $order_id . "'");
        }

        $customer_id = (int) ($order['customer_id'] ?? ($this->customer->isLogged() ? $this->customer->getId() : 0));
        if ($customer_id > 0 && $phone !== '') {
            $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET telephone = '" . $this->db->escape($phone) . "' WHERE customer_id = '" . $customer_id . "'");
        }

        if ($customer_id > 0 && $country_id > 0) {
            $address_ids = [];
            if (!empty($order['payment_address_id'])) {
                $address_ids[] = (int) $order['payment_address_id'];
            }
            if (!empty($this->session->data['payment_address']['address_id'])) {
                $address_ids[] = (int) $this->session->data['payment_address']['address_id'];
            }
            foreach (array_unique(array_filter($address_ids)) as $address_id) {
                $this->db->query("UPDATE `" . DB_PREFIX . "address` SET country_id = '" . $country_id . "' WHERE address_id = '" . $address_id . "' AND customer_id = '" . $customer_id . "'");
            }
        }

        if ($country_id > 0) {
            $this->session->data['payment_address']['country_id'] = $country_id;
            $this->session->data['payment_address']['country'] = $country_name;
            $this->session->data['payment_address']['iso_code_2'] = $country_iso;
        }
    }

    private function orderColumnExists(string $column): bool
    {
        $query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "order` LIKE '" . $this->db->escape($column) . "'");
        return (bool) $query->num_rows;
    }

    private function csvConfig(string $key): array
    {
        $raw = (string) $this->config->get($key);
        return array_values(array_filter(array_map(static function ($value) {
            return strtoupper(trim($value));
        }, explode(',', $raw))));
    }

    private function isConfigured(): bool
    {
        if (!$this->config->get('payment_payxcommerce_webhook_secret')) {
            return false;
        }

        if ($this->config->get('payment_payxcommerce_auth_method') === 'bearer') {
            return (bool) $this->config->get('payment_payxcommerce_client_id') && (bool) $this->config->get('payment_payxcommerce_client_secret');
        }

        return (bool) $this->config->get('payment_payxcommerce_public_key') && (bool) $this->config->get('payment_payxcommerce_secret_key');
    }

    private function publicText(string $key, string $default): string
    {
        $value = (string) ($this->config->get($key) ?: $default);
        return str_replace('{brand}', $this->brandName(), $value);
    }

    private function brandName(): string
    {
        return (string) ($this->config->get('payment_payxcommerce_brand_name') ?: 'PayXCommerce');
    }
}
