'use strict';

const assert = require('assert');
const crypto = require('crypto');
const http = require('http');
const { HmacAuth, Verifier, eventTypes, redactor, errors } = require('../src');
const { HttpClient } = require('../src/http/client');

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
const unicodeBody = Buffer.from(`{\n  "event_id":"${eventId}","customer":"José / 東京","amount":1.2300\n}`, 'utf8');
const unicodeExpected = crypto.createHmac('sha256', 'webhook_secret').update(`${eventId}.`, 'utf8').update(unicodeBody).digest('hex');
same(unicodeExpected, Verifier.signature(eventId, unicodeBody, 'webhook_secret'), 'Webhook signature must preserve exact raw JSON bytes.');
const escapedBody = Buffer.from('{"event_id":"PXEVT-TEST","path":"https:\\/\\/example.test\\/a","message":"line\\nvalue","amount":1e2}', 'utf8');
const escapedSignature = Verifier.signature(eventId, escapedBody, 'webhook_secret');
const escapedDecoded = new Verifier('webhook_secret').verify(escapedBody, { 'X-PXC-Event-ID': eventId, 'X-PXC-Timestamp': String(Math.floor(Date.now() / 1000)), 'X-PXC-Signature': escapedSignature });
same(100, escapedDecoded.amount, 'Escaped JSON and exponent-form numbers should verify from their exact raw representation.');
same('secret=[redacted]', redactor.text('secret=abc123'), 'Redactor should hide secrets in text.');
same('[redacted]', redactor.context({ client_secret: 'abc123' }).client_secret, 'Redactor should hide secret context values.');

async function testValidationErrorFormatting() {
  const server = http.createServer((request, response) => {
    response.writeHead(422, { 'Content-Type': 'application/json' });
    response.end(JSON.stringify({
      message: 'The given data was invalid.',
      errors: { 'customer.country': ['The customer.country field is required.'] }
    }));
  });

  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  const port = server.address().port;

  try {
    await new HttpClient(5).send('POST', `http://127.0.0.1:${port}/api/v1/payment-requests`, {}, '{}');
    throw new Error('Validation response should fail.');
  } catch (error) {
    ok(error instanceof errors.ValidationError, 'Validation response should throw ValidationError.');
    ok(error.message.includes('customer.country: The customer.country field is required.'), 'Validation error should include field details.');
    same('The customer.country field is required.', error.errors['customer.country'][0], 'Validation error should expose structured errors.');
  } finally {
    await new Promise((resolve) => server.close(resolve));
  }
}

try {
  new Verifier('webhook_secret').verify(rawBody, { 'X-PXC-Event-ID': eventId, 'X-PXC-Timestamp': String(Math.floor(Date.now() / 1000)), 'X-PXC-Signature': 'invalid' });
  throw new Error('Invalid webhook signature should fail.');
} catch (error) {
  ok(error instanceof errors.WebhookVerificationError, 'Invalid webhook signature failed as expected.');
}

for (const invalidWebhook of [
  { body: '{invalid', timestamp: String(Math.floor(Date.now() / 1000)), message: 'Invalid JSON should fail.' },
  { body: rawBody, timestamp: String(Math.floor(Date.now() / 1000) - 1000), message: 'Stale timestamp should fail.' }
]) {
  try {
    new Verifier('webhook_secret').verify(invalidWebhook.body, {
      'X-PXC-Event-ID': eventId,
      'X-PXC-Timestamp': invalidWebhook.timestamp,
      'X-PXC-Signature': Verifier.signature(eventId, invalidWebhook.body, 'webhook_secret')
    });
    throw new Error(invalidWebhook.message);
  } catch (error) {
    ok(error instanceof errors.WebhookVerificationError, invalidWebhook.message);
  }
}

testValidationErrorFormatting()
  .then(() => console.log(`PayXCommerce Node SDK tests passed (${tests} assertions).`))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
