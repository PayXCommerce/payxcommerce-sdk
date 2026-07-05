'use strict';

const { Client, Config, HmacAuth, eventTypes } = require('../../packages/node-sdk/src');

const client = new Client(new Config({ auth: new HmacAuth('YOUR_PUBLIC_KEY', 'YOUR_SECRET_KEY') }));

client.paymentRequests().create({
  amount: 125.50,
  currency: 'USD',
  purpose: 'SDK example order',
  customer: { name: 'Jane Customer', email: 'customer@example.com', country: 'United States' },
  ipn_events: eventTypes.defaultSubscriptions(),
  metadata: { source: 'sdk-nodejs-example' },
  is_test: true
}).then((response) => console.log(response.checkout_url));
