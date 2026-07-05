'use strict';

class Config {
  constructor({ baseUrl = 'https://payxcommerce.com/api/v1', auth = null, timeoutSeconds = 30, debug = false, apiHeaderPrefix = 'PXC' } = {}) {
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
