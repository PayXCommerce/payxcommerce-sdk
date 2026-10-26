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
        const incomplete = () => reject(new ApiError('PayXCommerce API response was interrupted; the operation outcome may be unknown.'));
        response.on('aborted', incomplete);
        response.on('error', incomplete);
        response.on('data', (chunk) => { responseBody += chunk; });
        response.on('end', () => {
          const decoded = decode(responseBody);
          if ((response.statusCode || 0) >= 300) {
            reject(errorForStatus(response.statusCode || 0, decoded, responseBody, finalHeaders(response.rawHeaders)));
          } else {
            resolve(decoded);
          }
        });
      });
      request.on('timeout', () => request.destroy(new ApiError('PayXCommerce API request timed out.')));
      request.on('error', () => reject(new ApiError('PayXCommerce API transport failed; the operation outcome may be unknown.')));
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

function finalHeaders(raw = []) {
  const headers = {};
  for (let i = 0; i < raw.length; i += 2) {
    const name = raw[i].toLowerCase();
    if (!['retry-after','x-ratelimit-limit','x-ratelimit-remaining','x-ratelimit-reset','x-request-id','x-error-group-id'].includes(name)) continue;
    headers[name] = Object.prototype.hasOwnProperty.call(headers, name) ? null : raw[i + 1];
  }
  return headers;
}

function errorForStatus(status, decoded, rawBody, headers = {}) {
  const errors = decoded.errors && typeof decoded.errors === 'object' && !Array.isArray(decoded.errors) ? decoded.errors : {};
  const text = typeof decoded.message === 'string' ? decoded.message : typeof decoded.error === 'string' ? decoded.error : 'PayXCommerce API error.';
  const message = status === 429 ? 'Payment service is busy. Please retry after the indicated delay.'
    : status >= 500 ? 'Payment service is unavailable; the operation outcome may be unknown.'
      : status < 400 ? 'PayXCommerce API redirect was not followed.' : messageWithErrors(text, errors);
  const code = typeof decoded.error_code === 'string' && /^[a-z][a-z0-9_]{0,63}$/.test(decoded.error_code) ? decoded.error_code : null;
  if (status >= 500) return new ApiError(message, status, code, rawBody, errors, headers);
  if (status === 429) return new RateLimitError(message, status, code, rawBody, errors, headers);
  if (['authentication_failed', 'signature_invalid', 'timestamp_expired', 'nonce_reused'].includes(code) || [401, 403].includes(status)) {
    return new AuthError(message, status, code, rawBody, errors, headers);
  }
  if (['validation_failed', 'currency_not_supported', 'amount_out_of_range'].includes(code) || status === 422) {
    return new ValidationError(message, status, code, rawBody, errors, headers);
  }
  if (code === 'rate_limit_exceeded' || status === 429) {
    return new RateLimitError(message, status, code, rawBody, errors, headers);
  }
  return new ApiError(message, status, code, rawBody, errors, headers);
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
