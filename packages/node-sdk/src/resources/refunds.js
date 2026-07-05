'use strict';
class Refunds { constructor(client) { this.client = client; } create(payload, idempotencyKey = null) { const headers = idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {}; return this.client.request('POST', '/refunds', payload, headers); } }
module.exports = { Refunds };
