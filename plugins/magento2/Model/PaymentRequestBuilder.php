<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model;

use Magento\Sales\Api\Data\OrderInterface;

class PaymentRequestBuilder
{
    public function __construct(private readonly Config $config)
    {
    }

    public function build(OrderInterface $order): array
    {
        $storeId = (int) $order->getStoreId();
        $billing = $order->getBillingAddress();

        return [
            'amount' => (float) $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode(),
            'purpose' => 'Magento Order #' . $order->getIncrementId(),
            'customer' => [
                'name' => $billing ? trim((string) $billing->getFirstname() . ' ' . (string) $billing->getLastname()) ?: 'Customer' : 'Customer',
                'email' => $order->getCustomerEmail(),
                'mobile' => $billing ? $billing->getTelephone() : '',
                'address' => $billing ? implode(' ', (array) $billing->getStreet()) : '',
                'city' => $billing ? $billing->getCity() : '',
                'country' => $billing ? $billing->getCountryId() : '',
            ],
            'merchant_reference' => 'M2-' . $order->getIncrementId(),
            'merchant_order_id' => (string) $order->getEntityId(),
            'success_url' => $order->getStore()->getBaseUrl() . 'checkout/onepage/success',
            'failed_url' => $order->getStore()->getBaseUrl() . 'checkout/cart',
            'cancel_url' => $order->getStore()->getBaseUrl() . 'checkout/cart',
            'webhook_url' => $order->getStore()->getBaseUrl() . 'payxcommerce/webhook/index',
            'ipn_events' => ['payment.succeeded', 'payment.failed', 'payment.cancelled', 'payment.expired', 'refund.succeeded', 'payment.refunded', 'chargeback.created', 'dispute.created'],
            'metadata' => [
                'platform' => 'magento2',
                'module_version' => Config::MODULE_VERSION,
                'order_id' => (string) $order->getEntityId(),
                'increment_id' => (string) $order->getIncrementId(),
                'store_id' => (string) $storeId,
            ],
            'is_test' => $this->config->value('environment', $storeId) !== 'live',
        ];
    }
}
