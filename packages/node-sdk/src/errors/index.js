'use strict';

class ApiError extends Error {
  constructor(message, statusCode = null, payxErrorCode = null, rawResponseBody = null) {
    super(message);
    this.name = this.constructor.name;
    this.statusCode = statusCode;
    this.payxErrorCode = payxErrorCode;
    this.rawResponseBody = rawResponseBody;
  }
}
class AuthError extends ApiError {}
class ValidationError extends ApiError {}
class RateLimitError extends ApiError {}
class WebhookVerificationError extends Error {}

module.exports = { ApiError, AuthError, ValidationError, RateLimitError, WebhookVerificationError };
