'use strict';

const crypto = require('crypto');
const { WebhookVerificationError } = require('../errors');

class Verifier {
  constructor(webhookSecret, toleranceSeconds = 300) {
    this.webhookSecret = webhookSecret;
    this.toleranceSeconds = toleranceSeconds;
  }

  verify(rawBody, headers) {
    const eventId = this._header(headers, 'X-PXC-Event-ID');
    const timestamp = this._header(headers, 'X-PXC-Timestamp');
    const receivedSignature = this._header(headers, 'X-PXC-Signature');
    if (!eventId || !timestamp || !receivedSignature) throw new WebhookVerificationError('Missing PayXCommerce webhook signature headers.');
    if (!/^\d+$/.test(timestamp)) throw new WebhookVerificationError('Invalid PayXCommerce webhook timestamp.');
    if (Math.abs(Math.floor(Date.now() / 1000) - Number(timestamp)) > this.toleranceSeconds) throw new WebhookVerificationError('PayXCommerce webhook timestamp is outside the allowed tolerance.');
    let payload;
    try { payload = JSON.parse(rawBody); } catch (error) { throw new WebhookVerificationError('PayXCommerce webhook body is not valid JSON.'); }
    const expected = Verifier.signature(eventId, rawBody, this.webhookSecret);
    if (expected.length !== receivedSignature.length || !crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(receivedSignature))) {
      throw new WebhookVerificationError('Invalid PayXCommerce webhook signature.');
    }
    return payload;
  }

  static signature(eventId, rawBody, webhookSecret) {
    let canonicalBody = rawBody;
    try { canonicalBody = JSON.stringify(JSON.parse(rawBody)); } catch (error) {}
    return crypto.createHmac('sha256', webhookSecret).update(`${eventId}.${canonicalBody}`).digest('hex');
  }

  _header(headers, name) {
    const normalized = name.toLowerCase();
    for (const [key, value] of Object.entries(headers || {})) {
      if (key.toLowerCase() === normalized) return String(value || '');
    }
    const serverName = 'HTTP_' + name.toUpperCase().replace(/-/g, '_');
    return String((headers || {})[serverName] || '');
  }
}

module.exports = { Verifier };
