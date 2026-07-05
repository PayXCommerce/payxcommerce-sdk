<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use PayXCommerce\Payment\Model\Api\Client;

class Start implements HttpGetActionInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Client $client
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
        $billing = $order->getBillingAddress();
        $payload = [
            'amount' => (float) $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode(),
            'purpose' => 'Magento Order #' . $order->getIncrementId(),
            'customer' => [
                'name' => trim((string) $billing->getFirstname() . ' ' . (string) $billing->getLastname()) ?: 'Customer',
                'email' => $order->getCustomerEmail(),
                'mobile' => $billing->getTelephone(),
                'address' => implode(' ', (array) $billing->getStreet()),
                'city' => $billing->getCity(),
                'country' => $billing->getCountryId(),
            ],
            'merchant_reference' => 'M2-' . $order->getIncrementId(),
            'merchant_order_id' => (string) $order->getEntityId(),
            'success_url' => $order->getStore()->getBaseUrl() . 'checkout/onepage/success',
            'failed_url' => $order->getStore()->getBaseUrl() . 'checkout/cart',
            'cancel_url' => $order->getStore()->getBaseUrl() . 'checkout/cart',
            'webhook_url' => $order->getStore()->getBaseUrl() . 'payxcommerce/webhook/index',
            'ipn_events' => ['payment.success', 'payment.failed', 'payment.cancelled', 'payment.expired', 'refund.success', 'chargeback.created'],
            'metadata' => ['platform' => 'magento2', 'order_id' => (string) $order->getEntityId(), 'increment_id' => $order->getIncrementId(), 'store_id' => (string) $storeId],
            'is_test' => $this->client->config('environment', $storeId) !== 'live',
        ];

        try {
            $response = $this->client->createPaymentRequest($payload, 'magento2-order-' . $order->getEntityId() . '-' . time(), $storeId);
            $payment = $order->getPayment();
            $payment->setAdditionalInformation('payxcommerce_request_number', $response['request_number'] ?? '');
            $payment->setAdditionalInformation('payxcommerce_invoice_number', $response['invoice_number'] ?? '');
            $payment->setAdditionalInformation('payxcommerce_checkout_url', $response['checkout_url'] ?? '');
            $order->addCommentToStatusHistory('PayXCommerce checkout created: ' . ($response['request_number'] ?? ''));
            $this->orderRepository->save($order);
            return $result->setUrl((string) ($response['checkout_url'] ?? $order->getStore()->getBaseUrl() . 'checkout/cart'));
        } catch (\Throwable $exception) {
            $order->addCommentToStatusHistory('PayXCommerce checkout creation failed: ' . $exception->getMessage());
            $this->orderRepository->save($order);
            return $result->setPath('checkout/cart');
        }
    }
}
