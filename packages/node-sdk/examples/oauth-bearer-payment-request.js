'use strict';

const { BearerTokenAuth, Client, ClientCredentials, Config, eventTypes } = require('../src');

(async () => {
  const credentials = new ClientCredentials('YOUR_DEVELOPER_APP_CLIENT_ID', 'YOUR_DEVELOPER_APP_CLIENT_SECRET');
  const accessToken = await credentials.accessToken('payment_requests.write transactions.read balances.read refunds.write');
  const client = new Client(new Config({ auth: new BearerTokenAuth(accessToken) }));
  const response = await client.paymentRequests().create({
    amount: 125.50,
    currency: 'USD',
    purpose: 'SDK bearer example order',
    customer: { name: 'Jane Customer', email: 'customer@example.com', country: 'United States' },
    webhook_url: 'https://example.com/payxcommerce/webhook/order-1001',
    ipn_events: eventTypes.defaultSubscriptions(),
    metadata: { source: 'node-sdk-bearer-example' },
    is_test: true
  });
  console.log(response.checkout_url);
})();
