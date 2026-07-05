<?php
/**
 * Plugin Name: PayXCommerce Gateway for WooCommerce
 * Description: Accept payments through PayXCommerce hosted checkout.
 * Version: 0.2.0
 * Author: PayXCommerce
 * License: MIT
 * Requires PHP: 8.1
 * WC requires at least: 7.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', function (): void {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    final class WC_Gateway_PayXCommerce extends WC_Payment_Gateway
    {
        private const DEFAULT_BASE_URL = 'https://payxcommerce.com/api/v1';
        private const TOKEN_SCOPE = 'payment_requests.write transactions.read balances.read refunds.write';

        public function __construct()
        {
            $this->id = 'payxcommerce';
            $this->method_title = 'PayXCommerce';
            $this->method_description = 'Redirect customers to PayXCommerce hosted checkout and receive signed webhook updates.';
            $this->has_fields = false;
            $this->supports = ['products', 'refunds'];

            $this->init_form_fields();
            $this->init_settings();

            $this->title = (string) $this->get_option('title', 'Pay securely with PayXCommerce');
            $this->description = (string) $this->get_option('description', 'You will be redirected to PayXCommerce hosted checkout.');
            $this->enabled = (string) $this->get_option('enabled', 'no');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_api_payxcommerce', [$this, 'handle_webhook']);
        }

        public function init_form_fields(): void
        {
            $this->form_fields = [
                'enabled' => [
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enable PayXCommerce hosted checkout',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => 'Checkout Title',
                    'type' => 'text',
                    'default' => 'Pay securely with PayXCommerce',
                ],
                'description' => [
                    'title' => 'Checkout Description',
                    'type' => 'textarea',
                    'default' => 'You will be redirected to PayXCommerce hosted checkout.',
                ],
                'environment' => [
                    'title' => 'Environment',
                    'type' => 'select',
                    'default' => 'test',
                    'options' => ['test' => 'Test', 'live' => 'Live'],
                ],
                'auth_method' => [
                    'title' => 'Authentication Method',
                    'type' => 'select',
                    'default' => 'hmac',
                    'options' => ['hmac' => 'HMAC API Key', 'bearer' => 'Developer App Bearer Token'],
                    'description' => 'HMAC is recommended for merchant-owned credentials. Bearer token uses Developer App client credentials.',
                ],
                'base_url' => [
                    'title' => 'API Base URL',
                    'type' => 'text',
                    'default' => self::DEFAULT_BASE_URL,
                ],
                'public_key' => [
                    'title' => 'Public Key',
                    'type' => 'text',
                    'default' => '',
                ],
                'secret_key' => [
                    'title' => 'Secret Key',
                    'type' => 'password',
                    'default' => '',
                    'description' => 'Leave blank to keep the existing saved secret.',
                ],
                'client_id' => [
                    'title' => 'Developer App Client ID',
                    'type' => 'text',
                    'default' => '',
                ],
                'client_secret' => [
                    'title' => 'Developer App Client Secret',
                    'type' => 'password',
                    'default' => '',
                    'description' => 'Leave blank to keep the existing saved client secret.',
                ],
                'webhook_secret' => [
                    'title' => 'Webhook Secret',
                    'type' => 'password',
                    'default' => '',
                    'description' => 'Webhook URL: ' . esc_url(WC()->api_request_url('payxcommerce')) . '. Leave blank to keep the existing saved secret.',
                ],
                'allowed_currencies' => [
                    'title' => 'Allowed Currencies',
                    'type' => 'text',
                    'default' => 'USD,EUR,GBP,AUD,NZD,CAD,JPY',
                    'description' => 'Comma-separated ISO currency codes. Leave blank to allow all store currencies.',
                ],
                'allowed_countries' => [
                    'title' => 'Allowed Billing Countries',
                    'type' => 'text',
                    'default' => '',
                    'description' => 'Comma-separated ISO country codes. Leave blank to allow all billing countries.',
                ],
                'min_amount' => [
                    'title' => 'Minimum Order Amount',
                    'type' => 'number',
                    'default' => '0',
                    'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                ],
                'max_amount' => [
                    'title' => 'Maximum Order Amount',
                    'type' => 'number',
                    'default' => '0',
                    'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                    'description' => 'Use 0 for no maximum.',
                ],
                'debug' => [
                    'title' => 'Debug Logging',
                    'type' => 'checkbox',
                    'label' => 'Enable redacted debug logs',
                    'default' => 'no',
                ],
            ];
        }

        public function needs_setup(): bool
        {
            if ($this->get_option('auth_method') === 'bearer') {
                return !$this->get_option('client_id') || !$this->get_option('client_secret') || !$this->get_option('webhook_secret');
            }

            return !$this->get_option('public_key') || !$this->get_option('secret_key') || !$this->get_option('webhook_secret');
        }

        public function is_available(): bool
        {
            if (!parent::is_available() || $this->needs_setup()) {
                return false;
            }

            $currency = get_woocommerce_currency();
            $allowedCurrencies = $this->csv_option('allowed_currencies');
            if ($allowedCurrencies && !in_array(strtoupper($currency), $allowedCurrencies, true)) {
                return false;
            }

            $total = $this->current_checkout_total();
            $min = (float) $this->get_option('min_amount', '0');
            $max = (float) $this->get_option('max_amount', '0');
            if ($min > 0 && $total > 0 && $total < $min) {
                return false;
            }
            if ($max > 0 && $total > $max) {
                return false;
            }

            $country = WC()->customer ? strtoupper((string) WC()->customer->get_billing_country()) : '';
            $allowedCountries = $this->csv_option('allowed_countries');
            if ($country !== '' && $allowedCountries && !in_array($country, $allowedCountries, true)) {
                return false;
            }

            return true;
        }

        public function payment_fields(): void
        {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }
            echo '<p class="payxcommerce-checkout-note">' . esc_html__('You will complete payment securely on PayXCommerce hosted checkout.', 'payxcommerce') . '</p>';
        }

        public function process_admin_options(): bool
        {
            $saved = parent::process_admin_options();

            if (!$saved) {
                return false;
            }

            delete_transient('payxcommerce_bearer_token_' . md5((string) $this->get_option('client_id')));

            if ($this->get_option('enabled') === 'yes') {
                try {
                    $this->validate_credentials();
                    WC_Admin_Settings::add_message('PayXCommerce credentials validated successfully.');
                } catch (Throwable $exception) {
                    WC_Admin_Settings::add_error('PayXCommerce credential validation failed: ' . esc_html($exception->getMessage()));
                }
            }

            return true;
        }

        public function validate_secret_key_field($key, $value): string
        {
            return $this->preserve_password_field('secret_key', $value);
        }

        public function validate_client_secret_field($key, $value): string
        {
            return $this->preserve_password_field('client_secret', $value);
        }

        public function validate_webhook_secret_field($key, $value): string
        {
            return $this->preserve_password_field('webhook_secret', $value);
        }

        public function process_payment($order_id): array
        {
            $order = wc_get_order($order_id);
            if (!$order) {
                wc_add_notice('Unable to load order for PayXCommerce checkout.', 'error');
                return ['result' => 'failure'];
            }

            if (!$this->order_supported($order)) {
                wc_add_notice('PayXCommerce is not available for this order currency, billing country, or amount.', 'error');
                return ['result' => 'failure'];
            }

            $existingCheckout = (string) $order->get_meta('_payxcommerce_checkout_url');
            if ($existingCheckout && !$order->is_paid()) {
                return ['result' => 'success', 'redirect' => $existingCheckout];
            }

            try {
                $response = $this->api_request('POST', '/payment-requests', $this->payment_request_payload($order), [
                    'Idempotency-Key' => 'woocommerce-order-' . $order->get_id() . '-attempt-' . time(),
                ]);
            } catch (Throwable $exception) {
                $this->log('Create payment request failed: ' . $exception->getMessage());
                wc_add_notice('Unable to start PayXCommerce checkout. Please try again.', 'error');
                return ['result' => 'failure'];
            }

            $checkoutUrl = esc_url_raw((string) ($response['checkout_url'] ?? ''));
            if ($checkoutUrl === '') {
                wc_add_notice('PayXCommerce did not return a checkout URL.', 'error');
                return ['result' => 'failure'];
            }

            $order->update_meta_data('_payxcommerce_request_number', sanitize_text_field((string) ($response['request_number'] ?? '')));
            $order->update_meta_data('_payxcommerce_invoice_number', sanitize_text_field((string) ($response['invoice_number'] ?? '')));
            $order->update_meta_data('_payxcommerce_checkout_url', $checkoutUrl);
            $order->update_meta_data('_payxcommerce_environment', (string) $this->get_option('environment', 'test'));
            $order->save();
            $order->add_order_note('PayXCommerce checkout created: ' . sanitize_text_field((string) ($response['request_number'] ?? '')));

            return ['result' => 'success', 'redirect' => $checkoutUrl];
        }

        public function process_refund($order_id, $amount = null, $reason = '')
        {
            $order = wc_get_order($order_id);
            if (!$order) {
                return new WP_Error('payxcommerce_order_missing', 'Unable to load WooCommerce order.');
            }

            $transactionReference = (string) $order->get_meta('_payxcommerce_transaction_reference');
            if ($transactionReference === '') {
                return new WP_Error('payxcommerce_transaction_missing', 'Missing PayXCommerce transaction reference.');
            }

            try {
                $response = $this->api_request('POST', '/refunds', [
                    'transaction_reference' => $transactionReference,
                    'amount' => $amount !== null ? (float) $amount : null,
                    'reason' => $reason ?: 'WooCommerce refund request',
                ], [
                    'Idempotency-Key' => 'woocommerce-refund-' . $order->get_id() . '-' . time(),
                ]);
            } catch (Throwable $exception) {
                $this->log('Refund request failed: ' . $exception->getMessage());
                return new WP_Error('payxcommerce_refund_failed', $exception->getMessage());
            }

            $order->add_order_note('PayXCommerce refund requested: ' . sanitize_text_field((string) ($response['refund_reference'] ?? 'pending')));
            return true;
        }

        public function handle_webhook(): void
        {
            $rawBody = file_get_contents('php://input') ?: '';
            $eventId = sanitize_text_field($_SERVER['HTTP_X_PXC_EVENT_ID'] ?? '');
            $signature = sanitize_text_field($_SERVER['HTTP_X_PXC_SIGNATURE'] ?? '');
            $timestamp = sanitize_text_field($_SERVER['HTTP_X_PXC_TIMESTAMP'] ?? '');

            if (!$this->verify_webhook($eventId, $timestamp, $signature, $rawBody)) {
                status_header(401);
                echo 'Invalid PayXCommerce webhook signature';
                exit;
            }

            $payload = json_decode($rawBody, true);
            if (!is_array($payload)) {
                status_header(400);
                echo 'Invalid JSON';
                exit;
            }

            $order = $this->find_order_from_payload($payload);
            if (!$order) {
                $this->log('Webhook accepted but order not found. Event: ' . $eventId);
                status_header(202);
                echo 'Accepted; order not found';
                exit;
            }

            if ($eventId && $order->get_meta('_payxcommerce_event_' . $eventId)) {
                status_header(200);
                echo 'Duplicate event ignored';
                exit;
            }

            $eventType = sanitize_text_field((string) ($payload['event_type'] ?? ''));
            $this->apply_event($order, $eventType, $payload);

            if ($eventId) {
                $order->update_meta_data('_payxcommerce_event_' . $eventId, current_time('mysql'));
            }
            $order->save();

            status_header(200);
            echo 'OK';
            exit;
        }

        private function payment_request_payload(WC_Order $order): array
        {
            return [
                'amount' => (float) $order->get_total(),
                'currency' => $order->get_currency(),
                'purpose' => 'WooCommerce Order #' . $order->get_order_number(),
                'customer' => [
                    'name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: 'Customer',
                    'email' => $order->get_billing_email(),
                    'mobile' => $order->get_billing_phone(),
                    'address' => trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2()),
                    'city' => $order->get_billing_city(),
                    'country' => $order->get_billing_country() ?: 'US',
                ],
                'merchant_reference' => 'WC-' . $order->get_id(),
                'merchant_order_id' => (string) $order->get_id(),
                'success_url' => $this->get_return_url($order),
                'failed_url' => $order->get_cancel_order_url_raw(),
                'cancel_url' => $order->get_cancel_order_url_raw(),
                'webhook_url' => WC()->api_request_url('payxcommerce'),
                'ipn_events' => ['payment.success', 'payment.failed', 'payment.cancelled', 'payment.expired', 'refund.success', 'chargeback.created'],
                'metadata' => [
                    'platform' => 'woocommerce',
                    'platform_version' => defined('WC_VERSION') ? WC_VERSION : '',
                    'site_url' => home_url('/'),
                    'order_id' => (string) $order->get_id(),
                ],
                'is_test' => $this->get_option('environment') !== 'live',
            ];
        }

        private function api_request(string $method, string $path, ?array $payload = null, array $headers = []): array
        {
            $body = $payload === null ? '' : wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                throw new RuntimeException('Unable to encode PayXCommerce payload.');
            }

            $baseUrl = rtrim((string) $this->get_option('base_url', self::DEFAULT_BASE_URL), '/');
            $headers = array_merge(['Accept' => 'application/json'], $headers);
            if ($payload !== null) {
                $headers['Content-Type'] = 'application/json';
            }

            if ($this->get_option('auth_method') === 'bearer') {
                $headers['Authorization'] = 'Bearer ' . $this->bearer_token();
            } else {
                $timestamp = (string) time();
                $nonce = bin2hex(random_bytes(16));
                $secret = (string) $this->get_option('secret_key');
                $headers['X-PXC-Public-Key'] = (string) $this->get_option('public_key');
                $headers['X-PXC-Timestamp'] = $timestamp;
                $headers['X-PXC-Nonce'] = $nonce;
                $headers['X-PXC-Signature'] = hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $secret);
            }

            $args = [
                'method' => strtoupper($method),
                'timeout' => 30,
                'headers' => $headers,
            ];
            if ($body !== '') {
                $args['body'] = $body;
            }

            $response = wp_remote_request($baseUrl . '/' . ltrim($path, '/'), $args);
            if (is_wp_error($response)) {
                throw new RuntimeException($response->get_error_message());
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
            if ($status >= 400) {
                throw new RuntimeException((string) ($decoded['message'] ?? $decoded['error'] ?? 'PayXCommerce API request failed.'));
            }

            return is_array($decoded) ? $decoded : [];
        }

        private function bearer_token(): string
        {
            $clientId = (string) $this->get_option('client_id');
            $cached = get_transient('payxcommerce_bearer_token_' . md5($clientId));
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            $baseUrl = rtrim((string) $this->get_option('base_url', self::DEFAULT_BASE_URL), '/');
            $response = wp_remote_post($baseUrl . '/oauth/token', [
                'timeout' => 30,
                'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'body' => wp_json_encode([
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => (string) $this->get_option('client_secret'),
                    'scope' => self::TOKEN_SCOPE,
                ]),
            ]);

            if (is_wp_error($response)) {
                throw new RuntimeException($response->get_error_message());
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
            if ($status >= 400) {
                throw new RuntimeException((string) ($decoded['message'] ?? $decoded['error'] ?? 'PayXCommerce OAuth token request failed.'));
            }

            $token = (string) ($decoded['access_token'] ?? '');
            if ($token === '') {
                throw new RuntimeException('PayXCommerce OAuth token response did not include an access token.');
            }

            set_transient('payxcommerce_bearer_token_' . md5($clientId), $token, max(60, (int) ($decoded['expires_in'] ?? 3600) - 60));
            return $token;
        }

        private function validate_credentials(): void
        {
            if ($this->needs_setup()) {
                throw new RuntimeException('Required PayXCommerce credentials or webhook secret are missing.');
            }

            $this->api_request('GET', '/balance', null);
        }

        private function verify_webhook(string $eventId, string $timestamp, string $signature, string $rawBody): bool
        {
            if ($eventId === '' || $timestamp === '' || $signature === '' || !ctype_digit($timestamp)) {
                return false;
            }
            if (abs(time() - (int) $timestamp) > 300) {
                return false;
            }

            $payload = json_decode($rawBody, true);
            if (!is_array($payload)) {
                return false;
            }

            $canonicalBody = wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
            $expected = hash_hmac('sha256', $eventId . '.' . $canonicalBody, (string) $this->get_option('webhook_secret'));

            return hash_equals($expected, $signature);
        }

        private function find_order_from_payload(array $payload): ?WC_Order
        {
            $orderId = $payload['metadata']['order_id'] ?? $payload['merchant_order_id'] ?? null;
            if ($orderId) {
                $order = wc_get_order((int) $orderId);
                if ($order) {
                    return $order;
                }
            }

            foreach (['request_number' => '_payxcommerce_request_number', 'invoice_number' => '_payxcommerce_invoice_number', 'transaction_reference' => '_payxcommerce_transaction_reference'] as $payloadKey => $metaKey) {
                $value = (string) ($payload[$payloadKey] ?? '');
                if ($value === '') {
                    continue;
                }
                $orders = wc_get_orders(['limit' => 1, 'meta_key' => $metaKey, 'meta_value' => $value]);
                if (!empty($orders)) {
                    return $orders[0];
                }
            }

            return null;
        }

        private function apply_event(WC_Order $order, string $eventType, array $payload): void
        {
            foreach (['_payxcommerce_transaction_reference' => 'transaction_reference', '_payxcommerce_payment_id' => 'payment_id', '_payxcommerce_settlement_status' => 'settlement_status'] as $metaKey => $payloadKey) {
                $value = (string) ($payload[$payloadKey] ?? '');
                if ($value !== '') {
                    $order->update_meta_data($metaKey, sanitize_text_field($value));
                }
            }

            match ($eventType) {
                'payment.success' => $order->payment_complete((string) ($payload['transaction_reference'] ?? '')),
                'payment.failed' => $order->update_status('failed', 'PayXCommerce payment failed.'),
                'payment.cancelled', 'payment.expired' => $order->update_status('cancelled', 'PayXCommerce payment cancelled or expired.'),
                'refund.success', 'payment.refunded' => $order->add_order_note('PayXCommerce refund completed.'),
                'chargeback.created', 'dispute.created' => $order->update_status('on-hold', 'PayXCommerce dispute or chargeback created.'),
                default => $order->add_order_note('PayXCommerce event received: ' . sanitize_text_field($eventType)),
            };
        }

        private function order_supported(WC_Order $order): bool
        {
            $currency = strtoupper($order->get_currency());
            $allowedCurrencies = $this->csv_option('allowed_currencies');
            if ($allowedCurrencies && !in_array($currency, $allowedCurrencies, true)) {
                return false;
            }

            $country = strtoupper((string) $order->get_billing_country());
            $allowedCountries = $this->csv_option('allowed_countries');
            if ($country !== '' && $allowedCountries && !in_array($country, $allowedCountries, true)) {
                return false;
            }

            $total = (float) $order->get_total();
            $min = (float) $this->get_option('min_amount', '0');
            $max = (float) $this->get_option('max_amount', '0');

            return !($min > 0 && $total < $min) && !($max > 0 && $total > $max);
        }

        private function current_checkout_total(): float
        {
            if (WC()->cart) {
                return (float) WC()->cart->get_total('edit');
            }
            return 0.0;
        }

        private function csv_option(string $key): array
        {
            $raw = (string) $this->get_option($key, '');
            return array_values(array_filter(array_map(static fn ($value) => strtoupper(trim($value)), explode(',', $raw))));
        }

        private function preserve_password_field(string $optionKey, mixed $value): string
        {
            $value = is_string($value) ? trim($value) : '';
            return $value !== '' ? $value : (string) $this->get_option($optionKey, '');
        }

        private function log(string $message): void
        {
            if ($this->get_option('debug') !== 'yes') {
                return;
            }

            wc_get_logger()->info($this->redact($message), ['source' => 'payxcommerce']);
        }

        private function redact(string $message): string
        {
            return preg_replace('/(secret|token|signature|authorization|client_secret|secret_key)([^\s]*)/i', '$1[redacted]', $message) ?: $message;
        }
    }

    add_filter('woocommerce_payment_gateways', function (array $gateways): array {
        $gateways[] = WC_Gateway_PayXCommerce::class;
        return $gateways;
    });
});
