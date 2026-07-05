'use strict';

const crypto = require('crypto');
const nonce = require('../util/nonce');
const idempotency = require('../util/idempotency');

class HmacAuth {
  constructor(publicKey, secretKey, autoIdempotency = true) {
    this.publicKey = publicKey;
    this.secretKey = secretKey;
    this.autoIdempotency = autoIdempotency;
  }

  headers(method, path, body, headers, config) {
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const nonceValue = nonce.generate();
    headers[config.apiHeader('Public-Key')] = this.publicKey;
    headers[config.apiHeader('Timestamp')] = timestamp;
    headers[config.apiHeader('Nonce')] = nonceValue;
    headers[config.apiHeader('Signature')] = HmacAuth.sign(timestamp, nonceValue, body, this.secretKey);
    if (this.autoIdempotency && ['POST', 'PUT', 'PATCH'].includes(String(method).toUpperCase()) && !headers['Idempotency-Key']) {
      headers['Idempotency-Key'] = idempotency.generate();
    }
    return headers;
  }

  static sign(timestamp, nonceValue, body, secretKey) {
    return crypto.createHmac('sha256', secretKey).update(`${timestamp}.${nonceValue}.${body}`).digest('hex');
  }
}

module.exports = { HmacAuth };
