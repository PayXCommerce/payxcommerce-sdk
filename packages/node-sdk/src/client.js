'use strict';

const { Config } = require('./config');
const { HttpClient } = require('./http/client');
const { PaymentRequests } = require('./resources/payment-requests');
const { Balance } = require('./resources/balance');
const { Transactions } = require('./resources/transactions');
const { Refunds } = require('./resources/refunds');

class Client {
  constructor(config = new Config(), httpClient = null) {
    this.config = config;
    this.http = httpClient || new HttpClient(config.timeoutSeconds);
  }

  request(method, path, payload = null, headers = {}, auth = null) {
    const body = payload == null ? '' : JSON.stringify(payload);
    let requestHeaders = Object.assign({ Accept: 'application/json', 'Content-Type': 'application/json' }, headers);
    const selectedAuth = auth || this.config.auth;
    if (selectedAuth) {
      requestHeaders = selectedAuth.headers(method, path, body, requestHeaders, this.config);
    }
    return this.http.send(method, this.config.endpoint(path), requestHeaders, body);
  }

  paymentRequests() { return new PaymentRequests(this); }
  balance() { return new Balance(this); }
  transactions() { return new Transactions(this); }
  refunds() { return new Refunds(this); }
}

module.exports = { Client };
