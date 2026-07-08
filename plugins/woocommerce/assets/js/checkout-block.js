(function () {
  const settings = window.wc && window.wc.wcSettings ? window.wc.wcSettings.getSetting('payxcommerce_data', {}) : {};
  const labelText = window.wp && window.wp.htmlEntities ? window.wp.htmlEntities.decodeEntities(settings.title || 'Pay securely') : (settings.title || 'Pay securely');
  const description = window.wp && window.wp.htmlEntities ? window.wp.htmlEntities.decodeEntities(settings.description || '') : (settings.description || '');
  const brandName = window.wp && window.wp.htmlEntities ? window.wp.htmlEntities.decodeEntities(settings.brandName || 'PayXCommerce') : (settings.brandName || 'PayXCommerce');
  const element = window.wp && window.wp.element ? window.wp.element.createElement : null;

  if (!window.wc || !window.wc.wcBlocksRegistry || !element) {
    return;
  }

  const icon = settings.iconUrl ? element('img', {
    src: settings.iconUrl,
    alt: brandName,
    style: { width: '24px', height: '24px', borderRadius: '5px', marginRight: '8px', verticalAlign: 'middle' }
  }) : null;
  const label = element('span', { style: { display: 'inline-flex', alignItems: 'center' } }, icon, element('span', null, labelText));
  const content = description ? element('p', null, description) : element('span', null, '');

  window.wc.wcBlocksRegistry.registerPaymentMethod({
    name: 'payxcommerce',
    label: label,
    ariaLabel: labelText,
    content: content,
    edit: content,
    canMakePayment: function () { return true; },
    supports: { features: settings.supports || ['products'] }
  });
}());
