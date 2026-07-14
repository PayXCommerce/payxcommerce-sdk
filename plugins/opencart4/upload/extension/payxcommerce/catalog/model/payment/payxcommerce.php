<?php
namespace Opencart\Catalog\Model\Extension\Payxcommerce\Payment;

class Payxcommerce extends \Opencart\System\Engine\Model
{
    public function getMethods(array $address = []): array
    {
        $this->load->language('extension/payxcommerce/payment/payxcommerce');

        if (!$this->config->get('payment_payxcommerce_status') || !$this->isConfigured()) {
            return [];
        }

        $geo_zone_id = (int) $this->config->get('payment_payxcommerce_geo_zone_id');
        if ($geo_zone_id > 0) {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE geo_zone_id = '" . $geo_zone_id . "' AND country_id = '" . (int) ($address['country_id'] ?? 0) . "' AND (zone_id = '" . (int) ($address['zone_id'] ?? 0) . "' OR zone_id = '0')");
            if (!$query->num_rows) {
                return [];
            }
        }

        $total = (float) ($this->cart ? $this->cart->getTotal() : 0);
        $min_total = (float) $this->config->get('payment_payxcommerce_min_total');
        $max_total = (float) $this->config->get('payment_payxcommerce_max_total');
        if (($min_total > 0 && $total < $min_total) || ($max_total > 0 && $total > $max_total)) {
            return [];
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

        $title = $this->publicText('payment_payxcommerce_title', 'Pay securely with {brand}');
        $icon = '<img src="extension/payxcommerce/catalog/view/image/payxcommerce/logo-icon-dark-64.png" alt="' . htmlspecialchars($this->brandName(), ENT_QUOTES, 'UTF-8') . '" style="height:24px;width:24px;border-radius:5px;margin-right:8px;vertical-align:middle;">';
        $title_with_icon = $icon . $title;
        $option_data = [
            'payxcommerce' => [
                'code' => 'payxcommerce.payxcommerce',
                'name' => $title_with_icon,
            ],
        ];

        return [
            'code' => 'payxcommerce',
            'name' => $title_with_icon,
            'option' => $option_data,
            'sort_order' => (int) $this->config->get('payment_payxcommerce_sort_order'),
        ];
    }

    public function savePayxOrder(int $order_id, array $response, string $merchant_reference): void
    {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "payxcommerce_order` SET order_id = '" . (int) $order_id . "', payx_request_number = '" . $this->db->escape((string) ($response['request_number'] ?? '')) . "', payx_invoice_number = '" . $this->db->escape((string) ($response['invoice_number'] ?? '')) . "', merchant_reference = '" . $this->db->escape($merchant_reference) . "', checkout_url = '" . $this->db->escape((string) ($response['checkout_url'] ?? '')) . "', payment_status = 'created', created_at = NOW(), updated_at = NOW() ON DUPLICATE KEY UPDATE payx_request_number = VALUES(payx_request_number), payx_invoice_number = VALUES(payx_invoice_number), merchant_reference = VALUES(merchant_reference), checkout_url = VALUES(checkout_url), payment_status = VALUES(payment_status), updated_at = NOW()");
    }

    public function findOrderId(array $payload): int
    {
        foreach ([
            'metadata.order_id',
            'metadata.opencart_order_id',
            'data.metadata.order_id',
            'data.metadata.opencart_order_id',
            'payload.metadata.order_id',
            'payload.metadata.opencart_order_id',
            'resource.metadata.order_id',
            'resource.metadata.opencart_order_id',
            'merchant_order_id',
            'merchant_reference',
            'order_id',
            'data.merchant_order_id',
            'data.merchant_reference',
            'data.order_id',
            'payload.merchant_order_id',
            'payload.merchant_reference',
            'payload.order_id',
            'resource.merchant_order_id',
            'resource.merchant_reference',
            'resource.order_id',
        ] as $path) {
            $value = $this->payloadValue($payload, $path);
            if ($value === null || $value === '') {
                continue;
            }
            if (is_numeric($value)) {
                return (int) $value;
            }
            if (preg_match('/^OC4-(\d+)$/i', (string) $value, $matches)) {
                return (int) $matches[1];
            }
        }

        foreach ([
            'request_number' => 'payx_request_number',
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

    public function returnReferenceMatches(int $order_id, string $merchant_reference): bool
    {
        if ($order_id <= 0 || $merchant_reference === '') {
            return false;
        }

        $query = $this->db->query("SELECT order_id FROM `" . DB_PREFIX . "payxcommerce_order` WHERE order_id = '" . (int) $order_id . "' AND merchant_reference = '" . $this->db->escape($merchant_reference) . "' LIMIT 1");
        return (bool) $query->num_rows;
    }

    public function markReturnSuccess(int $order_id): void
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "payxcommerce_order` SET payment_status = 'payment.return_success', updated_at = NOW() WHERE order_id = '" . (int) $order_id . "' AND payment_status NOT IN ('payment.success', 'payment.succeeded', 'payment.refunded', 'refund.success', 'refund.succeeded')");
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
        $payment_id = $this->firstPayloadValue($payload, ['payment_id', 'data.payment_id', 'payload.payment_id', 'resource.payment_id']);
        $transaction_reference = $this->firstPayloadValue($payload, ['transaction_reference', 'gateway_transaction_id', 'data.transaction_reference', 'data.gateway_transaction_id', 'payload.transaction_reference', 'payload.gateway_transaction_id', 'resource.transaction_reference', 'resource.gateway_transaction_id']);
        $settlement_status = $this->firstPayloadValue($payload, ['settlement_status', 'data.settlement_status', 'payload.settlement_status', 'resource.settlement_status']);
        $event_type = (string) ($payload['event_type'] ?? '');

        $this->db->query("UPDATE `" . DB_PREFIX . "payxcommerce_order` SET payx_payment_id = IF('" . $this->db->escape((string) $payment_id) . "' = '', payx_payment_id, '" . $this->db->escape((string) $payment_id) . "'), payx_transaction_reference = IF('" . $this->db->escape((string) $transaction_reference) . "' = '', payx_transaction_reference, '" . $this->db->escape((string) $transaction_reference) . "'), payment_status = IF('" . $this->db->escape($event_type) . "' = '', payment_status, '" . $this->db->escape($event_type) . "'), settlement_status = IF('" . $this->db->escape((string) $settlement_status) . "' = '', settlement_status, '" . $this->db->escape((string) $settlement_status) . "'), updated_at = NOW() WHERE order_id = '" . (int) $order_id . "'");
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

    private function firstPayloadValue(array $payload, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = $this->payloadValue($payload, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function payloadValue(array $payload, string $path): mixed
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
        return array_values(array_filter(array_map(static fn($value) => strtoupper(trim($value)), explode(',', (string) $this->config->get($key)))));
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
