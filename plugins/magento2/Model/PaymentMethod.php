<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\CartInterface;

class PaymentMethod extends AbstractMethod
{
    public const CODE = 'payxcommerce';

    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = false;
    protected $_canRefund = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;

    public function isAvailable(CartInterface $quote = null): bool
    {
        if (!parent::isAvailable($quote)) {
            return false;
        }

        if (!$this->getConfigData('webhook_secret')) {
            return false;
        }

        if ($this->getConfigData('auth_method') === 'bearer') {
            if (!$this->getConfigData('client_id') || !$this->getConfigData('client_secret')) {
                return false;
            }
        } elseif (!$this->getConfigData('public_key') || !$this->getConfigData('secret_key')) {
            return false;
        }

        if (!$quote) {
            return true;
        }

        $currency = strtoupper((string) $quote->getQuoteCurrencyCode());
        $allowedCurrencies = $this->csvConfig('allowed_currencies');
        if ($allowedCurrencies && !in_array($currency, $allowedCurrencies, true)) {
            return false;
        }

        $billing = $quote->getBillingAddress();
        $country = $billing ? strtoupper((string) $billing->getCountryId()) : '';
        $allowedCountries = $this->csvConfig('allowed_countries');
        if ($country !== '' && $allowedCountries && !in_array($country, $allowedCountries, true)) {
            return false;
        }

        $total = (float) $quote->getGrandTotal();
        $min = (float) $this->getConfigData('min_order_total');
        $max = (float) $this->getConfigData('max_order_total');

        return !($min > 0 && $total < $min) && !($max > 0 && $total > $max);
    }

    private function csvConfig(string $key): array
    {
        return array_values(array_filter(array_map(static fn($value) => strtoupper(trim($value)), explode(',', (string) $this->getConfigData($key)))));
    }
}
