'use strict';

const { Client, Config, HmacAuth, eventTypes } = require('../../packages/node-sdk/src');


function printSdkError(error) {
  console.error(`PayXCommerce API error: ${error.message}`);
  if (error.statusCode) console.error(`HTTP status: ${error.statusCode}`);
  if (error.payxErrorCode) console.error(`Error code: ${error.payxErrorCode}`);
  if (error.errors && Object.keys(error.errors).length) {
    console.error('Validation details:');
    Object.entries(error.errors).forEach(([field, messages]) => {
      [].concat(messages || []).forEach((message) => console.error(` - ${field}: ${message}`));
    });
  }
  process.exitCode = 1;
}

const client = new Client(new Config({ auth: new HmacAuth('YOUR_PUBLIC_KEY', 'YOUR_SECRET_KEY') }));

client.paymentRequests().create({
  amount: 125.50,
  currency: 'USD',
  purpose: 'SDK example order',
  customer: { name: 'Jane Customer', email: 'customer@example.com', country: 'United States' },
  webhook_url: 'https://example.com/payxcommerce/webhook/order-1001',
  ipn_events: eventTypes.defaultSubscriptions(),
  metadata: { source: 'sdk-nodejs-example' },
  is_test: true
}).then((response) => console.log(response.checkout_url)).catch(printSdkError);
