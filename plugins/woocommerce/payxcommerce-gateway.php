<?php
/**
 * Plugin Name: PayXCommerce Gateway for WooCommerce
 * Description: Accept payments through PayXCommerce hosted checkout.
 * Version: 0.1.0
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
        public function __construct()
        {
            $this->id = 'payxcommerce';
            $this->method_title = 'PayXCommerce';
            $this->method_description = 'Redirect customers to PayXCommerce hosted checkout.';
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
                    'title' => 'Title',
                    'type' => 'text',
                    'default' => 'Pay securely with PayXCommerce',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'description' => [
                    'title' => 'Description',
                    'type' => 'textarea',
                    'default' => 'You will be redirected to PayXCommerce hosted checkout.',
                    'sanitize_callback' => 'sanitize_textarea_field',
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
                ],
                'base_url' => [
                    'title' => 'API Base URL',
                    'type' => 'text',
                    'default' => 'https://payxcommerce.com/api/v1',
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
                ],
                'webhook_secret' => [
                    'title' => 'Webhook Secret',
                    'type' => 'password',
                    'default' => '',
                    'description' => 'Webhook URL: ' . esc_url(WC()->api_request_url('payxcommerce')),
                ],
                'debug' => [
                    'title' => 'Debug Logging',
                    'type' => 'checkbox',
                    'label' => 'Enable redacted debug logs',
                    'default' => 'no',
                ],
            ];
        }

        public function process_payment($order_id): array
        {
            $order = wc_get_order($order_id);
            if (!$order) {
                wc_add_notice('Unable to load order for PayXCommerce checkout.', 'error');
                return ['result' => 'failure'];
            }

            $existingCheckout = (string) $order->get_meta('_payxcommerce_checkout_url');
            if ($existingCheckout && !$order->is_paid()) {
                return ['result' => 'success', 'redirect' => $existingCheckout];
            }

            $payload = $this->payment_request_payload($order);

            try {
                $response = $this->api_request('POST', '/payment-requests', $payload, [
                    'Idempotency-Key' => 'woocommerce-order-' . $order->get_id() . '-attempt-' . time(),
                ]);
            } catch (Throwable $exception) {
                $this->log('Create payment request failed: ' . $exception->getMessage());
                wc_add_notice('Unable to start PayXCommerce checkout. Please try again.', 'error');
                return ['result' => 'failure'];
            }

            $checkoutUrl = (string) ($response['checkout_url'] ?? '');
            if ($checkoutUrl === '') {
                wc_add_notice('PayXCommerce did not return a checkout URL.', 'error');
                return ['result' => 'failure'];
            }

            $order->update_meta_data('_payxcommerce_request_number', (string) ($response['request_number'] ?? ''));
            $order->update_meta_data('_payxcommerce_invoice_number', (string) ($response['invoice_number'] ?? ''));
            $order->update_meta_data('_payxcommerce_checkout_url', $checkoutUrl);
            $order->save();
            $order->add_order_note('PayXCommerce checkout created: ' . sanitize_text_field((string) ($response['request_number'] ?? '')));

            return ['result' => 'success', 'redirect' => $checkoutUrl];
        }

        public function process_refund($order_id, $amount = null, $reason = ''): bool
        {
            $order = wc_get_order($order_id);
            if (!$order) {
                return false;
            }

            $transactionReference = (string) $order->get_meta('_payxcommerce_transaction_reference');
            if ($transactionReference === '') {
                $order->add_order_note('PayXCommerce refund skipped: missing transaction reference.');
                return false;
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
                $order->add_order_note('PayXCommerce refund request failed: ' . $exception->getMessage());
                return false;
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
                status_header(202);
                echo 'PayXCommerce webhook accepted; order not found';
                exit;
            }

            if ($eventId && $order->get_meta('_payxcommerce_event_' . $eventId)) {
                status_header(200);
                echo 'Duplicate event ignored';
                exit;
            }

            $eventType = (string) ($payload['event_type'] ?? '');
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
                    'country' => $order->get_billing_country() ?: 'United States',
                ],
                'merchant_reference' => 'WC-' . $order->get_id(),
                'merchant_order_id' => (string) $order->get_id(),
                'success_url' => $this->get_return_url($order),
                'failed_url' => $order->get_cancel_order_url_raw(),
                'cancel_url' => $order->get_cancel_order_url_raw(),
                'webhook_url' => WC()->api_request_url('payxcommerce'),
                'ipn_events' => ['payment.success', 'payment.failed', 'payment.cancelled', 'payment.expired', 'refund.success', 'chargeback.created'],
                'metadata' => ['platform' => 'woocommerce', 'order_id' => (string) $order->get_id()],
                'is_test' => $this->get_option('environment') !== 'live',
            ];
        }

        private function api_request(string $method, string $path, array $payload = [], array $headers = []): array
        {
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                throw new RuntimeException('Unable to encode PayXCommerce payload.');
            }

            $baseUrl = rtrim((string) $this->get_option('base_url', 'https://payxcommerce.com/api/v1'), '/');
            $headers = array_merge(['Content-Type' => 'application/json', 'Accept' => 'application/json'], $headers);

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
                'method' => $method,
                'timeout' => 30,
                'headers' => $headers,
                'body' => $body,
            ];

            $response = wp_remote_request($baseUrl . '/' . ltrim($path, '/'), $args);
            if (is_wp_error($response)) {
                throw new RuntimeException($response->get_error_message());
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
            if ($status >= 400) {
                throw new RuntimeException((string) ($decoded['message'] ?? 'PayXCommerce API request failed.'));
            }

            return is_array($decoded) ? $decoded : [];
        }

        private function bearer_token(): string
        {
            $cached = get_transient('payxcommerce_bearer_token');
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            $baseUrl = rtrim((string) $this->get_option('base_url', 'https://payxcommerce.com/api/v1'), '/');
            $response = wp_remote_post($baseUrl . '/oauth/token', [
                'timeout' => 30,
                'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'body' => wp_json_encode([
                    'grant_type' => 'client_credentials',
                    'client_id' => (string) $this->get_option('client_id'),
                    'client_secret' => (string) $this->get_option('client_secret'),
                    'scope' => 'payment_requests.write transactions.read balances.read refunds.write',
                ]),
            ]);

            if (is_wp_error($response)) {
                throw new RuntimeException($response->get_error_message());
            }

            $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
            $token = (string) ($decoded['access_token'] ?? '');
            if ($token === '') {
                throw new RuntimeException('PayXCommerce OAuth token response did not include an access token.');
            }

            set_transient('payxcommerce_bearer_token', $token, max(60, (int) ($decoded['expires_in'] ?? 3600) - 60));
            return $token;
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

            $requestNumber = (string) ($payload['request_number'] ?? '');
            if ($requestNumber === '') {
                return null;
            }

            $orders = wc_get_orders([
                'limit' => 1,
                'meta_key' => '_payxcommerce_request_number',
                'meta_value' => $requestNumber,
            ]);

            return $orders[0] ?? null;
        }

        private function apply_event(WC_Order $order, string $eventType, array $payload): void
        {
            $transactionReference = (string) ($payload['transaction_reference'] ?? '');
            if ($transactionReference !== '') {
                $order->update_meta_data('_payxcommerce_transaction_reference', $transactionReference);
            }

            match ($eventType) {
                'payment.success' => $order->payment_complete($transactionReference ?: ''),
                'payment.failed' => $order->update_status('failed', 'PayXCommerce payment failed.'),
                'payment.cancelled', 'payment.expired' => $order->update_status('cancelled', 'PayXCommerce payment cancelled or expired.'),
                'refund.success' => $order->add_order_note('PayXCommerce refund completed.'),
                'chargeback.created' => $order->update_status('on-hold', 'PayXCommerce chargeback created.'),
                default => $order->add_order_note('PayXCommerce event received: ' . sanitize_text_field($eventType)),
            };
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
            return preg_replace('/(secret|token|signature|authorization)([^\\s]*)/i', '$1[redacted]', $message) ?: $message;
        }
    }

    add_filter('woocommerce_payment_gateways', function (array $gateways): array {
        $gateways[] = WC_Gateway_PayXCommerce::class;
        return $gateways;
    });
});

