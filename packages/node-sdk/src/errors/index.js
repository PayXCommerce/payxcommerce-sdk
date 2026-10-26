'use strict';
const { safeHeaders, retryAfter } = require('../http/response-metadata');

class ApiError extends Error {
  constructor(message, statusCode = null, payxErrorCode = null, rawResponseBody = null, errors = {}, responseHeaders = {}) {
    super(message);
    this.name = this.constructor.name;
    this.statusCode = statusCode;
    this.payxErrorCode = payxErrorCode;
    this.rawResponseBody = rawResponseBody;
    this.errors = errors || {};
    this.responseHeaders = safeHeaders(responseHeaders);
    this.retryAfterSeconds = retryAfter(this.responseHeaders['retry-after']);
  }
}
class AuthError extends ApiError {}
class ValidationError extends ApiError {}
class RateLimitError extends ApiError {}
class WebhookVerificationError extends Error {}

module.exports = { ApiError, AuthError, ValidationError, RateLimitError, WebhookVerificationError };
