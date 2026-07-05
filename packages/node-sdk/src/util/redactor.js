'use strict';

function text(message) {
  return String(message).replace(/(secret|token|signature|authorization|password|key|client_secret|secret_key|webhook_secret)([^\s:=]*)?([:=]\s*)?([A-Za-z0-9_\-.!@$%^&*+/=]+)/gi, '$1$2$3[redacted]');
}

function context(values) {
  const redacted = {};
  for (const [key, value] of Object.entries(values || {})) {
    if (/secret|token|signature|authorization|password|key/i.test(key)) {
      redacted[key] = '[redacted]';
    } else if (typeof value === 'string') {
      redacted[key] = text(value);
    } else if (value && typeof value === 'object' && !Array.isArray(value)) {
      redacted[key] = context(value);
    } else {
      redacted[key] = value;
    }
  }
  return redacted;
}

module.exports = { text, context };
