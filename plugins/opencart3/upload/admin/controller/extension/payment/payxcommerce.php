<?php

class ControllerExtensionPaymentPayXCommerce extends Controller
{
    private $error = [];

    public function index()
    {
        $this->load->language('extension/payment/payxcommerce');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('localisation/order_status');
        $this->load->model('localisation/geo_zone');

        if (($this->request->server['REQUEST_METHOD'] ?? '') === 'POST' && $this->validate()) {
            $post = $this->request->post;
            foreach (['secret_key', 'client_secret', 'webhook_secret'] as $secret_field) {
                $key = 'payment_payxcommerce_' . $secret_field;
                if (($post[$key] ?? '') === '') {
                    $post[$key] = (string) $this->config->get($key);
                }
            }

            if (!empty($post['payment_payxcommerce_status']) && !$this->validateCredentials($post)) {
                $this->error['warning'] = $this->language->get('error_credentials');
            } else {
                $this->model_setting_setting->editSetting('payment_payxcommerce', $post);
                $this->session->data['success'] = $this->language->get('text_success');
                $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
                return;
            }
        }

        $data = [];
        $data['error_warning'] = $this->error['warning'] ?? '';
        $data['action'] = $this->url->link('extension/payment/payxcommerce', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $defaults = [
            'payment_payxcommerce_status' => 0,
            'payment_payxcommerce_environment' => 'test',
            'payment_payxcommerce_auth_method' => 'hmac',
            'payment_payxcommerce_base_url' => 'https://payxcommerce.com/api/v1',
            'payment_payxcommerce_public_key' => '',
            'payment_payxcommerce_secret_key' => '',
            'payment_payxcommerce_client_id' => '',
            'payment_payxcommerce_client_secret' => '',
            'payment_payxcommerce_webhook_secret' => '',
            'payment_payxcommerce_title' => 'Pay securely with PayXCommerce',
            'payment_payxcommerce_description' => 'You will be redirected to PayXCommerce hosted checkout.',
            'payment_payxcommerce_allowed_currencies' => 'USD,EUR,GBP,AUD,NZD,CAD,JPY',
            'payment_payxcommerce_allowed_countries' => '',
            'payment_payxcommerce_min_total' => '0',
            'payment_payxcommerce_max_total' => '0',
            'payment_payxcommerce_geo_zone_id' => 0,
            'payment_payxcommerce_pending_status_id' => 1,
            'payment_payxcommerce_success_status_id' => 2,
            'payment_payxcommerce_failed_status_id' => 10,
            'payment_payxcommerce_cancelled_status_id' => 7,
            'payment_payxcommerce_expired_status_id' => 14,
            'payment_payxcommerce_refunded_status_id' => 11,
            'payment_payxcommerce_chargeback_status_id' => 13,
            'payment_payxcommerce_debug' => 0,
            'payment_payxcommerce_sort_order' => 0,
        ];

        foreach ($defaults as $key => $default) {
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } else {
                $value = $this->config->get($key);
                $data[$key] = $value !== null ? $value : $default;
            }
        }

        $data['webhook_url'] = HTTPS_CATALOG . 'index.php?route=extension/payment/payxcommerce/webhook';
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/payxcommerce', $data));
    }

    public function install()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "payxcommerce_order` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `order_id` INT(11) NOT NULL,
            `payx_request_number` VARCHAR(64) DEFAULT NULL,
            `payx_invoice_number` VARCHAR(64) DEFAULT NULL,
            `payx_payment_id` VARCHAR(64) DEFAULT NULL,
            `payx_transaction_reference` VARCHAR(64) DEFAULT NULL,
            `merchant_reference` VARCHAR(128) DEFAULT NULL,
            `checkout_url` TEXT DEFAULT NULL,
            `payment_status` VARCHAR(64) DEFAULT NULL,
            `settlement_status` VARCHAR(64) DEFAULT NULL,
            `created_at` DATETIME DEFAULT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `order_id` (`order_id`),
            KEY `payx_request_number` (`payx_request_number`),
            KEY `payx_transaction_reference` (`payx_transaction_reference`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "payxcommerce_webhook_event` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `event_id` VARCHAR(128) NOT NULL,
            `order_id` INT(11) DEFAULT NULL,
            `event_type` VARCHAR(128) DEFAULT NULL,
            `payload_hash` VARCHAR(64) DEFAULT NULL,
            `processing_status` VARCHAR(32) DEFAULT NULL,
            `error_message` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT NULL,
            `processed_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `event_id` (`event_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    public function uninstall()
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('payment_payxcommerce');
    }

