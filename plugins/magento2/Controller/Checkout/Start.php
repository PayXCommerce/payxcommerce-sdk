<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use PayXCommerce\Payment\Model\Api\Client;
use PayXCommerce\Payment\Model\Config;
use PayXCommerce\Payment\Model\Logger;
use PayXCommerce\Payment\Model\PaymentRequestBuilder;

class Start implements HttpGetActionInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Client $client,
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly PaymentRequestBuilder $paymentRequestBuilder
    ) {
    }

    public function execute()
    {
        $result = $this->redirectFactory->create();
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order || !$order->getEntityId()) {
            return $result->setPath('checkout/cart');
        }

        $storeId = (int) $order->getStoreId();

        try {
            if (!$this->config->isConfigured($storeId)) {
                throw new \RuntimeException('Payment method is not fully configured.');
            }

            $payload = $this->paymentRequestBuilder->build($order);
            $response = $this->client->createPaymentRequest($payload, 'magento2-order-' . $order->getEntityId() . '-' . time(), $storeId);
            $payment = $order->getPayment();
            $payment->setAdditionalInformation('payxcommerce_request_number', $response['request_number'] ?? '');
            $payment->setAdditionalInformation('payxcommerce_invoice_number', $response['invoice_number'] ?? '');
            $payment->setAdditionalInformation('payxcommerce_checkout_url', $response['checkout_url'] ?? '');
            $order->addCommentToStatusHistory($this->config->brandName($storeId) . ' checkout created: ' . ($response['request_number'] ?? ''));
            $this->orderRepository->save($order);
            return $result->setUrl((string) ($response['checkout_url'] ?? $order->getStore()->getBaseUrl() . 'checkout/cart'));
        } catch (\Throwable $exception) {
            $this->logger->error('Checkout creation failed: ' . $exception->getMessage(), ['order_id' => (string) $order->getEntityId()]);
            $order->addCommentToStatusHistory($this->config->brandName($storeId) . ' checkout creation failed.');
            $this->orderRepository->save($order);
            return $result->setPath('checkout/cart');
        }
    }
}
