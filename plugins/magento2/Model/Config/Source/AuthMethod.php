<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class AuthMethod implements ArrayInterface
{
    public function toOptionArray(): array
    {
        return [['value' => 'hmac', 'label' => __('HMAC API Key')], ['value' => 'bearer', 'label' => __('Developer App Bearer Token')]];
    }
}
