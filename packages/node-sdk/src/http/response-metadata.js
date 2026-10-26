'use strict';

const MAX_RETRY_SECONDS = 300;
const NAMES = ['retry-after', 'x-ratelimit-limit', 'x-ratelimit-remaining', 'x-ratelimit-reset', 'x-request-id', 'x-error-group-id'];

function retryAfter(value, now = Math.floor(Date.now() / 1000)) {
  if (typeof value !== 'string' || value.length > 128) return null;
  value = value.trim();
  if (/^\d+$/.test(value)) return Math.min(MAX_RETRY_SECONDS, Number(value));
  if (!/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun), \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/.test(value)) return null;
  const date = Date.parse(value);
  if (!Number.isFinite(date) || new Date(date).toUTCString() !== value) return null;
  const seconds = Math.ceil(date / 1000 - now);
  return seconds < 0 ? null : Math.min(MAX_RETRY_SECONDS, seconds);
}

function safeHeaders(headers = {}) {
  const safe = {}, seen = new Set();
  for (const [rawName, rawValue] of Object.entries(headers || {})) {
    const name = rawName.toLowerCase().trim();
    if (!NAMES.includes(name)) continue;
    if (seen.has(name)) { delete safe[name]; continue; }
    seen.add(name);
    if (typeof rawValue !== 'string' || rawValue.length > 128 || /[\x00-\x1f\x7f]/.test(rawValue)) continue;
    const value = rawValue.trim();
    if (name === 'retry-after' ? retryAfter(value) !== null
      : name.startsWith('x-ratelimit-') ? /^\d{1,12}$/.test(value) : /^[A-Za-z0-9_.:-]{1,128}$/.test(value)) safe[name] = value;
  }
  return safe;
}

module.exports = { MAX_RETRY_SECONDS, safeHeaders, retryAfter };
