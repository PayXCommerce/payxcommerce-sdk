'use strict';

const PAYMENT_SUCCEEDED = 'payment.succeeded';
const PAYMENT_SUCCESS_LEGACY = 'payment.success';
const PAYMENT_FAILED = 'payment.failed';
const PAYMENT_CANCELLED = 'payment.cancelled';
const PAYMENT_CANCELED = 'payment.canceled';
const PAYMENT_EXPIRED = 'payment.expired';
const PAYMENT_REFUNDED = 'payment.refunded';
const REFUND_SUCCEEDED = 'refund.succeeded';
const REFUND_SUCCESS_LEGACY = 'refund.success';
const CHARGEBACK_CREATED = 'chargeback.created';
const DISPUTE_CREATED = 'dispute.created';

function defaultSubscriptions() { return [PAYMENT_SUCCEEDED, PAYMENT_FAILED, PAYMENT_CANCELLED, PAYMENT_EXPIRED, REFUND_SUCCEEDED, PAYMENT_REFUNDED, CHARGEBACK_CREATED, DISPUTE_CREATED]; }
function isSuccessfulPayment(eventType) { return [PAYMENT_SUCCEEDED, PAYMENT_SUCCESS_LEGACY].includes(eventType); }
function isFailedPayment(eventType) { return eventType === PAYMENT_FAILED; }
function isCancelledPayment(eventType) { return [PAYMENT_CANCELLED, PAYMENT_CANCELED, PAYMENT_EXPIRED].includes(eventType); }
function isRefundCompleted(eventType) { return [REFUND_SUCCEEDED, REFUND_SUCCESS_LEGACY, PAYMENT_REFUNDED].includes(eventType); }
function isDisputeOrChargeback(eventType) { return [CHARGEBACK_CREATED, DISPUTE_CREATED].includes(eventType); }

module.exports = { PAYMENT_SUCCEEDED, PAYMENT_SUCCESS_LEGACY, PAYMENT_FAILED, PAYMENT_CANCELLED, PAYMENT_CANCELED, PAYMENT_EXPIRED, PAYMENT_REFUNDED, REFUND_SUCCEEDED, REFUND_SUCCESS_LEGACY, CHARGEBACK_CREATED, DISPUTE_CREATED, defaultSubscriptions, isSuccessfulPayment, isFailedPayment, isCancelledPayment, isRefundCompleted, isDisputeOrChargeback };
