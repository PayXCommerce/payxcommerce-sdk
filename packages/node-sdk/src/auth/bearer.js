'use strict';

const idempotency = require('../util/idempotency');

class BearerTokenAuth {
  constructor(accessToken, autoIdempotency = true) {
    this.accessToken = accessToken;
    this.autoIdempotency = autoIdempotency;
  }

  headers(method, path, body, headers) {
    headers.Authorization = `Bearer ${this.accessToken}`;
    if (this.autoIdempotency && ['POST', 'PUT', 'PATCH'].includes(String(method).toUpperCase()) && !headers['Idempotency-Key']) {
      headers['Idempotency-Key'] = idempotency.generate();
    }
    return headers;
  }
}

module.exports = { BearerTokenAuth };
