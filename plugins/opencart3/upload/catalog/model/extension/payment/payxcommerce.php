<?php

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

        $min_total = (float) $this->config->get('payment_payxcommerce_min_total');
        $max_total = (float) $this->config->get('payment_payxcommerce_max_total');
        if ($min_total > 0 && (float) $total < $min_total) {
            return [];
        }
        if ($max_total > 0 && (float) $total > $max_total) {
            return [];
        }

        return [
            'code' => 'payxcommerce',
            'title' => $this->publicText('payment_payxcommerce_title', 'Pay securely with {brand}'),
            'terms' => '',
            'sort_order' => (int) $this->config->get('payment_payxcommerce_sort_order'),
        ];
    }

    public function savePayxOrder(int $order_id, array $response, string $merchant_reference): void
    {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "payxcommerce_order` SET order_id = '" . (int) $order_id . "', payx_request_number = '" . $this->db->escape((string) ($response['request_number'] ?? '')) . "', payx_invoice_number = '" . $this->db->escape((string) ($response['invoice_number'] ?? '')) . "', merchant_reference = '" . $this->db->escape($merchant_reference) . "', checkout_url = '" . $this->db->escape((string) ($response['checkout_url'] ?? '')) . "', payment_status = 'created', created_at = NOW(), updated_at = NOW() ON DUPLICATE KEY UPDATE payx_request_number = VALUES(payx_request_number), payx_invoice_number = VALUES(payx_invoice_number), merchant_reference = VALUES(merchant_reference), checkout_url = VALUES(checkout_url), payment_status = VALUES(payment_status), updated_at = NOW()");
    }

    public function findOrderId(array $payload): int
    {
        if (!empty($payload['metadata']['order_id'])) {
            return (int) $payload['metadata']['order_id'];
        }
        if (!empty($payload['merchant_order_id'])) {
            return (int) $payload['merchant_order_id'];
        }

        foreach (['request_number' => 'payx_request_number', 'invoice_number' => 'payx_invoice_number', 'transaction_reference' => 'payx_transaction_reference'] as $payload_key => $column) {
            if (empty($payload[$payload_key])) {
                continue;
            }
            $query = $this->db->query("SELECT order_id FROM `" . DB_PREFIX . "payxcommerce_order` WHERE `" . $column . "` = '" . $this->db->escape((string) $payload[$payload_key]) . "' LIMIT 1");
            if ($query->num_rows) {
                return (int) $query->row['order_id'];
            }
        }

        return 0;
    }

    public function webhookEventExists(string $event_id): bool
    {
        $query = $this->db->query("SELECT id FROM `" . DB_PREFIX . "payxcommerce_webhook_event` WHERE event_id = '" . $this->db->escape($event_id) . "' LIMIT 1");
        return (bool) $query->num_rows;
    }

    public function recordWebhookEvent(string $event_id, int $order_id, string $event_type, string $raw_body): void
    {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "payxcommerce_webhook_event` SET event_id = '" . $this->db->escape($event_id) . "', order_id = '" . (int) $order_id . "', event_type = '" . $this->db->escape($event_type) . "', payload_hash = '" . hash('sha256', $raw_body) . "', processing_status = 'processing', created_at = NOW()");
    }

    public function completeWebhookEvent(string $event_id, string $status = 'processed', string $error = ''): void
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "payxcommerce_webhook_event` SET processing_status = '" . $this->db->escape($status) . "', error_message = '" . $this->db->escape($error) . "', processed_at = NOW() WHERE event_id = '" . $this->db->escape($event_id) . "'");
    }

    public function updatePayxOrder(int $order_id, array $payload): void
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "payxcommerce_order` SET payx_payment_id = '" . $this->db->escape((string) ($payload['payment_id'] ?? '')) . "', payx_transaction_reference = '" . $this->db->escape((string) ($payload['transaction_reference'] ?? '')) . "', payment_status = '" . $this->db->escape((string) ($payload['event_type'] ?? '')) . "', settlement_status = '" . $this->db->escape((string) ($payload['settlement_status'] ?? '')) . "', updated_at = NOW() WHERE order_id = '" . (int) $order_id . "'");
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
        $brand = (string) ($this->config->get('payment_payxcommerce_brand_name') ?: 'PayXCommerce');
        $value = (string) ($this->config->get($key) ?: $default);
        return str_replace('{brand}', $brand, $value);
    }
}
