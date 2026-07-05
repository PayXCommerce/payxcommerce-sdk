PAYMENT_SUCCEEDED = "payment.succeeded"
PAYMENT_SUCCESS_LEGACY = "payment.success"
PAYMENT_FAILED = "payment.failed"
PAYMENT_CANCELLED = "payment.cancelled"
PAYMENT_CANCELED = "payment.canceled"
PAYMENT_EXPIRED = "payment.expired"
PAYMENT_REFUNDED = "payment.refunded"
REFUND_SUCCEEDED = "refund.succeeded"
REFUND_SUCCESS_LEGACY = "refund.success"
CHARGEBACK_CREATED = "chargeback.created"
DISPUTE_CREATED = "dispute.created"


def default_subscriptions() -> list[str]:
    return [PAYMENT_SUCCEEDED, PAYMENT_FAILED, PAYMENT_CANCELLED, PAYMENT_EXPIRED, REFUND_SUCCEEDED, PAYMENT_REFUNDED, CHARGEBACK_CREATED, DISPUTE_CREATED]


def is_successful_payment(event_type: str) -> bool:
    return event_type in {PAYMENT_SUCCEEDED, PAYMENT_SUCCESS_LEGACY}


def is_failed_payment(event_type: str) -> bool:
    return event_type == PAYMENT_FAILED


def is_cancelled_payment(event_type: str) -> bool:
    return event_type in {PAYMENT_CANCELLED, PAYMENT_CANCELED, PAYMENT_EXPIRED}


def is_refund_completed(event_type: str) -> bool:
    return event_type in {REFUND_SUCCEEDED, REFUND_SUCCESS_LEGACY, PAYMENT_REFUNDED}


def is_dispute_or_chargeback(event_type: str) -> bool:
    return event_type in {CHARGEBACK_CREATED, DISPUTE_CREATED}
