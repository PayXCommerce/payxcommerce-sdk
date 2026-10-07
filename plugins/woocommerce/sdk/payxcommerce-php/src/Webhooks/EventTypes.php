<?php

declare(strict_types=1);

namespace PayXCommerce\Webhooks;

final class EventTypes
{
    public const PAYMENT_SUCCESS = 'payment.success';
    public const PAYMENT_SUCCEEDED = 'payment.succeeded';
    /** @deprecated Historical misnomer retained for backwards compatibility. */
    public const PAYMENT_SUCCESS_LEGACY = 'payment.success';
    public const PAYMENT_SUCCEEDED_LEGACY = 'payment.succeeded';
    public const PAYMENT_REQUEST_CREATED = 'payment_request.created';
    public const PAYMENT_REQUEST_EXPIRED = 'payment_request.expired';
    public const PAYMENT_CREATED = 'payment.created';
    public const PAYMENT_PROCESSING = 'payment.processing';
    public const PAYMENT_FAILED = 'payment.failed';
    public const PAYMENT_CANCELLED = 'payment.cancelled';
    public const PAYMENT_CANCELED = 'payment.canceled';
    public const PAYMENT_EXPIRED = 'payment.expired';
    public const PAYMENT_REFUNDED = 'payment.refunded';
    public const REFUND_SUCCEEDED = 'refund.succeeded';
    public const REFUND_SUCCESS = 'refund.success';
    /** @deprecated Historical misnomer retained for backwards compatibility. */
    public const REFUND_SUCCESS_LEGACY = 'refund.success';
    public const REFUND_SUCCEEDED_LEGACY = 'refund.succeeded';
    public const REFUND_REQUESTED = 'refund.requested';
    public const REFUND_CREATED = 'refund.created';
    public const REFUND_APPROVED = 'refund.approved';
    public const REFUND_FAILED = 'refund.failed';
    public const REFUND_CANCELLED = 'refund.cancelled';
    public const REFUND_REJECTED = 'refund.rejected';
    public const REFUND_REVERSED = 'refund.reversed';
    public const CHARGEBACK_CREATED = 'chargeback.created';
    public const CHARGEBACK_UPDATED = 'chargeback.updated';
    public const CHARGEBACK_CLOSED = 'chargeback.closed';
    public const DISPUTE_CREATED = 'dispute.created';
    public const DISPUTE_OPENED = 'dispute.opened';
    public const DISPUTE_UPDATED = 'dispute.updated';
    public const DISPUTE_EVIDENCE_REQUIRED = 'dispute.evidence_required';
    public const DISPUTE_WON = 'dispute.won';
    public const DISPUTE_LOST = 'dispute.lost';
    public const DISPUTE_CLOSED = 'dispute.closed';
    public const TRANSACTION_CREATED = 'transaction.created';
    public const SETTLEMENT_CREATED = 'settlement.created';
    public const SETTLEMENT_APPROVED = 'settlement.approved';
    public const SETTLEMENT_PAID = 'settlement.paid';
    public const SETTLEMENT_FAILED = 'settlement.failed';
    public const BALANCE_UPDATED = 'balance.updated';
    public const ACCOUNT_STATUS_CHANGED = 'account.status_changed';
    public const CUSTOMER_KYC_REQUIRED = 'customer.kyc.required';
    public const CUSTOMER_KYC_SESSION_CREATED = 'customer.kyc.session_created';
    public const CUSTOMER_KYC_APPROVED = 'customer.kyc.approved';
    public const CUSTOMER_KYC_DECLINED = 'customer.kyc.declined';
    public const CUSTOMER_KYC_REVIEW = 'customer.kyc.review';
    public const CUSTOMER_KYC_INFO_REQUESTED = 'customer.kyc.info_requested';
    public const CUSTOMER_KYC_INFO_SUBMITTED = 'customer.kyc.info_submitted';
    public const WEBHOOK_TEST = 'webhook.test';

    public static function defaultSubscriptions(): array
    {
        return [
            self::PAYMENT_SUCCESS,
            self::PAYMENT_FAILED,
            self::PAYMENT_CANCELLED,
            self::PAYMENT_EXPIRED,
            self::REFUND_SUCCESS,
            self::PAYMENT_REFUNDED,
            self::CHARGEBACK_CREATED,
            self::DISPUTE_CREATED,
        ];
    }

    public static function isSuccessfulPayment(string $eventType): bool
    {
        return in_array($eventType, [self::PAYMENT_SUCCESS, self::PAYMENT_SUCCEEDED], true);
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
        return in_array($eventType, [self::REFUND_SUCCESS, self::REFUND_SUCCEEDED, self::PAYMENT_REFUNDED], true);
    }

    public static function isDisputeOrChargeback(string $eventType): bool
    {
        return in_array($eventType, [self::CHARGEBACK_CREATED, self::DISPUTE_CREATED], true);
    }
}
