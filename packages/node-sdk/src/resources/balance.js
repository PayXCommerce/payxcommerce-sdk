'use strict';
class Balance { constructor(client) { this.client = client; } get() { return this.client.request('GET', '/balance'); } }
module.exports = { Balance };
