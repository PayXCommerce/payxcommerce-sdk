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
            return window.checkoutConfig.payment.payxcommerce.description || 'You will be redirected to PayXCommerce hosted checkout.';
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
                window.location.replace(urlBuilder.build('payxcommerce/checkout/start'));
            }).fail(function () {
                fullScreenLoader.stopLoader();
            });

            return true;
        }
    });
});
