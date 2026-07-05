'use strict';

const { DEFAULT_BASE_URL, endpoint, hmacHeaders, jsonRequest, printResponse } = require('./payxcommerce');

const publicKey = 'YOUR_PUBLIC_KEY';
const secretKey = 'YOUR_SECRET_KEY';
const transactionReference = 'PXTRX-YYYYMMDD-XXXXXX';

jsonRequest('GET', endpoint(DEFAULT_BASE_URL, `/transactions/${encodeURIComponent(transactionReference)}`), hmacHeaders(publicKey, secretKey, '')).then(printResponse).catch((error) => {
  console.error(error.message);
  process.exit(1);
});
