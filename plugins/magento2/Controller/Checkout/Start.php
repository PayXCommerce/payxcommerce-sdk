<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Api\Data\OrderInterface;
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
        private readonly PaymentRequestBuilder $paymentRequestBuilder,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute()
    {
        $result = $this->redirectFactory->create();
        $order = $this->resolveCheckoutOrder();
        if (!$order || !$order->getEntityId()) {
            $this->messageManager->addErrorMessage(__('Unable to locate the order for PayXCommerce checkout. Please try again.'));

            return $result->setPath('checkout');
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
            $checkoutUrl = $this->resolveCheckoutUrl($response);
            $payment->setAdditionalInformation('payxcommerce_checkout_url', $checkoutUrl);
            $order->addCommentToStatusHistory($this->config->brandName($storeId) . ' checkout created: ' . ($response['request_number'] ?? ''));
            $this->orderRepository->save($order);

            return $result->setUrl($checkoutUrl);
        } catch (\Throwable $exception) {
            $this->logger->error('Checkout creation failed: ' . $exception->getMessage(), ['order_id' => (string) $order->getEntityId()]);
            $order->addCommentToStatusHistory($this->config->brandName($storeId) . ' checkout creation failed.');
            $this->orderRepository->save($order);
            $this->messageManager->addErrorMessage(__('Unable to start PayXCommerce checkout. Please review your order and try again.'));

            return $result->setPath('checkout');
        }
    }

    private function resolveCheckoutOrder(): ?OrderInterface
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if ($order && $order->getEntityId()) {
            return $order;
        }

        $orderId = (int) $this->checkoutSession->getLastOrderId();
        if ($orderId <= 0) {
            return null;
        }

        try {
            return $this->orderRepository->get($orderId);
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to reload last order for checkout: ' . $exception->getMessage(), ['order_id' => (string) $orderId]);

            return null;
        }
    }

    /**
     * @param array<string,mixed> $response
     */
    private function resolveCheckoutUrl(array $response): string
    {
        foreach (['checkout_url', 'payment_url', 'redirect_url', 'url'] as $key) {
            $url = trim((string) ($response[$key] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        throw new \RuntimeException('PayXCommerce response did not include a checkout redirect URL.');
    }
}
