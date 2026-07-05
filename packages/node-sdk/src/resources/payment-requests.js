'use strict';

class PaymentRequests {
  constructor(client) { this.client = client; }
  create(payload, idempotencyKey = null) {
    const headers = idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {};
    return this.client.request('POST', '/payment-requests', payload, headers);
  }
}
module.exports = { PaymentRequests };
