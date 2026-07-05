'use strict';

const { Verifier } = require('../../packages/node-sdk/src');

const rawBody = '{"event_id":"PXEVT-TEST","event_type":"payment.succeeded"}';
const headers = {
  'X-PXC-Event-ID': 'PXEVT-TEST',
  'X-PXC-Timestamp': 'REPLACE_WITH_UNIX_TIMESTAMP',
  'X-PXC-Signature': 'REPLACE_WITH_SIGNATURE'
};

const payload = new Verifier('YOUR_WEBHOOK_SECRET').verify(rawBody, headers);
console.log(payload.event_type);
