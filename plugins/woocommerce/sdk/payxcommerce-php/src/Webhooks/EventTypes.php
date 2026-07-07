<?php

declare(strict_types=1);

namespace PayXCommerce\Webhooks;

final class EventTypes
{
    public const PAYMENT_SUCCEEDED = 'payment.succeeded';
    public const PAYMENT_SUCCESS_LEGACY = 'payment.success';
    public const PAYMENT_FAILED = 'payment.failed';
    public const PAYMENT_CANCELLED = 'payment.cancelled';
    public const PAYMENT_CANCELED = 'payment.canceled';
    public const PAYMENT_EXPIRED = 'payment.expired';
    public const PAYMENT_REFUNDED = 'payment.refunded';
    public const REFUND_SUCCEEDED = 'refund.succeeded';
    public const REFUND_SUCCESS_LEGACY = 'refund.success';
    public const CHARGEBACK_CREATED = 'chargeback.created';
    public const DISPUTE_CREATED = 'dispute.created';

    public static function defaultSubscriptions(): array
    {
        return [
            self::PAYMENT_SUCCEEDED,
            self::PAYMENT_FAILED,
            self::PAYMENT_CANCELLED,
            self::PAYMENT_EXPIRED,
            self::REFUND_SUCCEEDED,
            self::PAYMENT_REFUNDED,
            self::CHARGEBACK_CREATED,
            self::DISPUTE_CREATED,
        ];
    }

    public static function isSuccessfulPayment(string $eventType): bool
    {
        return in_array($eventType, [self::PAYMENT_SUCCEEDED, self::PAYMENT_SUCCESS_LEGACY], true);
    }

    public static function isFailedPayment(string $eventType): bool
    {
        return $eventType === self::PAYMENT_FAILED;
    }

    public static function isCancelledPayment(string $eventType): bool
    {
        return in_array($eventType, [self::PAYMENT_CANCELLED, self::PAYMENT_CANCELED, self::PAYMENT_EXPIRED], true);
    }

    public static function isRefundCompleted(string $eventType): bool
    {
        return in_array($eventType, [self::REFUND_SUCCEEDED, self::REFUND_SUCCESS_LEGACY, self::PAYMENT_REFUNDED], true);
    }

    public static function isDisputeOrChargeback(string $eventType): bool
    {
        return in_array($eventType, [self::CHARGEBACK_CREATED, self::DISPUTE_CREATED], true);
    }
}
