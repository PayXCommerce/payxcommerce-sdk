<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce\Order;

use WC_Order;

final class Metadata
{
    public const REQUEST_NUMBER = '_payxcommerce_request_number';
    public const INVOICE_NUMBER = '_payxcommerce_invoice_number';
    public const CHECKOUT_URL = '_payxcommerce_checkout_url';
    public const TRANSACTION_REFERENCE = '_payxcommerce_transaction_reference';
    public const PAYMENT_ID = '_payxcommerce_payment_id';
    public const SETTLEMENT_STATUS = '_payxcommerce_settlement_status';
    public const ENVIRONMENT = '_payxcommerce_environment';

    public function saveCheckout(WC_Order $order, array $response, string $environment): void
    {
        $order->update_meta_data(self::REQUEST_NUMBER, sanitize_text_field((string) ($response['request_number'] ?? '')));
        $order->update_meta_data(self::INVOICE_NUMBER, sanitize_text_field((string) ($response['invoice_number'] ?? '')));
        $order->update_meta_data(self::CHECKOUT_URL, esc_url_raw((string) ($response['checkout_url'] ?? '')));
        $order->update_meta_data(self::ENVIRONMENT, sanitize_text_field($environment));
    }

    public function markEvent(WC_Order $order, string $eventId): void
    {
        $order->update_meta_data('_payxcommerce_event_' . sanitize_key($eventId), current_time('mysql'));
    }

    public function hasEvent(WC_Order $order, string $eventId): bool
    {
        return $eventId !== '' && (bool) $order->get_meta('_payxcommerce_event_' . sanitize_key($eventId));
    }
}
