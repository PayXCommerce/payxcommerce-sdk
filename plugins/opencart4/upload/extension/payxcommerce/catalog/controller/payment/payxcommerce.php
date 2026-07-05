<?php
namespace Opencart\Catalog\Controller\Extension\Payxcommerce\Payment;

class Payxcommerce extends \Opencart\System\Engine\Controller
{
    public function index(): string
    {
        $this->load->language('extension/payxcommerce/payment/payxcommerce');

        return $this->load->view('extension/payxcommerce/payment/payxcommerce', [
            'button_confirm' => $this->language->get('button_confirm'),
            'description' => $this->config->get('payment_payxcommerce_description'),
            'action' => $this->url->link('extension/payxcommerce/payment/payxcommerce.confirm'),
        ]);
    }

    public function confirm(): void
    {
        $this->load->model('checkout/order');
        $this->load->model('extension/payxcommerce/payment/payxcommerce');

        $order_id = (int) ($this->session->data['order_id'] ?? 0);
        $order = $this->model_checkout_order->getOrder($order_id);
        if (!$order) {
            $this->response->redirect($this->url->link('checkout/failure'));
            return;
        }

        $merchant_reference = 'OC4-' . $order_id;
        $payload = [
            'amount' => (float) $order['total'],
            'currency' => $order['currency_code'],
            'purpose' => 'OpenCart Order #' . $order_id,
            'customer' => [
                'name' => trim($order['firstname'] . ' ' . $order['lastname']) ?: 'Customer',
                'email' => $order['email'],
                'mobile' => $order['telephone'],
                'address' => trim($order['payment_address_1'] . ' ' . $order['payment_address_2']),
                'city' => $order['payment_city'],
                'country' => $order['payment_iso_code_2'] ?: $order['payment_country'],
            ],
            'merchant_reference' => $merchant_reference,
            'merchant_order_id' => (string) $order_id,
            'success_url' => $this->url->link('checkout/success'),
            'failed_url' => $this->url->link('checkout/failure'),
            'cancel_url' => $this->url->link('checkout/checkout'),
            'webhook_url' => $this->url->link('extension/payxcommerce/payment/payxcommerce.webhook'),
            'ipn_events' => ['payment.success', 'payment.failed', 'payment.cancelled', 'payment.expired', 'refund.success', 'chargeback.created'],
            'metadata' => ['platform' => 'opencart', 'platform_version' => '4', 'order_id' => (string) $order_id, 'store_id' => (string) $order['store_id']],
            'is_test' => $this->config->get('payment_payxcommerce_environment') !== 'live',
        ];

        try {
            $response = $this->apiRequest('POST', '/payment-requests', $payload, 'opencart-4-order-' . $order_id . '-' . time());
            $checkout_url = (string) ($response['checkout_url'] ?? '');
        } catch (\Throwable $exception) {
            $this->log->write('PayXCommerce create request failed: ' . $exception->getMessage());
            $checkout_url = '';
        }

        if ($checkout_url === '') {
            $this->response->redirect($this->url->link('checkout/failure'));
            return;
        }

        $this->model_extension_payxcommerce_payment_payxcommerce->savePayxOrder($order_id, $response, $merchant_reference);
        $this->model_checkout_order->addHistory($order_id, (int) $this->config->get('payment_payxcommerce_pending_status_id'), 'PayXCommerce checkout created.', true);
        $this->response->redirect($checkout_url);
    }

