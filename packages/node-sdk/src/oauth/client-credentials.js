'use strict';

const { Client } = require('../client');
const { Config } = require('../config');

class ClientCredentials {
  constructor(clientId, clientSecret, config = new Config(), httpClient = null) {
    this.clientId = clientId;
    this.clientSecret = clientSecret;
    this.config = config;
    this.httpClient = httpClient;
    this._accessToken = null;
    this._expiresAt = null;
  }

  async token(scope = null) {
    const payload = { grant_type: 'client_credentials', client_id: this.clientId, client_secret: this.clientSecret };
    if (scope) payload.scope = scope;
    const response = await new Client(new Config({ baseUrl: this.config.baseUrl, auth: null, timeoutSeconds: this.config.timeoutSeconds, debug: this.config.debug, apiHeaderPrefix: this.config.apiHeaderPrefix }), this.httpClient).request('POST', '/oauth/token', payload);
    this._accessToken = String(response.access_token || '');
    this._expiresAt = Math.floor(Date.now() / 1000) + Number(response.expires_in || 3600);
    return response;
  }

  async accessToken(scope = null) {
    if (!this._accessToken || !this._expiresAt || this._expiresAt <= Math.floor(Date.now() / 1000) + 60) {
      await this.token(scope);
    }
    return this._accessToken;
  }

  revoke(token) {
    return new Client(new Config({ baseUrl: this.config.baseUrl, auth: null, timeoutSeconds: this.config.timeoutSeconds, debug: this.config.debug, apiHeaderPrefix: this.config.apiHeaderPrefix }), this.httpClient).request('POST', '/oauth/revoke', { client_id: this.clientId, client_secret: this.clientSecret, token });
  }
}

module.exports = { ClientCredentials };
