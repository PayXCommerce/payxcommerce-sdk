'use strict';

const PAYMENT_SUCCESS = 'payment.success';
const PAYMENT_SUCCEEDED = 'payment.succeeded';
const PAYMENT_SUCCESS_LEGACY = 'payment.success';
const PAYMENT_SUCCEEDED_LEGACY = 'payment.succeeded';
const PAYMENT_REQUEST_CREATED = 'payment_request.created';
const PAYMENT_REQUEST_EXPIRED = 'payment_request.expired';
const PAYMENT_CREATED = 'payment.created';
const PAYMENT_PROCESSING = 'payment.processing';
const PAYMENT_FAILED = 'payment.failed';
const PAYMENT_CANCELLED = 'payment.cancelled';
const PAYMENT_CANCELED = 'payment.canceled';
const PAYMENT_EXPIRED = 'payment.expired';
const PAYMENT_REFUNDED = 'payment.refunded';
const REFUND_SUCCEEDED = 'refund.succeeded';
const REFUND_SUCCESS = 'refund.success';
const REFUND_SUCCESS_LEGACY = 'refund.success';
const REFUND_SUCCEEDED_LEGACY = 'refund.succeeded';
const REFUND_REQUESTED = 'refund.requested';
const REFUND_CREATED = 'refund.created';
const REFUND_APPROVED = 'refund.approved';
const REFUND_FAILED = 'refund.failed';
const REFUND_CANCELLED = 'refund.cancelled';
const REFUND_REJECTED = 'refund.rejected';
const REFUND_REVERSED = 'refund.reversed';
const CHARGEBACK_CREATED = 'chargeback.created';
const CHARGEBACK_UPDATED = 'chargeback.updated';
const CHARGEBACK_CLOSED = 'chargeback.closed';
const DISPUTE_CREATED = 'dispute.created';
const DISPUTE_OPENED = 'dispute.opened';
const DISPUTE_UPDATED = 'dispute.updated';
const DISPUTE_EVIDENCE_REQUIRED = 'dispute.evidence_required';
const DISPUTE_WON = 'dispute.won';
const DISPUTE_LOST = 'dispute.lost';
const DISPUTE_CLOSED = 'dispute.closed';
const TRANSACTION_CREATED = 'transaction.created';
const SETTLEMENT_CREATED = 'settlement.created';
const SETTLEMENT_APPROVED = 'settlement.approved';
const SETTLEMENT_PAID = 'settlement.paid';
const SETTLEMENT_FAILED = 'settlement.failed';
const BALANCE_UPDATED = 'balance.updated';
const ACCOUNT_STATUS_CHANGED = 'account.status_changed';
const CUSTOMER_KYC_REQUIRED = 'customer.kyc.required';
const CUSTOMER_KYC_SESSION_CREATED = 'customer.kyc.session_created';
const CUSTOMER_KYC_APPROVED = 'customer.kyc.approved';
const CUSTOMER_KYC_DECLINED = 'customer.kyc.declined';
const CUSTOMER_KYC_REVIEW = 'customer.kyc.review';
const CUSTOMER_KYC_INFO_REQUESTED = 'customer.kyc.info_requested';
const CUSTOMER_KYC_INFO_SUBMITTED = 'customer.kyc.info_submitted';
const WEBHOOK_TEST = 'webhook.test';

function defaultSubscriptions() { return [PAYMENT_SUCCESS, PAYMENT_FAILED, PAYMENT_CANCELLED, PAYMENT_EXPIRED, REFUND_SUCCESS, PAYMENT_REFUNDED, CHARGEBACK_CREATED, DISPUTE_CREATED]; }
function isSuccessfulPayment(eventType) { return [PAYMENT_SUCCESS, PAYMENT_SUCCEEDED].includes(eventType); }
function isFailedPayment(eventType) { return eventType === PAYMENT_FAILED; }
function isCancelledPayment(eventType) { return [PAYMENT_CANCELLED, PAYMENT_CANCELED, PAYMENT_EXPIRED].includes(eventType); }
function isRefundCompleted(eventType) { return [REFUND_SUCCESS, REFUND_SUCCEEDED, PAYMENT_REFUNDED].includes(eventType); }
function isDisputeOrChargeback(eventType) { return [CHARGEBACK_CREATED, DISPUTE_CREATED].includes(eventType); }

module.exports = {
  PAYMENT_SUCCESS, PAYMENT_SUCCEEDED, PAYMENT_SUCCESS_LEGACY, PAYMENT_SUCCEEDED_LEGACY,
  PAYMENT_REQUEST_CREATED, PAYMENT_REQUEST_EXPIRED, PAYMENT_CREATED, PAYMENT_PROCESSING,
  PAYMENT_FAILED, PAYMENT_CANCELLED, PAYMENT_CANCELED, PAYMENT_EXPIRED, PAYMENT_REFUNDED,
  REFUND_SUCCESS, REFUND_SUCCEEDED, REFUND_SUCCESS_LEGACY, REFUND_SUCCEEDED_LEGACY,
  REFUND_REQUESTED, REFUND_CREATED, REFUND_APPROVED, REFUND_FAILED, REFUND_CANCELLED,
  REFUND_REJECTED, REFUND_REVERSED,
  CHARGEBACK_CREATED, CHARGEBACK_UPDATED, CHARGEBACK_CLOSED,
  DISPUTE_CREATED, DISPUTE_OPENED, DISPUTE_UPDATED, DISPUTE_EVIDENCE_REQUIRED,
  DISPUTE_WON, DISPUTE_LOST, DISPUTE_CLOSED,
  TRANSACTION_CREATED, SETTLEMENT_CREATED, SETTLEMENT_APPROVED, SETTLEMENT_PAID,
  SETTLEMENT_FAILED, BALANCE_UPDATED, ACCOUNT_STATUS_CHANGED,
  CUSTOMER_KYC_REQUIRED, CUSTOMER_KYC_SESSION_CREATED, CUSTOMER_KYC_APPROVED,
  CUSTOMER_KYC_DECLINED, CUSTOMER_KYC_REVIEW, CUSTOMER_KYC_INFO_REQUESTED,
  CUSTOMER_KYC_INFO_SUBMITTED, WEBHOOK_TEST,
  defaultSubscriptions, isSuccessfulPayment, isFailedPayment, isCancelledPayment,
  isRefundCompleted, isDisputeOrChargeback
};
