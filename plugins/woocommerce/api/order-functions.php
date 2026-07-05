<?php
/**
 * Public helper functions for PayXCommerce WooCommerce order operations.
 */

declare(strict_types=1);

use PayXCommerce\WooCommerce\Order\Metadata;

if (!function_exists('payxcommerce_get_order_request_number')) {
    function payxcommerce_get_order_request_number(WC_Order $order): string
    {
        return (string) $order->get_meta(Metadata::REQUEST_NUMBER);
    }
}

if (!function_exists('payxcommerce_get_order_transaction_reference')) {
    function payxcommerce_get_order_transaction_reference(WC_Order $order): string
    {
        return (string) $order->get_meta(Metadata::TRANSACTION_REFERENCE);
    }
}

if (!function_exists('payxcommerce_order_has_checkout_url')) {
    function payxcommerce_order_has_checkout_url(WC_Order $order): bool
    {
        return (string) $order->get_meta(Metadata::CHECKOUT_URL) !== '';
    }
}

if (!function_exists('payxcommerce_gateway_instance')) {
    function payxcommerce_gateway_instance(): ?object
    {
        if (!function_exists('WC')) {
            return null;
        }
        $gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
        return isset($gateways['payxcommerce']) && is_object($gateways['payxcommerce']) ? $gateways['payxcommerce'] : null;
    }
}
