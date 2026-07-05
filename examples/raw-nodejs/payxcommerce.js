'use strict';

const crypto = require('crypto');
const http = require('http');
const https = require('https');

const DEFAULT_BASE_URL = 'https://payxcommerce.com/api/v1';
const TOKEN_SCOPE = 'payment_requests.write transactions.read balances.read refunds.write';

function defaultIpnEvents() {
  return [
    'payment.succeeded',
    'payment.failed',
    'payment.cancelled',
    'payment.expired',
    'refund.succeeded',
    'payment.refunded',
    'chargeback.created',
    'dispute.created'
  ];
}

function jsonBody(payload) {
  return payload == null ? '' : JSON.stringify(payload);
}

function hmacHeaders(publicKey, secretKey, body, idempotencyKey) {
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const nonce = crypto.randomBytes(16).toString('hex');
  const signature = crypto.createHmac('sha256', secretKey).update(`${timestamp}.${nonce}.${body}`).digest('hex');
  const headers = {
    'X-PXC-Public-Key': publicKey,
    'X-PXC-Timestamp': timestamp,
    'X-PXC-Nonce': nonce,
    'X-PXC-Signature': signature
  };
  if (idempotencyKey) {
    headers['Idempotency-Key'] = idempotencyKey;
  }
  return headers;
}

function jsonRequest(method, url, headers = {}, payload = null) {
  const body = jsonBody(payload);
  const target = new URL(url);
  const transport = target.protocol === 'http:' ? http : https;
  const requestHeaders = Object.assign({ Accept: 'application/json', 'Content-Type': 'application/json' }, headers);
  if (body) {
    requestHeaders['Content-Length'] = Buffer.byteLength(body);
  }

  return new Promise((resolve, reject) => {
    const request = transport.request({
      method: method.toUpperCase(),
      hostname: target.hostname,
      port: target.port || undefined,
      path: target.pathname + target.search,
      headers: requestHeaders,
      timeout: 30000
    }, (response) => {
      let responseBody = '';
      response.setEncoding('utf8');
      response.on('data', (chunk) => { responseBody += chunk; });
      response.on('end', () => resolve({ status: response.statusCode, body: parseJson(responseBody) }));
    });

    request.on('timeout', () => request.destroy(new Error('PayXCommerce request timed out.')));
    request.on('error', reject);
    if (body) {
      request.write(body);
    }
    request.end();
  });
}

function parseJson(value) {
  if (!value) {
    return {};
  }
  try {
    return JSON.parse(value);
  } catch (error) {
    return value;
  }
}

function endpoint(baseUrl, path) {
  return `${baseUrl.replace(/\/+$/, '')}/${path.replace(/^\/+/, '')}`;
}

async function oauthClientCredentials(baseUrl, clientId, clientSecret, scope = TOKEN_SCOPE) {
  return jsonRequest('POST', endpoint(baseUrl, '/oauth/token'), {}, {
    grant_type: 'client_credentials',
    client_id: clientId,
    client_secret: clientSecret,
    scope
  });
}

function verifyWebhook(rawBody, headers, webhookSecret, toleranceSeconds = 300) {
  const eventId = header(headers, 'X-PXC-Event-ID');
  const timestamp = header(headers, 'X-PXC-Timestamp');
  const signature = header(headers, 'X-PXC-Signature');
  if (!eventId || !timestamp || !signature) {
    throw new Error('Missing PayXCommerce webhook signature headers.');
  }
  if (!/^\d+$/.test(timestamp) || Math.abs(Math.floor(Date.now() / 1000) - Number(timestamp)) > toleranceSeconds) {
    throw new Error('Invalid or expired PayXCommerce webhook timestamp.');
  }

  const payload = JSON.parse(rawBody);
  const canonicalBody = JSON.stringify(payload);
  const expected = crypto.createHmac('sha256', webhookSecret).update(`${eventId}.${canonicalBody}`).digest('hex');
  if (expected.length !== signature.length) {
    throw new Error('Invalid PayXCommerce webhook signature.');
  }
  if (!crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signature))) {
    throw new Error('Invalid PayXCommerce webhook signature.');
  }
  return payload;
}

function header(headers, name) {
  const normalized = name.toLowerCase();
  for (const [key, value] of Object.entries(headers)) {
    if (key.toLowerCase() === normalized) {
      return String(value || '');
    }
  }
  return '';
}

function printResponse(response) {
  console.log(`HTTP Status: ${response.status}`);
  console.log(JSON.stringify(response.body, null, 2));
}

function redact(message) {
  return String(message).replace(/(secret|token|signature|authorization|password|key|client_secret|secret_key|webhook_secret)([^\s:=]*)?([:=]\s*)?([A-Za-z0-9_\-.!@$%^&*+/=]+)/gi, '$1$2$3[redacted]');
}

module.exports = {
  DEFAULT_BASE_URL,
  TOKEN_SCOPE,
  defaultIpnEvents,
  endpoint,
  hmacHeaders,
  jsonBody,
  jsonRequest,
  oauthClientCredentials,
  printResponse,
  redact,
  verifyWebhook
};
