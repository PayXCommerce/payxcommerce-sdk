<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class Active extends Value
{
    public function beforeSave()
    {
        if ((string) $this->getValue() !== '1') {
            return parent::beforeSave();
        }

        $authMethod = $this->fieldValue('auth_method', 'hmac');
        $webhookSecret = $this->fieldValue('webhook_secret');

        if ($webhookSecret === '') {
            throw new LocalizedException(__('Webhook Secret is required before enabling PayXCommerce.'));
        }

        if ($authMethod === 'bearer') {
            if ($this->fieldValue('client_id') === '' || $this->fieldValue('client_secret') === '') {
                throw new LocalizedException(__('Developer App Client ID and Client Secret are required before enabling PayXCommerce.'));
            }
        } elseif ($this->fieldValue('public_key') === '' || $this->fieldValue('secret_key') === '') {
            throw new LocalizedException(__('Public Key and Secret Key are required before enabling PayXCommerce.'));
        }

        return parent::beforeSave();
    }

    private function fieldValue(string $field, string $default = ''): string
    {
        $value = $this->getData('groups/payxcommerce/fields/' . $field . '/value');
        if ($value === null || $value === '') {
            $value = $this->_config->getValue('payment/payxcommerce/' . $field);
        }

        $value = is_scalar($value) ? trim((string) $value) : '';
        return $value !== '' ? $value : $default;
    }
}
