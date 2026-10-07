PAYMENT_SUCCESS = "payment.success"
PAYMENT_SUCCEEDED = "payment.succeeded"
PAYMENT_SUCCESS_LEGACY = "payment.success"
PAYMENT_SUCCEEDED_LEGACY = "payment.succeeded"
PAYMENT_REQUEST_CREATED = "payment_request.created"
PAYMENT_REQUEST_EXPIRED = "payment_request.expired"
PAYMENT_CREATED = "payment.created"
PAYMENT_PROCESSING = "payment.processing"
PAYMENT_FAILED = "payment.failed"
PAYMENT_CANCELLED = "payment.cancelled"
PAYMENT_CANCELED = "payment.canceled"
PAYMENT_EXPIRED = "payment.expired"
PAYMENT_REFUNDED = "payment.refunded"
REFUND_SUCCEEDED = "refund.succeeded"
REFUND_SUCCESS = "refund.success"
REFUND_SUCCESS_LEGACY = "refund.success"
REFUND_SUCCEEDED_LEGACY = "refund.succeeded"
REFUND_REQUESTED = "refund.requested"
CHARGEBACK_CREATED = "chargeback.created"
DISPUTE_CREATED = "dispute.created"
REFUND_CREATED = "refund.created"
REFUND_APPROVED = "refund.approved"
REFUND_FAILED = "refund.failed"
REFUND_CANCELLED = "refund.cancelled"
REFUND_REJECTED = "refund.rejected"
REFUND_REVERSED = "refund.reversed"
CHARGEBACK_UPDATED = "chargeback.updated"
CHARGEBACK_CLOSED = "chargeback.closed"
DISPUTE_EVIDENCE_REQUIRED = "dispute.evidence_required"
DISPUTE_OPENED = "dispute.opened"
DISPUTE_UPDATED = "dispute.updated"
DISPUTE_WON = "dispute.won"
DISPUTE_LOST = "dispute.lost"
DISPUTE_CLOSED = "dispute.closed"
SETTLEMENT_CREATED = "settlement.created"
SETTLEMENT_APPROVED = "settlement.approved"
SETTLEMENT_PAID = "settlement.paid"
SETTLEMENT_FAILED = "settlement.failed"
TRANSACTION_CREATED = "transaction.created"
BALANCE_UPDATED = "balance.updated"
ACCOUNT_STATUS_CHANGED = "account.status_changed"
CUSTOMER_KYC_REQUIRED = "customer.kyc.required"
CUSTOMER_KYC_SESSION_CREATED = "customer.kyc.session_created"
CUSTOMER_KYC_APPROVED = "customer.kyc.approved"
CUSTOMER_KYC_DECLINED = "customer.kyc.declined"
CUSTOMER_KYC_REVIEW = "customer.kyc.review"
CUSTOMER_KYC_INFO_REQUESTED = "customer.kyc.info_requested"
CUSTOMER_KYC_INFO_SUBMITTED = "customer.kyc.info_submitted"
WEBHOOK_TEST = "webhook.test"


def default_subscriptions() -> list[str]:
    return [PAYMENT_SUCCESS, PAYMENT_FAILED, PAYMENT_CANCELLED, PAYMENT_EXPIRED, REFUND_SUCCESS, PAYMENT_REFUNDED, CHARGEBACK_CREATED, DISPUTE_CREATED]


def is_successful_payment(event_type: str) -> bool:
    return event_type in {PAYMENT_SUCCESS, PAYMENT_SUCCEEDED}


def is_failed_payment(event_type: str) -> bool:
    return event_type == PAYMENT_FAILED


def is_cancelled_payment(event_type: str) -> bool:
    return event_type in {PAYMENT_CANCELLED, PAYMENT_CANCELED, PAYMENT_EXPIRED}


def is_refund_completed(event_type: str) -> bool:
    return event_type in {REFUND_SUCCESS, REFUND_SUCCEEDED, PAYMENT_REFUNDED}


def is_dispute_or_chargeback(event_type: str) -> bool:
    return event_type in {CHARGEBACK_CREATED, DISPUTE_CREATED}
