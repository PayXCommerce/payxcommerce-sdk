<?php

namespace PayXCommerce\Payment\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class SearchableMultiselect extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $selectId = $element->getHtmlId();
        $searchId = $selectId . '_search';
        $summaryId = $selectId . '_summary';
        $html = '<div class="payxcommerce-searchable-multiselect">';
        $html .= '<input type="search" id="' . $searchId . '" class="admin__control-text" placeholder="Search countries..." style="margin-bottom:8px;max-width:420px;width:100%;">';
        $html .= $element->getElementHtml();
        $html .= '<div id="' . $summaryId . '" style="margin-top:8px;color:#586674;font-size:12px;"></div>';
        $html .= '</div>';
        $html .= '<style>
            #' . $selectId . ' { min-height: 190px; max-width: 520px; width: 100%; }
            .payxcommerce-searchable-multiselect option:checked { background: #021b3a linear-gradient(0deg,#021b3a,#021b3a); color: #fff; }
        </style>';
        $html .= '<script>
            require(["jquery"], function ($) {
                var select = $("#' . $selectId . '");
                var search = $("#' . $searchId . '");
                var summary = $("#' . $summaryId . '");
                function updateSummary() {
                    var selected = select.find("option:selected").map(function () { return $(this).text(); }).get();
                    summary.text(selected.length ? selected.length + " selected: " + selected.slice(0, 8).join(", ") + (selected.length > 8 ? " +" + (selected.length - 8) + " more" : "") : "No country restrictions selected. All countries are allowed.");
                }
                search.on("input", function () {
                    var query = $(this).val().toLowerCase();
                    select.find("option").each(function () {
                        var option = $(this);
                        var text = option.text().toLowerCase();
                        var value = String(option.val()).toLowerCase();
                        option.toggle(query === "" || text.indexOf(query) !== -1 || value.indexOf(query) !== -1);
                    });
                });
                select.on("change", updateSummary);
                updateSummary();
            });
        </script>';

        return $html;
    }
}
