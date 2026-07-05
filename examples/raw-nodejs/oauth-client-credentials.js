'use strict';

const { DEFAULT_BASE_URL, oauthClientCredentials, printResponse } = require('./payxcommerce');

const clientId = 'YOUR_DEVELOPER_APP_CLIENT_ID';
const clientSecret = 'YOUR_DEVELOPER_APP_CLIENT_SECRET';

oauthClientCredentials(DEFAULT_BASE_URL, clientId, clientSecret).then(printResponse).catch((error) => {
  console.error(error.message);
  process.exit(1);
});
