'use strict';

const { DEFAULT_BASE_URL, defaultIpnEvents, endpoint, hmacHeaders, jsonBody, jsonRequest, printResponse } = require('./payxcommerce');

const publicKey = 'YOUR_PUBLIC_KEY';
const secretKey = 'YOUR_SECRET_KEY';

const payload = {
  amount: 125.50,
  currency: 'USD',
  purpose: 'Invoice INV-1001',
  customer: { name: 'Jane Customer', email: 'customer@example.com', mobile: '+15551234567', country: 'United States' },
  merchant_reference: 'CRM-1001',
  merchant_order_id: 'ORDER-1001',
  success_url: 'https://example.com/payment/success',
  failed_url: 'https://example.com/payment/failed',
  cancel_url: 'https://example.com/payment/cancel',
  webhook_url: 'https://example.com/payxcommerce/webhook/order-1001',
  ipn_events: defaultIpnEvents(),
  metadata: { source: 'raw-nodejs-example' },
  is_test: true
};

const headers = hmacHeaders(publicKey, secretKey, jsonBody(payload), `raw-nodejs-order-1001-${Math.floor(Date.now() / 1000)}`);
jsonRequest('POST', endpoint(DEFAULT_BASE_URL, '/payment-requests'), headers, payload).then(printResponse).catch((error) => {
  console.error(error.message);
  process.exit(1);
});
