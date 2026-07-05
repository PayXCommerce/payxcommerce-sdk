<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Environment implements ArrayInterface
{
    public function toOptionArray(): array
    {
        return [['value' => 'test', 'label' => __('Test')], ['value' => 'live', 'label' => __('Live')]];
    }
}
