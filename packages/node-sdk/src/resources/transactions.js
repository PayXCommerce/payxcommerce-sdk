'use strict';
class Transactions { constructor(client) { this.client = client; } lookup(transactionReference) { return this.client.request('GET', `/transactions/${encodeURIComponent(transactionReference)}`); } }
module.exports = { Transactions };
