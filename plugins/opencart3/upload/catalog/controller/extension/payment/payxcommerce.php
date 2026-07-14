<?php

class ControllerExtensionPaymentPayXCommerce extends Controller
{
    public function index()
    {
        $this->load->language('extension/payment/payxcommerce');
        $this->load->model('checkout/order');
        $this->load->model('localisation/country');
        $brand = $this->brandName();
        $contact = $this->checkoutContact();

        return $this->load->view('extension/payment/payxcommerce', [
            'button_confirm' => $this->publicText('payment_payxcommerce_button_text', $this->language->get('button_confirm'), $brand),
            'description' => $this->publicText('payment_payxcommerce_description', $this->language->get('text_description'), $brand),
            'brand_name' => $brand,
            'icon_url' => 'catalog/view/theme/default/image/payxcommerce/logo-icon-dark-64.png',
            'countries' => $this->model_localisation_country->getCountries(),
            'current_phone' => $contact['phone'],
            'current_country' => $contact['country'],
            'requires_phone' => $contact['phone'] === '',
            'requires_country' => $contact['country'] === '',
            'text_required_contact_title' => $this->language->get('text_required_contact_title'),
            'text_required_contact_intro' => $this->language->get('text_required_contact_intro'),
            'entry_phone' => $this->language->get('entry_phone'),
            'entry_country' => $this->language->get('entry_country'),
            'text_select_country' => $this->language->get('text_select_country'),
            'text_required_contact_error' => $this->language->get('text_required_contact_error'),
            'button_continue' => $this->language->get('button_continue'),
            'button_cancel' => $this->language->get('button_cancel'),
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

        $submitted_contact = $this->submittedContact();
        $this->load->model('localisation/country');
        $order_country = $this->countryFromOrder($order);
        $country = $submitted_contact['country'] !== '' ? $this->countryByIsoCode2($submitted_contact['country']) : $order_country;
        $phone = $submitted_contact['phone'] !== '' ? $submitted_contact['phone'] : trim((string) ($order['telephone'] ?? ''));
        $country_iso = $country ? strtoupper((string) $country['iso_code_2']) : '';
        $country_name = $country ? (string) $country['name'] : (string) ($order['payment_country'] ?? '');

        if ($phone === '' || $country_iso === '') {
            $this->session->data['error'] = $this->language->get('error_required_contact');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        $this->model_extension_payment_payxcommerce->updateCheckoutContact($order, $phone, $country ?: []);
        $order['telephone'] = $phone;
        $order['payment_country'] = $country_name ?: $country_iso;
        $order['payment_iso_code_2'] = $country_iso;

        $merchant_reference = 'OC3-' . $order_id;
        $return_args = 'order_id=' . $order_id . '&merchant_reference=' . urlencode($merchant_reference);
        $payload = [
            'amount' => (float) $order['total'],
            'currency' => $order['currency_code'],
            'purpose' => 'OpenCart Order #' . $order_id,
            'customer' => [
                'name' => trim($order['firstname'] . ' ' . $order['lastname']) ?: 'Customer',
                'email' => $order['email'],
                'mobile' => $phone,
                'address' => trim($order['payment_address_1'] . ' ' . $order['payment_address_2']),
                'city' => $order['payment_city'],
                'country' => $country_iso,
            ],
            'merchant_reference' => $merchant_reference,
            'merchant_order_id' => (string) $order_id,
            'success_url' => $this->url->link('extension/payment/payxcommerce/success', $return_args, true),
            'failed_url' => $this->url->link('checkout/failure', '', true),
            'cancel_url' => $this->url->link('checkout/checkout', '', true),
            'webhook_url' => $this->url->link('extension/payment/payxcommerce/webhook', '', true),
            'ipn_events' => ['payment.succeeded', 'payment.failed', 'payment.cancelled', 'payment.expired', 'refund.succeeded', 'payment.refunded', 'chargeback.created', 'dispute.created'],
            'metadata' => [
                'platform' => 'opencart',
                'platform_version' => '3',
                'order_id' => (string) $order_id,
                'opencart_order_id' => (string) $order_id,
                'store_id' => (string) $order['store_id'],
                'merchant_reference' => $merchant_reference,
            ],
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
        $this->addOrderStatus($order_id, $this->statusSetting('pending_status_id'), $this->brandName() . ' checkout created. Status: pending.');
        $this->response->redirect($checkout_url);
    }

    public function success()
    {
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/payxcommerce');

        $order_id = $this->resolvedReturnOrderId();
        $merchant_reference = (string) ($this->request->get['merchant_reference'] ?? '');
        if ($order_id > 0 && $this->model_checkout_order->getOrder($order_id) && $this->model_extension_payment_payxcommerce->returnReferenceMatches($order_id, $merchant_reference)) {
            $this->model_extension_payment_payxcommerce->markReturnSuccess($order_id);
            $this->addOrderStatus($order_id, $this->statusSetting('success_status_id'), $this->brandName() . ' return: payment successful.');
        }

        $this->response->redirect($this->url->link('checkout/success', '', true));
    }

    private function resolvedReturnOrderId(): int
    {
        $session_order_id = (int) ($this->session->data['order_id'] ?? 0);
        if ($session_order_id > 0) {
            return $session_order_id;
        }

        return (int) ($this->request->get['order_id'] ?? 0);
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

        if ($event_id === '') {
            $event_id = 'payload-' . hash('sha256', $raw_body);
        }

        if ($event_id !== '' && $this->model_extension_payment_payxcommerce->webhookEventExists($event_id)) {
            $this->response->setOutput('Duplicate ignored');
            return;
        }

        $event_type = $this->eventTypeFromPayload($payload);
        if ($event_type !== '') {
            $payload['event_type'] = $event_type;
        }
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
                $this->addOrderStatus($order_id, $status_id, $this->brandName() . ' webhook event: ' . $event_type . '.');
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
        $event_type = strtolower(trim($event_type));

        return match ($event_type) {
            'payment.success', 'payment.succeeded' => $this->statusSetting('success_status_id'),
            'payment.failed' => $this->statusSetting('failed_status_id'),
            'payment.cancelled', 'payment.canceled' => $this->statusSetting('cancelled_status_id'),
            'payment.expired' => $this->statusSetting('expired_status_id'),
            'refund.success', 'refund.succeeded', 'payment.refunded' => $this->statusSetting('refunded_status_id'),
            'chargeback.created', 'dispute.created' => $this->statusSetting('chargeback_status_id'),
            default => 0,
        };
    }

    private function eventTypeFromPayload(array $payload): string
    {
        foreach (['event_type', 'type', 'event'] as $key) {
            if (!empty($payload[$key]) && is_string($payload[$key])) {
                return strtolower(trim($payload[$key]));
            }
        }

        foreach (['data.event_type', 'payload.event_type', 'resource.event_type'] as $path) {
            $value = $this->payloadValue($payload, $path);
            if (is_string($value) && $value !== '') {
                return strtolower(trim($value));
            }
        }

        foreach (['payment_status', 'status', 'data.payment_status', 'data.status', 'payload.payment_status', 'payload.status', 'resource.payment_status', 'resource.status'] as $path) {
            $value = $this->payloadValue($payload, $path);
            if ($value === null || $value === '') {
                continue;
            }

            $status = strtolower((string) $value);
            if (in_array($status, ['paid', 'success', 'successful', 'succeeded', 'completed'], true)) {
                return 'payment.success';
            }
            if (in_array($status, ['failed', 'declined', 'error'], true)) {
                return 'payment.failed';
            }
            if (in_array($status, ['cancelled', 'canceled'], true)) {
                return 'payment.cancelled';
            }
            if ($status === 'expired') {
                return 'payment.expired';
            }
            if (in_array($status, ['refunded', 'refund_successful'], true)) {
                return 'payment.refunded';
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

    private function statusSetting(string $key): int
    {
        return (int) $this->config->get('payment_payxcommerce_' . $key);
    }

    private function addOrderStatus(int $order_id, int $status_id, string $comment): void
    {
        if ($status_id <= 0) {
            return;
        }

        $this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment, true, true);
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

    private function checkoutContact(): array
    {
        $order = [];
        $order_id = (int) ($this->session->data['order_id'] ?? 0);
        if ($order_id > 0) {
            $order = $this->model_checkout_order->getOrder($order_id) ?: [];
        }

        $phone = trim((string) ($order['telephone'] ?? ''));
        if ($phone === '' && $this->customer->isLogged()) {
            $phone = trim((string) $this->customer->getTelephone());
        }

        $country_data = $this->countryFromOrder($order);
        $country = strtoupper(trim((string) ($country_data['iso_code_2'] ?? '')));
        if ($country === '' && !empty($this->session->data['payment_address']['iso_code_2'])) {
            $country = strtoupper(trim((string) $this->session->data['payment_address']['iso_code_2']));
        }

        return ['phone' => $phone, 'country' => $country];
    }

    private function submittedContact(): array
    {
        $phone = preg_replace('/[^0-9+().\\-\\s]/', '', (string) ($this->request->post['payx_customer_phone'] ?? ''));
        $country = strtoupper(trim((string) ($this->request->post['payx_customer_country'] ?? '')));

        return [
            'phone' => trim((string) $phone),
            'country' => preg_match('/^[A-Z]{2}$/', $country) ? $country : '',
        ];
    }

    private function countryByIsoCode2(string $iso_code_2): array
    {
        $iso_code_2 = strtoupper($iso_code_2);

        foreach ($this->model_localisation_country->getCountries() as $country) {
            if (strtoupper((string) ($country['iso_code_2'] ?? '')) === $iso_code_2) {
                return $country;
            }
        }

        return [];
    }

    private function countryFromOrder(array $order): array
    {
        $iso_code_2 = strtoupper(trim((string) ($order['payment_iso_code_2'] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $iso_code_2)) {
            return [
                'country_id' => (int) ($order['payment_country_id'] ?? 0),
                'name' => (string) ($order['payment_country'] ?? $iso_code_2),
                'iso_code_2' => $iso_code_2,
            ];
        }

        if (!empty($order['payment_country_id']) && is_callable([$this->model_localisation_country, 'getCountry'])) {
            return $this->model_localisation_country->getCountry((int) $order['payment_country_id']) ?: [];
        }

        $country = strtoupper(trim((string) ($order['payment_country'] ?? '')));
        return preg_match('/^[A-Z]{2}$/', $country) ? $this->countryByIsoCode2($country) : [];
    }
}
