<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'payxcommerce';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function getConfig(): array
    {
        return [
            'payment' => [
                self::CODE => [
                    'title' => (string) $this->scopeConfig->getValue('payment/payxcommerce/title'),
                    'description' => (string) $this->scopeConfig->getValue('payment/payxcommerce/description'),
                    'redirectUrl' => 'payxcommerce/checkout/start',
                ],
            ],
        ];
    }
}
