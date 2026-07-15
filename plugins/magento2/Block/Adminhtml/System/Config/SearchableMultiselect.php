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
        $html .= '<div id="' . $summaryId . '" class="payxcommerce-selected-summary"></div>';
        $html .= '</div>';
        $html .= '<style>
            #' . $selectId . ' { min-height: 190px; max-width: 520px; width: 100%; }
            .payxcommerce-searchable-multiselect option:checked { background: #021b3a linear-gradient(0deg,#021b3a,#021b3a) !important; color: #fff !important; font-weight: 700; }
            .payxcommerce-selected-summary { align-items: center; display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; max-width: 560px; }
            .payxcommerce-selected-pill { background: #eef9fc; border: 1px solid #bdebf4; border-radius: 999px; color: #023047; display: inline-flex; font-size: 12px; font-weight: 700; line-height: 1.2; padding: 6px 10px; }
            .payxcommerce-selected-empty { color: #586674; font-size: 12px; }
        </style>';
        $html .= '<script>
            require(["jquery"], function ($) {
                var select = $("#' . $selectId . '");
                var search = $("#' . $searchId . '");
                var summary = $("#' . $summaryId . '");
                function updateSummary() {
                    var selected = select.find("option:selected").map(function () {
                        return {label: $(this).text(), value: String($(this).val())};
                    }).get();
                    summary.empty();
                    if (!selected.length) {
                        summary.append($("<span>").addClass("payxcommerce-selected-empty").text("No country restrictions selected. All countries are allowed."));
                        return;
                    }
                    selected.slice(0, 12).forEach(function (country) {
                        summary.append($("<span>").addClass("payxcommerce-selected-pill").text(country.label + (country.value ? " (" + country.value + ")" : "")));
                    });
                    if (selected.length > 12) {
                        summary.append($("<span>").addClass("payxcommerce-selected-pill").text("+" + (selected.length - 12) + " more"));
                    }
                }
                search.on("input", function () {
                    var query = $(this).val().toLowerCase();
                    select.find("option").each(function () {
                        var option = $(this);
                        var text = option.text().toLowerCase();
                        var value = String(option.val()).toLowerCase();
                        option.toggle(option.prop("selected") || query === "" || text.indexOf(query) !== -1 || value.indexOf(query) !== -1);
                    });
                });
                select.on("change", updateSummary);
                updateSummary();
            });
        </script>';

        return $html;
    }
}
