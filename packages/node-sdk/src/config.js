'use strict';

class Config {
  constructor({ baseUrl = 'https://payxcommerce.com/api/v1', auth = null, timeoutSeconds = 30, debug = false, apiHeaderPrefix = 'PXC' } = {}) {
    const parsed = new URL(baseUrl);
    const local = ['localhost', '127.0.0.1', '::1'].includes(parsed.hostname) || parsed.hostname.endsWith('.test');
    if (parsed.protocol !== 'https:' && !(parsed.protocol === 'http:' && local)) {
      throw new TypeError('PayXCommerce API base URL must use HTTPS (HTTP is allowed only for localhost or .test development hosts).');
    }
    this.baseUrl = baseUrl;
    this.auth = auth;
    this.timeoutSeconds = timeoutSeconds;
    this.debug = debug;
    this.apiHeaderPrefix = apiHeaderPrefix;
  }

  endpoint(path) {
    return `${this.baseUrl.replace(/\/+$/, '')}/${String(path).replace(/^\/+/, '')}`;
  }

  apiHeader(name) {
    return `X-${String(this.apiHeaderPrefix).toUpperCase()}-${name}`;
  }
}

module.exports = { Config };
