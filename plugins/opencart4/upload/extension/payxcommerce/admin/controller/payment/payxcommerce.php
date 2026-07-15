<?php
namespace Opencart\Admin\Controller\Extension\Payxcommerce\Payment;

class Payxcommerce extends \Opencart\System\Engine\Controller
{
    private array $error = [];

    public function index(): void
    {
        $this->load->language('extension/payxcommerce/payment/payxcommerce');
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
                $this->error['warning'] = 'PayXCommerce API credential validation failed. Check keys and API access.';
            } else {
                $this->model_setting_setting->editSetting('payment_payxcommerce', $post);
                $this->session->data['success'] = $this->language->get('text_success');
                $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment'));
                return;
            }
        }

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
            'payment_payxcommerce_brand_name' => 'PayXCommerce',
            'payment_payxcommerce_title' => 'Pay securely with PayXCommerce',
            'payment_payxcommerce_description' => 'You will be redirected to PayXCommerce hosted checkout.',
            'payment_payxcommerce_button_text' => 'Continue to secure checkout',
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
            'payment_payxcommerce_sort_order' => 0,
        ];

        $data = [];
        foreach ($defaults as $key => $default) {
            $data[$key] = $this->request->post[$key] ?? ($this->config->get($key) ?? $default);
        }
        $data['error_warning'] = $this->error['warning'] ?? '';
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        $data['icon_url'] = 'extension/payxcommerce/admin/view/image/payment/payxcommerce-logo-icon-dark-64.png';
        $data['webhook_url'] = $this->catalogUrl() . 'index.php?route=extension/payxcommerce/payment/payxcommerce.webhook';
        $data['action'] = $this->url->link('extension/payxcommerce/payment/payxcommerce', 'user_token=' . $this->session->data['user_token']);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payxcommerce/payment/payxcommerce', $data));
    }

    public function install(): void
    {
        $this->cleanupLegacyPartialInstall();
        $this->grantPermissions();

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "payxcommerce_order` (`id` INT NOT NULL AUTO_INCREMENT, `order_id` INT NOT NULL, `payx_request_number` VARCHAR(64) DEFAULT NULL, `payx_invoice_number` VARCHAR(64) DEFAULT NULL, `payx_payment_id` VARCHAR(64) DEFAULT NULL, `payx_transaction_reference` VARCHAR(64) DEFAULT NULL, `merchant_reference` VARCHAR(128) DEFAULT NULL, `checkout_url` TEXT DEFAULT NULL, `payment_status` VARCHAR(64) DEFAULT NULL, `settlement_status` VARCHAR(64) DEFAULT NULL, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `order_id` (`order_id`), KEY `payx_request_number` (`payx_request_number`), KEY `payx_invoice_number` (`payx_invoice_number`), KEY `payx_transaction_reference` (`payx_transaction_reference`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "payxcommerce_webhook_event` (`id` INT NOT NULL AUTO_INCREMENT, `event_id` VARCHAR(128) NOT NULL, `order_id` INT DEFAULT NULL, `event_type` VARCHAR(128) DEFAULT NULL, `payload_hash` VARCHAR(64) DEFAULT NULL, `processing_status` VARCHAR(32) DEFAULT NULL, `error_message` TEXT DEFAULT NULL, `created_at` DATETIME DEFAULT NULL, `processed_at` DATETIME DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `event_id` (`event_id`), KEY `order_id` (`order_id`), KEY `event_type` (`event_type`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function uninstall(): void
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('payment_payxcommerce');
        $this->cleanupUninstallRecords();
        $this->removePermissions();
        $this->removeExtensionFiles();
    }

    private function validateCredentials(array $settings): bool
    {
        try {
            require_once DIR_EXTENSION . 'payxcommerce/system/library/payxcommerce.php';
            $client = new \Opencart\System\Library\Payxcommerce($settings);
            $client->validateCredentials();
            return true;
        } catch (\Throwable $exception) {
            $this->log->write('PayXCommerce credential validation failed: ' . \Opencart\System\Library\Payxcommerce::redact($exception->getMessage()));
            return false;
        }
    }

    private function validate(): bool
    {
        if (!$this->user->hasPermission('modify', 'extension/payxcommerce/payment/payxcommerce')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }

    private function catalogUrl(): string
    {
        if (defined('HTTPS_CATALOG') && HTTPS_CATALOG) {
            return rtrim((string) HTTPS_CATALOG, '/') . '/';
        }

        if (defined('HTTP_CATALOG') && HTTP_CATALOG) {
            return rtrim((string) HTTP_CATALOG, '/') . '/';
        }

        $catalogUrl = (string) ($this->config->get('config_ssl') ?: $this->config->get('config_url') ?: '');
        if ($catalogUrl !== '') {
            return rtrim($catalogUrl, '/') . '/';
        }

        $serverUrl = defined('HTTPS_SERVER') && HTTPS_SERVER ? (string) HTTPS_SERVER : (defined('HTTP_SERVER') ? (string) HTTP_SERVER : '');
        return rtrim(str_replace('/admin/', '/', $serverUrl), '/') . '/';
    }

    private function removeExtensionFiles(): void
    {
        if (!defined('DIR_EXTENSION')) {
            return;
        }

        $extensionRoot = rtrim((string) DIR_EXTENSION, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $target = $extensionRoot . 'payxcommerce';
        $realExtensionRoot = realpath($extensionRoot);
        $realTarget = realpath($target);

        if (!$realExtensionRoot || !$realTarget || strpos($realTarget, $realExtensionRoot) !== 0 || basename($realTarget) !== 'payxcommerce') {
            return;
        }

        $this->deleteDirectory($realTarget);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath) && !is_link($itemPath)) {
                $this->deleteDirectory($itemPath);
            } elseif (is_file($itemPath) || is_link($itemPath)) {
                @unlink($itemPath);
            }
        }

        @rmdir($path);
    }

    private function cleanupLegacyPartialInstall(): void
    {
        $this->deleteIfTableExists('extension', "(`code` IN ('payxcommerce_heading_title', 'payxcommerce_text_payxcommerce') OR `extension` IN ('payxcommerce_heading_title', 'payxcommerce_text_payxcommerce'))");
        $this->deleteIfTableExists('extension_install', "(`code` IN ('payxcommerce_heading_title', 'payxcommerce_text_payxcommerce') OR `name` IN ('payxcommerce_heading_title', 'payxcommerce_text_payxcommerce'))");
    }

    private function cleanupUninstallRecords(): void
    {
        $this->deleteIfTableExists('setting', "`code` = 'payment_payxcommerce' OR `key` LIKE 'payment_payxcommerce_%'");
        $this->deleteIfTableExists('event', "`code` LIKE 'payxcommerce%' OR `action` LIKE '%payxcommerce%'");
        $this->deleteIfTableExists('modification', "`code` LIKE '%payxcommerce%' OR `name` LIKE '%PayXCommerce%'");
        $this->deleteIfTableExists('extension', "(`code` IN ('payxcommerce', 'payxcommerce.payxcommerce', 'payxcommerce_heading_title', 'payxcommerce_text_payxcommerce') OR `extension` IN ('payxcommerce', 'payxcommerce_heading_title', 'payxcommerce_text_payxcommerce'))");
        $this->deleteIfTableExists('extension_path', "`path` LIKE '%payxcommerce%'");
        $this->deleteIfTableExists('extension_install', "(`code` LIKE '%payxcommerce%' OR `name` LIKE '%PayXCommerce%' OR `name` IN ('payxcommerce_heading_title', 'payxcommerce_text_payxcommerce'))");
    }

    private function grantPermissions(): void
    {
        $this->updateAdministratorPermissions(function (array $permissions): array {
            foreach (['access', 'modify'] as $type) {
                $permissions[$type] = $permissions[$type] ?? [];
                if (!in_array('extension/payxcommerce/payment/payxcommerce', $permissions[$type], true)) {
                    $permissions[$type][] = 'extension/payxcommerce/payment/payxcommerce';
                }
            }

            return $permissions;
        });
    }

    private function removePermissions(): void
    {
        $this->updateAdministratorPermissions(function (array $permissions): array {
            foreach (['access', 'modify'] as $type) {
                $permissions[$type] = array_values(array_filter($permissions[$type] ?? [], static function ($permission) {
                    return strpos((string) $permission, 'extension/payxcommerce/') !== 0;
                }));
            }

            return $permissions;
        });
    }

    private function updateAdministratorPermissions(callable $callback): void
    {
        if (!$this->tableExists('user_group')) {
            return;
        }

        $query = $this->db->query("SELECT `user_group_id`, `permission` FROM `" . DB_PREFIX . "user_group` WHERE `name` = 'Administrator'");
        foreach ($query->rows as $row) {
            $permissions = json_decode((string) $row['permission'], true);
            $permissions = is_array($permissions) ? $permissions : [];
            $permissions = $callback($permissions);
            $this->db->query("UPDATE `" . DB_PREFIX . "user_group` SET `permission` = '" . $this->db->escape(json_encode($permissions)) . "' WHERE `user_group_id` = '" . (int) $row['user_group_id'] . "'");
        }
    }

    private function deleteIfTableExists(string $table, string $where): void
    {
        if ($this->tableExists($table)) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . $table . "` WHERE " . $where);
        }
    }

    private function tableExists(string $table): bool
    {
        $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'");
        return (bool) $query->num_rows;
    }
}
