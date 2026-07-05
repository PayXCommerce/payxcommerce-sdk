<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use PayXCommerce\Payment\Model\Config;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'payxcommerce';

    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getConfig(): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();

        return [
            'payment' => [
                self::CODE => [
                    'brandName' => $this->config->brandName($storeId),
                    'title' => $this->config->publicText('title', 'Pay securely with {brand}', $storeId),
                    'description' => $this->config->publicText('description', 'You will be redirected to secure hosted checkout to complete your payment.', $storeId),
                    'buttonText' => $this->config->publicText('button_text', 'Continue to secure checkout', $storeId),
                    'redirectUrl' => 'payxcommerce/checkout/start',
                ],
            ],
        ];
    }
}