    public function webhook(): void
    {
        $this->load->model('checkout/order');
        $this->load->model('extension/payxcommerce/payment/payxcommerce');

        $raw_body = file_get_contents('php://input') ?: '';
        $event_id = (string) ($this->request->server['HTTP_X_PXC_EVENT_ID'] ?? '');
        $timestamp = (string) ($this->request->server['HTTP_X_PXC_TIMESTAMP'] ?? '');
        $signature = (string) ($this->request->server['HTTP_X_PXC_SIGNATURE'] ?? '');

        if (!$this->verifyWebhook($event_id, $timestamp, $signature, $raw_body)) {
            $this->response->addHeader('HTTP/1.1 401 Unauthorized');
            $this->response->setOutput('Invalid signature');
            return;
        }

        if ($event_id !== '' && $this->model_extension_payxcommerce_payment_payxcommerce->webhookEventExists($event_id)) {
            $this->response->setOutput('Duplicate ignored');
            return;
        }

        $payload = json_decode($raw_body, true);
        if (!is_array($payload)) {
            $this->response->addHeader('HTTP/1.1 400 Bad Request');
            $this->response->setOutput('Invalid JSON');
            return;
        }

        $event_type = (string) ($payload['event_type'] ?? '');
        $order_id = $this->model_extension_payxcommerce_payment_payxcommerce->findOrderId($payload);
        $this->model_extension_payxcommerce_payment_payxcommerce->recordWebhookEvent($event_id, $order_id, $event_type, $raw_body);
        if ($order_id > 0) {
            $this->model_extension_payxcommerce_payment_payxcommerce->updatePayxOrder($order_id, $payload);
            $status_id = $this->statusForEvent($event_type);
            if ($status_id) {
                $this->model_checkout_order->addHistory($order_id, $status_id, 'PayXCommerce event: ' . $event_type, true);
            }
        }

        $this->response->setOutput('OK');
    }

    private function apiRequest(string $method, string $path, ?array $payload = null, ?string $idempotency_key = null): array
    {
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
        $headers = ['Accept: application/json'];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        if ($idempotency_key) {
            $headers[] = 'Idempotency-Key: ' . $idempotency_key;
        }
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        if ($this->config->get('payment_payxcommerce_auth_method') === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . $this->bearerToken();
        } else {
            $headers[] = 'X-PXC-Public-Key: ' . $this->config->get('payment_payxcommerce_public_key');
            $headers[] = 'X-PXC-Timestamp: ' . $timestamp;
            $headers[] = 'X-PXC-Nonce: ' . $nonce;
            $headers[] = 'X-PXC-Signature: ' . hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, (string) $this->config->get('payment_payxcommerce_secret_key'));
        }

        $curl = curl_init(rtrim((string) $this->config->get('payment_payxcommerce_base_url'), '/') . '/' . ltrim($path, '/'));
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30]);
        if ($body !== '') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $response_body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $decoded = json_decode((string) $response_body, true);
        if ($status >= 400) {
            throw new \RuntimeException((string) ($decoded['message'] ?? $decoded['error'] ?? 'PayXCommerce API error'));
        }
        return is_array($decoded) ? $decoded : [];
    }

    private function bearerToken(): string
    {
        $payload = json_encode(['grant_type' => 'client_credentials', 'client_id' => $this->config->get('payment_payxcommerce_client_id'), 'client_secret' => $this->config->get('payment_payxcommerce_client_secret'), 'scope' => 'payment_requests.write transactions.read balances.read refunds.write'], JSON_UNESCAPED_SLASHES);
        $curl = curl_init(rtrim((string) $this->config->get('payment_payxcommerce_base_url'), '/') . '/oauth/token');
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'], CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 30]);
        $response_body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $decoded = json_decode((string) $response_body, true);
        if ($status >= 400 || empty($decoded['access_token'])) { throw new \RuntimeException((string) ($decoded['message'] ?? $decoded['error'] ?? 'PayXCommerce OAuth error')); }
        return (string) $decoded['access_token'];
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
        return hash_equals(hash_hmac('sha256', $event_id . '.' . json_encode($payload, JSON_UNESCAPED_SLASHES), (string) $this->config->get('payment_payxcommerce_webhook_secret')), $signature);
    }

    private function statusForEvent(string $event_type): int
    {
        return match ($event_type) {
            'payment.success' => (int) $this->config->get('payment_payxcommerce_success_status_id'),
            'payment.failed' => (int) $this->config->get('payment_payxcommerce_failed_status_id'),
            'payment.cancelled' => (int) $this->config->get('payment_payxcommerce_cancelled_status_id'),
            'payment.expired' => (int) $this->config->get('payment_payxcommerce_expired_status_id'),
            'refund.success', 'payment.refunded' => (int) $this->config->get('payment_payxcommerce_refunded_status_id'),
            'chargeback.created', 'dispute.created' => (int) $this->config->get('payment_payxcommerce_chargeback_status_id'),
            default => 0,
        };
    }
}
