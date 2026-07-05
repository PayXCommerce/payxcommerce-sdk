'use strict';

const { DEFAULT_BASE_URL, endpoint, hmacHeaders, jsonBody, jsonRequest, printResponse } = require('./payxcommerce');

const publicKey = 'YOUR_PUBLIC_KEY';
const secretKey = 'YOUR_SECRET_KEY';
const payload = { transaction_reference: 'PXTRX-YYYYMMDD-XXXXXX', amount: 25.00, reason: 'Customer requested partial refund' };
const headers = hmacHeaders(publicKey, secretKey, jsonBody(payload), `raw-nodejs-refund-${Math.floor(Date.now() / 1000)}`);

jsonRequest('POST', endpoint(DEFAULT_BASE_URL, '/refunds'), headers, payload).then(printResponse).catch((error) => {
  console.error(error.message);
  process.exit(1);
});
