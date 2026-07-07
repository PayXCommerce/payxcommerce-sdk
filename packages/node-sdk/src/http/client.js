'use strict';

const http = require('http');
const https = require('https');
const { ApiError, AuthError, RateLimitError, ValidationError } = require('../errors');

class HttpClient {
  constructor(timeoutSeconds = 30) {
    this.timeoutSeconds = timeoutSeconds;
  }

  send(method, url, headers = {}, body = '') {
    const target = new URL(url);
    const transport = target.protocol === 'http:' ? http : https;
    const requestHeaders = Object.assign({}, headers);
    if (body) requestHeaders['Content-Length'] = Buffer.byteLength(body);

    return new Promise((resolve, reject) => {
      const request = transport.request({ method: method.toUpperCase(), hostname: target.hostname, port: target.port || undefined, path: target.pathname + target.search, headers: requestHeaders, timeout: this.timeoutSeconds * 1000 }, (response) => {
        let responseBody = '';
        response.setEncoding('utf8');
        response.on('data', (chunk) => { responseBody += chunk; });
        response.on('end', () => {
          const decoded = decode(responseBody);
          if ((response.statusCode || 0) >= 400) {
            reject(errorForStatus(response.statusCode || 0, decoded, responseBody));
          } else {
            resolve(decoded);
          }
        });
      });
      request.on('timeout', () => request.destroy(new ApiError('PayXCommerce API request timed out.')));
      request.on('error', reject);
      if (body) request.write(body);
      request.end();
    });
  }
}

function decode(responseBody) {
  if (!responseBody) return {};
  try {
    const decoded = JSON.parse(responseBody);
    return decoded && typeof decoded === 'object' && !Array.isArray(decoded) ? decoded : { data: decoded };
  } catch (error) {
    return { raw_body: responseBody };
  }
}

function errorForStatus(status, decoded, rawBody) {
  const errors = decoded.errors && typeof decoded.errors === 'object' && !Array.isArray(decoded.errors) ? decoded.errors : {};
  const message = messageWithErrors(String(decoded.message || decoded.error || 'PayXCommerce API error.'), errors);
  const code = decoded.error_code || null;
  if (['authentication_failed', 'signature_invalid', 'timestamp_expired', 'nonce_reused'].includes(code) || [401, 403].includes(status)) {
    return new AuthError(message, status, code, rawBody, errors);
  }
  if (['validation_failed', 'currency_not_supported', 'amount_out_of_range'].includes(code) || status === 422) {
    return new ValidationError(message, status, code, rawBody, errors);
  }
  if (code === 'rate_limit_exceeded' || status === 429) {
    return new RateLimitError(message, status, code, rawBody, errors);
  }
  return new ApiError(message, status, code, rawBody, errors);
}

function messageWithErrors(message, errors) {
  const details = [];
  Object.entries(errors).forEach(([field, fieldErrors]) => {
    [].concat(fieldErrors || []).forEach((fieldError) => {
      const text = String(fieldError || '').trim();
      if (text) details.push(`${field}: ${text}`);
    });
  });

  if (!details.length) return message;

  return `${message.replace(/\.$/, '')}. ${details.slice(0, 8).join(' ')}`;
}

module.exports = { HttpClient };
