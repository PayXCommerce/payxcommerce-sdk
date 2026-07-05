<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce\Order;

use WC_Order;

final class PayloadBuilder
{
    public function build(WC_Order $order, string $webhookUrl, bool $isTest): array
    {
        return [
            'amount' => (float) $order->get_total(),
            'currency' => $order->get_currency(),
            'purpose' => 'WooCommerce Order #' . $order->get_order_number(),
            'customer' => [
                'name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: 'Customer',
                'email' => $order->get_billing_email(),
                'mobile' => $order->get_billing_phone(),
                'address' => trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2()),
                'city' => $order->get_billing_city(),
                'country' => $order->get_billing_country() ?: 'US',
            ],
            'merchant_reference' => 'WC-' . $order->get_id(),
            'merchant_order_id' => (string) $order->get_id(),
            'success_url' => $order->get_checkout_order_received_url(),
            'failed_url' => $order->get_cancel_order_url_raw(),
            'cancel_url' => $order->get_cancel_order_url_raw(),
            'webhook_url' => $webhookUrl,
            'ipn_events' => ['payment.succeeded', 'payment.failed', 'payment.cancelled', 'payment.expired', 'refund.succeeded', 'payment.refunded', 'chargeback.created', 'dispute.created'],
            'metadata' => [
                'platform' => 'woocommerce',
                'platform_version' => defined('WC_VERSION') ? WC_VERSION : '',
                'site_url' => home_url('/'),
                'order_id' => (string) $order->get_id(),
            ],
            'is_test' => $isTest,
        ];
    }
}
