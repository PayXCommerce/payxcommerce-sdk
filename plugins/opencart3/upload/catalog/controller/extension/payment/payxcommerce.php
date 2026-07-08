<?php

class ControllerExtensionPaymentPayXCommerce extends Controller
{
    public function index()
    {
        $this->load->language('extension/payment/payxcommerce');
        $brand = $this->brandName();

        return $this->load->view('extension/payment/payxcommerce', [
            'button_confirm' => $this->publicText('payment_payxcommerce_button_text', $this->language->get('button_confirm'), $brand),
            'description' => $this->publicText('payment_payxcommerce_description', $this->language->get('text_description'), $brand),
            'brand_name' => $brand,
            'icon_url' => 'catalog/view/theme/default/image/payxcommerce/logo-icon-dark-64.png',
            'action' => $this->url->link('extension/payment/payxcommerce/confirm', '', true),
        ]);
    }

    public function confirm()
    {
        $this->load->language('extension/payment/payxcommerce');
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/payxcommerce');

        $order_id = (int) ($this->session->data['order_id'] ?? 0);
        $order = $this->model_checkout_order->getOrder($order_id);

        if (!$order) {
            $this->response->redirect($this->url->link('checkout/failure', '', true));
            return;
        }

        $merchant_reference = 'OC3-' . $order_id;
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
            'success_url' => $this->url->link('checkout/success', '', true),
            'failed_url' => $this->url->link('checkout/failure', '', true),
            'cancel_url' => $this->url->link('checkout/checkout', '', true),
            'webhook_url' => $this->url->link('extension/payment/payxcommerce/webhook', '', true),
            'ipn_events' => ['payment.succeeded', 'payment.failed', 'payment.cancelled', 'payment.expired', 'refund.succeeded', 'payment.refunded', 'chargeback.created', 'dispute.created'],
            'metadata' => ['platform' => 'opencart', 'platform_version' => '3', 'order_id' => (string) $order_id, 'store_id' => (string) $order['store_id']],
            'is_test' => $this->config->get('payment_payxcommerce_environment') !== 'live',
        ];

        try {
            require_once DIR_SYSTEM . 'library/payxcommerce.php';
            $client = new PayXCommerce($this->settings());
            if (!$client->isConfigured()) {
                throw new RuntimeException('Payment method is not fully configured.');
            }
            $response = $client->createPaymentRequest($payload, 'opencart-3-order-' . $order_id . '-' . time());
            $checkout_url = (string) ($response['checkout_url'] ?? '');
        } catch (Throwable $exception) {
            $this->debug('Create request failed: ' . $exception->getMessage());
            $checkout_url = '';
        }

        if ($checkout_url === '') {
            $this->response->redirect($this->url->link('checkout/failure', '', true));
            return;
        }

        $this->model_extension_payment_payxcommerce->savePayxOrder($order_id, $response, $merchant_reference);
        $this->model_checkout_order->addOrderHistory($order_id, (int) $this->config->get('payment_payxcommerce_pending_status_id'), $this->brandName() . ' checkout created.', true);
        $this->response->redirect($checkout_url);
    }

    public function webhook()
    {
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/payxcommerce');

        $raw_body = file_get_contents('php://input') ?: '';
        $event_id = (string) ($this->request->server['HTTP_X_PXC_EVENT_ID'] ?? '');
        $timestamp = (string) ($this->request->server['HTTP_X_PXC_TIMESTAMP'] ?? '');
        $signature = (string) ($this->request->server['HTTP_X_PXC_SIGNATURE'] ?? '');

        try {
            require_once DIR_SYSTEM . 'library/payxcommerce.php';
            $payload = (new PayXCommerce($this->settings()))->verifyWebhook($raw_body, $this->request->server);
        } catch (Throwable $exception) {
            $this->debug('Webhook verification failed: ' . $exception->getMessage());
            $this->response->addHeader('HTTP/1.1 401 Unauthorized');
            $this->response->setOutput('Invalid signature');
            return;
        }

        if ($event_id !== '' && $this->model_extension_payment_payxcommerce->webhookEventExists($event_id)) {
            $this->response->setOutput('Duplicate ignored');
            return;
        }

        $event_type = (string) ($payload['event_type'] ?? '');
        $order_id = $this->model_extension_payment_payxcommerce->findOrderId($payload);
        $this->model_extension_payment_payxcommerce->recordWebhookEvent($event_id, $order_id, $event_type, $raw_body);

        if ($order_id <= 0) {
            $this->model_extension_payment_payxcommerce->completeWebhookEvent($event_id, 'accepted_order_missing');
            $this->response->setOutput('Accepted');
            return;
        }

        try {
            $this->model_extension_payment_payxcommerce->updatePayxOrder($order_id, $payload);
            $status_id = $this->statusForEvent($event_type);
            if ($status_id) {
                $this->model_checkout_order->addOrderHistory($order_id, $status_id, $this->brandName() . ' event: ' . $event_type, true);
            }
            $this->model_extension_payment_payxcommerce->completeWebhookEvent($event_id);
            $this->response->setOutput('OK');
        } catch (Throwable $exception) {
            $this->model_extension_payment_payxcommerce->completeWebhookEvent($event_id, 'failed', $exception->getMessage());
            $this->response->addHeader('HTTP/1.1 500 Internal Server Error');
            $this->response->setOutput('Processing failed');
        }
    }

    private function statusForEvent(string $event_type): int
    {
        return match ($event_type) {
            'payment.success', 'payment.succeeded' => (int) $this->config->get('payment_payxcommerce_success_status_id'),
            'payment.failed' => (int) $this->config->get('payment_payxcommerce_failed_status_id'),
            'payment.cancelled', 'payment.canceled' => (int) $this->config->get('payment_payxcommerce_cancelled_status_id'),
            'payment.expired' => (int) $this->config->get('payment_payxcommerce_expired_status_id'),
            'refund.success', 'refund.succeeded', 'payment.refunded' => (int) $this->config->get('payment_payxcommerce_refunded_status_id'),
            'chargeback.created', 'dispute.created' => (int) $this->config->get('payment_payxcommerce_chargeback_status_id'),
            default => 0,
        };
    }

    private function debug(string $message): void
    {
        if ($this->config->get('payment_payxcommerce_debug')) {
            require_once DIR_SYSTEM . 'library/payxcommerce.php';
            $this->log->write('PayXCommerce: ' . PayXCommerce::redact($message));
        }
    }

    private function settings(): array
    {
        $keys = ['environment', 'auth_method', 'base_url', 'public_key', 'secret_key', 'client_id', 'client_secret', 'webhook_secret'];
        $settings = [];
        foreach ($keys as $key) {
            $settings['payment_payxcommerce_' . $key] = $this->config->get('payment_payxcommerce_' . $key);
        }
        return $settings;
    }

    private function brandName(): string
    {
        return (string) ($this->config->get('payment_payxcommerce_brand_name') ?: 'PayXCommerce');
    }

    private function publicText(string $key, string $default, string $brand): string
    {
        $value = (string) ($this->config->get($key) ?: $default);
        return str_replace('{brand}', $brand, $value);
    }
}
