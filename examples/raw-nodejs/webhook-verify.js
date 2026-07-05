'use strict';

const { verifyWebhook } = require('./payxcommerce');

const webhookSecret = 'YOUR_WEBHOOK_SECRET';
let rawBody = '';

process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => { rawBody += chunk; });
process.stdin.on('end', () => {
  try {
    const payload = verifyWebhook(rawBody, {
      'X-PXC-Event-ID': process.env.HTTP_X_PXC_EVENT_ID || '',
      'X-PXC-Timestamp': process.env.HTTP_X_PXC_TIMESTAMP || '',
      'X-PXC-Signature': process.env.HTTP_X_PXC_SIGNATURE || ''
    }, webhookSecret);

    // Store event_id and skip duplicates before updating local order status.
    // Map event_type to your local order lifecycle.
    console.log('PayXCommerce webhook verified:', payload.event_type || 'unknown');
  } catch (error) {
    console.error('Invalid webhook:', error.message);
    process.exit(1);
  }
});
