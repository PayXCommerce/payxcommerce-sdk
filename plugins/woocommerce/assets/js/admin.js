(function ($) {
  'use strict';

  function fieldRow(id) {
    return $('#woocommerce_payxcommerce_' + id).closest('tr');
  }

  function toggleCredentialFields() {
    var authMethod = $('#woocommerce_payxcommerce_auth_method').val() || 'hmac';
    var hmacRows = fieldRow('public_key').add(fieldRow('secret_key'));
    var oauthRows = fieldRow('client_id').add(fieldRow('client_secret'));

    if (authMethod === 'bearer') {
      hmacRows.hide();
      oauthRows.show();
      return;
    }

    oauthRows.hide();
    hmacRows.show();
  }

  $(function () {
    var authSelect = $('#woocommerce_payxcommerce_auth_method');
    if (!authSelect.length) {
      return;
    }

    authSelect.on('change', toggleCredentialFields);
    toggleCredentialFields();
  });
})(jQuery);
