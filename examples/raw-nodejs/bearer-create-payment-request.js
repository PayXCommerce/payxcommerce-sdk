'use strict';

const { DEFAULT_BASE_URL, defaultIpnEvents, endpoint, jsonRequest, printResponse } = require('./payxcommerce');

const accessToken = 'YOUR_DEVELOPER_APP_ACCESS_TOKEN';
const payload = {
  amount: 125.50,
  currency: 'USD',
  purpose: 'Invoice INV-1002',
  customer: { name: 'Jane Customer', email: 'customer@example.com', country: 'United States' },
  merchant_reference: 'CRM-1002',
  merchant_order_id: 'ORDER-1002',
  success_url: 'https://example.com/payment/success',
  failed_url: 'https://example.com/payment/failed',
  cancel_url: 'https://example.com/payment/cancel',
  webhook_url: 'https://example.com/payxcommerce/webhook',
  ipn_events: defaultIpnEvents(),
  metadata: { source: 'raw-nodejs-bearer-example' },
  is_test: true
};
const headers = { Authorization: `Bearer ${accessToken}`, 'Idempotency-Key': `raw-nodejs-bearer-order-1002-${Math.floor(Date.now() / 1000)}` };

jsonRequest('POST', endpoint(DEFAULT_BASE_URL, '/payment-requests'), headers, payload).then(printResponse).catch((error) => {
  console.error(error.message);
  process.exit(1);
});
