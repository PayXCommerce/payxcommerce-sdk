(function () {
  const settings = window.wc && window.wc.wcSettings ? window.wc.wcSettings.getSetting('payxcommerce_data', {}) : {};
  const label = window.wp && window.wp.htmlEntities ? window.wp.htmlEntities.decodeEntities(settings.title || 'Pay securely') : (settings.title || 'Pay securely');
  const description = window.wp && window.wp.htmlEntities ? window.wp.htmlEntities.decodeEntities(settings.description || '') : (settings.description || '');
  const element = window.wp && window.wp.element ? window.wp.element.createElement : null;

  if (!window.wc || !window.wc.wcBlocksRegistry || !element) {
    return;
  }

  window.wc.wcBlocksRegistry.registerPaymentMethod({
    name: 'payxcommerce',
    label: label,
    ariaLabel: label,
    content: element('p', null, description),
    edit: element('p', null, description),
    canMakePayment: function () { return true; },
    supports: { features: settings.supports || ['products'] }
  });
}());
