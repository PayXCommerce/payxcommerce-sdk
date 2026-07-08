<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Logo extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $iconUrl = $this->getViewFileUrl('PayXCommerce_Payment::images/logo-icon-dark-64.png');

        return sprintf(
            '<div style="display:inline-flex;align-items:center;gap:10px;padding:8px 0;"><img src="%s" alt="PayXCommerce" width="40" height="40" style="border-radius:8px;"><strong>PayXCommerce Hosted Checkout</strong></div>',
            $this->escapeUrl($iconUrl)
        );
    }

    protected function _renderScopeLabel(AbstractElement $element): string
    {
        return '';
    }

    protected function _isInheritCheckboxRequired(AbstractElement $element): bool
    {
        return false;
    }
}
