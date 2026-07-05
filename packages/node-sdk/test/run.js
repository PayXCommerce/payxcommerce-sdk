'use strict';

const assert = require('assert');
const crypto = require('crypto');
const { HmacAuth, Verifier, eventTypes, redactor, errors } = require('../src');

let tests = 0;
function same(expected, actual, message) { tests += 1; assert.strictEqual(actual, expected, message); }
function ok(condition, message) { tests += 1; assert.ok(condition, message); }

const body = '{"amount":100,"currency":"USD"}';
const expectedSignature = crypto.createHmac('sha256', 'secret123').update(`1710000000.nonce123.${body}`).digest('hex');
same(expectedSignature, HmacAuth.sign('1710000000', 'nonce123', body, 'secret123'), 'HMAC signature should match expected hash.');

const eventId = 'PXEVT-TEST';
const rawBody = JSON.stringify({ event_id: eventId, event_type: eventTypes.PAYMENT_SUCCEEDED, amount: 100 });
const signature = Verifier.signature(eventId, rawBody, 'webhook_secret');
const decoded = new Verifier('webhook_secret').verify(rawBody, { 'X-PXC-Event-ID': eventId, 'X-PXC-Timestamp': String(Math.floor(Date.now() / 1000)), 'X-PXC-Signature': signature });
same(eventTypes.PAYMENT_SUCCEEDED, decoded.event_type, 'Webhook verifier should return decoded payload.');
ok(eventTypes.isSuccessfulPayment('payment.success'), 'Legacy successful payment event should be recognized.');
same('secret=[redacted]', redactor.text('secret=abc123'), 'Redactor should hide secrets in text.');
same('[redacted]', redactor.context({ client_secret: 'abc123' }).client_secret, 'Redactor should hide secret context values.');

try {
  new Verifier('webhook_secret').verify(rawBody, { 'X-PXC-Event-ID': eventId, 'X-PXC-Timestamp': String(Math.floor(Date.now() / 1000)), 'X-PXC-Signature': 'invalid' });
  throw new Error('Invalid webhook signature should fail.');
} catch (error) {
  ok(error instanceof errors.WebhookVerificationError, 'Invalid webhook signature failed as expected.');
}

console.log(`PayXCommerce Node SDK tests passed (${tests} assertions).`);
