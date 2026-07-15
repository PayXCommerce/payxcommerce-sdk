define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/url',
    'jquery'
], function (Component, placeOrderAction, additionalValidators, fullScreenLoader, urlBuilder, $) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'PayXCommerce_Payment/payment/payxcommerce'
        },

        redirectAfterPlaceOrder: false,

        getCode: function () {
            return 'payxcommerce';
        },

        getTitle: function () {
            return window.checkoutConfig.payment.payxcommerce.title || 'PayXCommerce';
        },

        getDescription: function () {
            return window.checkoutConfig.payment.payxcommerce.description || 'You will be redirected to secure hosted checkout to complete your payment.';
        },

        getButtonText: function () {
            return window.checkoutConfig.payment.payxcommerce.buttonText || 'Continue to secure checkout';
        },

        getIconUrl: function () {
            return window.checkoutConfig.payment.payxcommerce.iconUrl || '';
        },

        placeOrder: function (data, event) {
            if (event) {
                event.preventDefault();
            }

            if (!this.validate() || !additionalValidators.validate()) {
                return false;
            }

            fullScreenLoader.startLoader();
            $.when(placeOrderAction(this.getData(), this.messageContainer)).done(function () {
                var redirectUrl = window.checkoutConfig.payment.payxcommerce.redirectUrl || 'payxcommerce/checkout/start';
                window.location.replace(urlBuilder.build(redirectUrl));
            }).fail(function () {
                fullScreenLoader.stopLoader();
            });

            return true;
        }
    });
});
