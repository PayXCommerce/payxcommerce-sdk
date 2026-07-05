'use strict';

const crypto = require('crypto');

function generate(bytes = 16) {
  return crypto.randomBytes(bytes).toString('hex');
}

module.exports = { generate };