    private function validate(): bool
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/payxcommerce')) {
            $this->error['warning'] = $this->language->get('error_permission');
            return false;
        }

        $auth = $this->request->post['payment_payxcommerce_auth_method'] ?? 'hmac';
        if (!empty($this->request->post['payment_payxcommerce_status'])) {
            if ($auth === 'hmac' && empty($this->request->post['payment_payxcommerce_public_key']) && !$this->config->get('payment_payxcommerce_public_key')) {
                $this->error['warning'] = $this->language->get('error_public_key');
            }
            if ($auth === 'bearer' && empty($this->request->post['payment_payxcommerce_client_id']) && !$this->config->get('payment_payxcommerce_client_id')) {
                $this->error['warning'] = $this->language->get('error_client_id');
            }
        }

        return !$this->error;
    }

    private function validateCredentials(array $settings): bool
    {
        try {
            $this->apiRequest('GET', '/balance', null, $settings);
            return true;
        } catch (Throwable $exception) {
            $this->log->write('PayXCommerce credential validation failed: ' . $exception->getMessage());
            return false;
        }
    }

    private function apiRequest(string $method, string $path, ?array $payload, array $settings): array
    {
        $base_url = rtrim((string) ($settings['payment_payxcommerce_base_url'] ?? 'https://payxcommerce.com/api/v1'), '/');
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
        $headers = ['Accept: application/json'];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        if (($settings['payment_payxcommerce_auth_method'] ?? 'hmac') === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . $this->bearerToken($settings);
        } else {
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));
            $secret = (string) ($settings['payment_payxcommerce_secret_key'] ?? '');
            $headers[] = 'X-PXC-Public-Key: ' . ($settings['payment_payxcommerce_public_key'] ?? '');
            $headers[] = 'X-PXC-Timestamp: ' . $timestamp;
            $headers[] = 'X-PXC-Nonce: ' . $nonce;
            $headers[] = 'X-PXC-Signature: ' . hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $secret);
        }

        $curl = curl_init($base_url . '/' . ltrim($path, '/'));
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30]);
        if ($body !== '') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $response_body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response_body === false) {
            throw new RuntimeException($error ?: 'PayXCommerce API request failed');
        }
        $decoded = json_decode((string) $response_body, true);
        if ($status >= 400) {
            throw new RuntimeException((string) ($decoded['message'] ?? $decoded['error'] ?? 'PayXCommerce API error'));
        }
        return is_array($decoded) ? $decoded : [];
    }

    private function bearerToken(array $settings): string
    {
        $base_url = rtrim((string) ($settings['payment_payxcommerce_base_url'] ?? 'https://payxcommerce.com/api/v1'), '/');
        $payload = json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => $settings['payment_payxcommerce_client_id'] ?? '',
            'client_secret' => $settings['payment_payxcommerce_client_secret'] ?? '',
            'scope' => 'payment_requests.write transactions.read balances.read refunds.write',
        ], JSON_UNESCAPED_SLASHES);
        $curl = curl_init($base_url . '/oauth/token');
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'], CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 30]);
        $response_body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $decoded = json_decode((string) $response_body, true);
        if ($status >= 400 || empty($decoded['access_token'])) {
            throw new RuntimeException((string) ($decoded['message'] ?? $decoded['error'] ?? 'Unable to create PayXCommerce token'));
        }
        return (string) $decoded['access_token'];
    }
}
