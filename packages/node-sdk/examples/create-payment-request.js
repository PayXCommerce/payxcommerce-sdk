'use strict';

const { Client, Config, HmacAuth, eventTypes } = require('../src');

const client = new Client(new Config({ auth: new HmacAuth('YOUR_PUBLIC_KEY', 'YOUR_SECRET_KEY') }));

client.paymentRequests().create({
  amount: 125.50,
  currency: 'USD',
  purpose: 'SDK example order',
  customer: { name: 'Jane Customer', email: 'customer@example.com', country: 'United States' },
  merchant_reference: 'NODESDK-1001',
  merchant_order_id: 'ORDER-1001',
  success_url: 'https://example.com/payment/success',
  failed_url: 'https://example.com/payment/failed',
  cancel_url: 'https://example.com/payment/cancel',
  webhook_url: 'https://example.com/payxcommerce/webhook',
  ipn_events: eventTypes.defaultSubscriptions(),
  metadata: { source: 'node-sdk-example' },
  is_test: true
}).then((response) => console.log(response.checkout_url));
