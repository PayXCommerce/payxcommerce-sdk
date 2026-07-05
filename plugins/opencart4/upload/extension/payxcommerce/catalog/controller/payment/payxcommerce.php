<?php

namespace Opencart\Catalog\Controller\Extension\Payxcommerce\Payment;

class Payxcommerce extends \Opencart\System\Engine\Controller
{
    public function index(): string
    {
        $this->load->language('extension/payxcommerce/payment/payxcommerce');

        return $this->load->view('extension/payxcommerce/payment/payxcommerce', [
            'button_confirm' => $this->language->get('button_confirm'),
            'action' => $this->url->link('extension/payxcommerce/payment/payxcommerce.confirm', '', true),
        ]);
    }

    public function confirm(): void
    {
        $this->response->redirect($this->url->link('checkout/success', '', true));
    }

    public function webhook(): void
    {
        $this->response->setOutput('PayXCommerce OpenCart 4 webhook endpoint placeholder');
    }
}

