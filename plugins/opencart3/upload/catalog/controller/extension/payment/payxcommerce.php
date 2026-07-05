<?php

class ControllerExtensionPaymentPayXCommerce extends Controller
{
    public function index()
    {
        $this->load->language('extension/payment/payxcommerce');

        return $this->load->view('extension/payment/payxcommerce', [
            'button_confirm' => $this->language->get('button_confirm'),
            'action' => $this->url->link('extension/payment/payxcommerce/confirm', '', true),
        ]);
    }

    public function confirm()
    {
        $this->load->language('extension/payment/payxcommerce');
        $this->load->model('checkout/order');

        $order_id = (int) $this->session->data['order_id'];
        $order = $this->model_checkout_order->getOrder($order_id);

        if (!$order) {
            $this->response->redirect($this->url->link('checkout/failure', '', true));
            return;
        }

        $payload = [
            'amount' => (float) $order['total'],
            'currency' => $order['currency_code'],
            'purpose' => 'OpenCart Order #' . $order_id,
            'customer' => [
                'name' => trim($order['firstname'] . ' ' . $order['lastname']),
                'email' => $order['email'],
                'mobile' => $order['telephone'],
                'address' => trim($order['payment_address_1'] . ' ' . $order['payment_address_2']),
                'city' => $order['payment_city'],
                'country' => $order['payment_country'],
            ],
            'merchant_reference' => 'OC3-' . $order_id,
            'merchant_order_id' => (string) $order_id,
            'success_url' => $this->url->link('checkout/success', '', true),
            'failed_url' => $this->url->link('checkout/failure', '', true),
            'cancel_url' => $this->url->link('checkout/checkout', '', true),
            'webhook_url' => $this->url->link('extension/payment/payxcommerce/webhook', '', true),
            'ipn_events' => ['payment.success', 'payment.failed', 'payment.cancelled', 'payment.expired', 'refund.success', 'chargeback.created'],
            'metadata' => ['platform' => 'opencart', 'platform_version' => '3', 'order_id' => (string) $order_id],
            'is_test' => $this->config->get('payment_payxcommerce_environment') !== 'live',
        ];

        try {
            $response = $this->payxcommerceRequest('POST', '/payment-requests', $payload, 'opencart-3-order-' . $order_id . '-' . time());
            $checkout_url = $response['checkout_url'] ?? '';
        } catch (Throwable $exception) {
            $this->log->write('PayXCommerce create request failed: ' . $exception->getMessage());
            $checkout_url = '';
        }

        if (!$checkout_url) {
            $this->response->redirect($this->url->link('checkout/failure', '', true));
            return;
        }

        $this->model_checkout_order->addOrderHistory($order_id, (int) $this->config->get('payment_payxcommerce_pending_status_id'), 'PayXCommerce checkout created.', true);
        $this->response->redirect($checkout_url);
    }

    public function webhook()
    {
        $raw_body = file_get_contents('php://input') ?: '';
        $event_id = $this->request->server['HTTP_X_PXC_EVENT_ID'] ?? '';
        $timestamp = $this->request->server['HTTP_X_PXC_TIMESTAMP'] ?? '';
        $signature = $this->request->server['HTTP_X_PXC_SIGNATURE'] ?? '';

        if (!$this->verifyWebhook($event_id, $timestamp, $signature, $raw_body)) {
            $this->response->addHeader('HTTP/1.1 401 Unauthorized');
            $this->response->setOutput('Invalid signature');
            return;
        }

        $payload = json_decode($raw_body, true);
        $order_id = (int) ($payload['metadata']['order_id'] ?? $payload['merchant_order_id'] ?? 0);
        if ($order_id <= 0) {
            $this->response->setOutput('Accepted');
            return;
        }

        $this->load->model('checkout/order');
        $event_type = (string) ($payload['event_type'] ?? '');
        $status_id = $this->statusForEvent($event_type);
        if ($status_id) {
            $this->model_checkout_order->addOrderHistory($order_id, $status_id, 'PayXCommerce event: ' . $event_type, true);
        }

        $this->response->setOutput('OK');
    }

    private function payxcommerceRequest(string $method, string $path, array $payload, string $idempotency_key): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $secret = (string) $this->config->get('payment_payxcommerce_secret_key');
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-PXC-Public-Key: ' . $this->config->get('payment_payxcommerce_public_key'),
            'X-PXC-Timestamp: ' . $timestamp,
            'X-PXC-Nonce: ' . $nonce,
            'X-PXC-Signature: ' . hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $secret),
            'Idempotency-Key: ' . $idempotency_key,
        ];

        $curl = curl_init(rtrim((string) $this->config->get('payment_payxcommerce_base_url'), '/') . '/' . ltrim($path, '/'));
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response_body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $decoded = json_decode((string) $response_body, true);
        if ($status >= 400) {
            throw new RuntimeException($decoded['message'] ?? 'PayXCommerce API error');
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function verifyWebhook(string $event_id, string $timestamp, string $signature, string $raw_body): bool
    {
        if ($event_id === '' || $timestamp === '' || $signature === '' || !ctype_digit($timestamp)) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }
        $payload = json_decode($raw_body, true);
        if (!is_array($payload)) {
            return false;
        }
        $expected = hash_hmac('sha256', $event_id . '.' . json_encode($payload, JSON_UNESCAPED_SLASHES), (string) $this->config->get('payment_payxcommerce_webhook_secret'));

        return hash_equals($expected, $signature);
    }

    private function statusForEvent(string $event_type): int
    {
        return match ($event_type) {
            'payment.success' => (int) $this->config->get('payment_payxcommerce_success_status_id'),
            'payment.failed' => (int) $this->config->get('payment_payxcommerce_failed_status_id'),
            'payment.cancelled', 'payment.expired' => (int) $this->config->get('payment_payxcommerce_cancelled_status_id'),
            'refund.success' => (int) $this->config->get('payment_payxcommerce_refunded_status_id'),
            default => 0,
        };
    }
}

