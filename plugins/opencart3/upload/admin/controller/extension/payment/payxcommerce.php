<?php

class ControllerExtensionPaymentPayXCommerce extends Controller
{
    public function index()
    {
        $this->load->language('extension/payment/payxcommerce');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] ?? '') === 'POST' && $this->user->hasPermission('modify', 'extension/payment/payxcommerce')) {
            $this->model_setting_setting->editSetting('payment_payxcommerce', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
            return;
        }

        $data = [
            'action' => $this->url->link('extension/payment/payxcommerce', 'user_token=' . $this->session->data['user_token'], true),
            'cancel' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
            'payment_payxcommerce_status' => $this->config->get('payment_payxcommerce_status'),
            'payment_payxcommerce_environment' => $this->config->get('payment_payxcommerce_environment') ?: 'test',
            'payment_payxcommerce_base_url' => $this->config->get('payment_payxcommerce_base_url') ?: 'https://payxcommerce.com/api/v1',
            'payment_payxcommerce_public_key' => $this->config->get('payment_payxcommerce_public_key'),
            'payment_payxcommerce_secret_key' => $this->config->get('payment_payxcommerce_secret_key'),
            'payment_payxcommerce_webhook_secret' => $this->config->get('payment_payxcommerce_webhook_secret'),
            'payment_payxcommerce_sort_order' => $this->config->get('payment_payxcommerce_sort_order'),
        ];

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/payxcommerce', $data));
    }
}

