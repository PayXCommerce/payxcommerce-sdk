'use strict';

const crypto = require('crypto');

function generate(prefix = 'pxc') {
  return `${prefix}_${crypto.randomBytes(16).toString('hex')}`;
}

module.exports = { generate };
