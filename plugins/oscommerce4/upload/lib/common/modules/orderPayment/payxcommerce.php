<?php

namespace common\modules\orderPayment;

use common\classes\modules\ModulePayment;
use common\classes\modules\ModuleSortOrder;
use common\classes\modules\ModuleStatus;
use common\modules\orderPayment\lib\PayxClient;

require_once __DIR__ . '/lib/PayxClient.php';

/** osCommerce 4 hosted checkout. The browser never handles card data or confirms payment. */
final class payxcommerce extends ModulePayment
{
    public $code = 'payxcommerce';
    public $title = 'PayXCommerce';
    public $public_title = 'Pay securely with PayXCommerce';
    public $description = 'Redirect customers to PayXCommerce hosted checkout.';
    public $enabled = false;
    protected $visibility = ['shop_order'];

    protected $encrypted_keys = [
        'MODULE_PAYMENT_PAYXCOMMERCE_SECRET_KEY',
        'MODULE_PAYMENT_PAYXCOMMERCE_CLIENT_SECRET',
        'MODULE_PAYMENT_PAYXCOMMERCE_WEBHOOK_SECRET',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->enabled = defined('MODULE_PAYMENT_PAYXCOMMERCE_STATUS')
            && MODULE_PAYMENT_PAYXCOMMERCE_STATUS === 'True';
        $this->sort_order = defined('MODULE_PAYMENT_PAYXCOMMERCE_SORT_ORDER')
            ? (int) MODULE_PAYMENT_PAYXCOMMERCE_SORT_ORDER : 0;
        $this->order_status = defined('MODULE_PAYMENT_PAYXCOMMERCE_PENDING_STATUS_ID')
            ? (int) MODULE_PAYMENT_PAYXCOMMERCE_PENDING_STATUS_ID : 0;
        $statuses = [$this->order_status];
        foreach (['SUCCESS', 'FAILED', 'CANCELLED', 'PARTIAL_REFUNDED', 'REFUNDED', 'CHARGEBACK'] as $kind) {
            $statuses[] = $this->status($kind);
        }
        if (min($statuses) <= 0 || count(array_unique($statuses)) !== count($statuses)) {
            $this->enabled = false;
        }
        if (defined('MODULE_PAYMENT_PAYXCOMMERCE_STATUS') && MODULE_PAYMENT_PAYXCOMMERCE_STATUS === 'True'
            && (!$this->configured() || $this->order_status <= 0 || !$this->enabled)) {
            $this->description = 'Configure credentials, webhook signing, and every order status before enabling. ' . $this->description;
            $this->enabled = false;
        }
        $this->dont_send_email = true;
        $this->validateAdminSaveBeforeAnyWrite();
        if (\Yii::$app->id === 'app-backend'
            && \Yii::$app->controller->id === 'modules'
            && \Yii::$app->controller->action->id === 'edit'
            && \Yii::$app->request->get('module') === $this->code) {
            \Yii::$app->view->registerJs(<<<'JS'
$(document).ajaxError(function (_event, xhr, settings) {
    if (xhr.status === 422 && settings.url.indexOf('modules/save?set=payment') !== -1) {
        var message = $('<div>').text(xhr.responseText).html();
        Resp('payxcommerce', '<div class="alert alert-danger">' + message + '</div>');
    }
});
JS);
        }
        if ($this->enabled && \Yii::$app->id === 'app-frontend'
            && \Yii::$app->controller->id === 'checkout'
            && \Yii::$app->controller->action->id === 'index'
            && !\Yii::$app->request->isPost
            && \Yii::$app->request->get('payxcommerce_express') === '1'
            && isset($this->manager) && $this->allowedForExpress()) {
            // Express is a shortcut into the normal checkout, not an order or
            // payment-creation endpoint. All address/shipping/consent checks remain.
            $this->manager->setPayment($this->code);
        }
    }

    /** The core save action writes fields one by one, so reject an invalid
     * enabled configuration in the constructor, before its first write. */
    private function validateAdminSaveBeforeAnyWrite(): void
    {
        if (\Yii::$app->id !== 'app-backend'
            || \Yii::$app->controller->id !== 'modules'
            || \Yii::$app->controller->action->id !== 'save'
            || \Yii::$app->request->post('module') !== $this->code) {
            return;
        }
        $submitted = \Yii::$app->request->post('configuration');
        if (!is_array($submitted)) {
            self::rejectSettingsSave('PayXCommerce settings were not submitted. No settings were changed.');
        }
        $platformId = (int) \Yii::$app->request->post('platform_id', \Yii::$app->request->get('platform_id', 0));
        if ($platformId <= 0) {
            self::rejectSettingsSave('Select a storefront before saving PayXCommerce settings. No settings were changed.');
        }
        $keys = array_keys($this->configure_keys());
        if (array_diff(array_keys($submitted), $keys)) {
            self::rejectSettingsSave('Unknown PayXCommerce setting. No settings were changed.');
        }
        $saved = \Yii::$app->db->createCommand(
            'SELECT configuration_key, configuration_value FROM platforms_configuration WHERE platform_id = :platform AND configuration_key LIKE :prefix',
            [':platform' => $platformId, ':prefix' => 'MODULE_PAYMENT_PAYXCOMMERCE_%']
        )->queryAll();
        $effective = array_column($saved, 'configuration_value', 'configuration_key');
        foreach ($submitted as $key => $value) {
            if (!is_scalar($value)) {
                self::rejectSettingsSave('Invalid PayXCommerce setting value. No settings were changed.');
            }
            if (in_array($key, $this->encrypted_keys, true)
                && ($value === '' || $value === (defined('PASSWORD_HIDDEN') ? PASSWORD_HIDDEN : '--Encrypted--'))) {
                continue;
            }
            $effective[$key] = (string) $value;
        }
        if (!in_array($effective['MODULE_PAYMENT_PAYXCOMMERCE_STATUS'] ?? '', ['True', 'False'], true)
            || !in_array($effective['MODULE_PAYMENT_PAYXCOMMERCE_EXPRESS_CHECKOUT'] ?? '', ['True', 'False'], true)
            || !ctype_digit((string) ($effective['MODULE_PAYMENT_PAYXCOMMERCE_SORT_ORDER'] ?? ''))) {
            self::rejectSettingsSave('PayXCommerce enablement or sort order is invalid. No settings were changed.');
        }
        if (($effective['MODULE_PAYMENT_PAYXCOMMERCE_STATUS'] ?? 'False') !== 'True') {
            return;
        }
        $mode = $effective['MODULE_PAYMENT_PAYXCOMMERCE_ENVIRONMENT'] ?? '';
        $auth = $effective['MODULE_PAYMENT_PAYXCOMMERCE_AUTH_METHOD'] ?? '';
        if (!in_array($mode, ['test', 'live'], true) || !in_array($auth, ['hmac', 'bearer'], true)) {
            self::rejectSettingsSave('Select a valid PayXCommerce mode and authentication method. No settings were changed.');
        }
        try {
            new PayxClient(['base_url' => $effective['MODULE_PAYMENT_PAYXCOMMERCE_API_URL'] ?? '']);
        } catch (\InvalidArgumentException $exception) {
            self::rejectSettingsSave('PayXCommerce API URL must be an HTTPS PayXCommerce host. No settings were changed.');
        }
        if (($effective['MODULE_PAYMENT_PAYXCOMMERCE_WEBHOOK_SECRET'] ?? '') === ''
            || ($auth === 'hmac' && (!str_starts_with($effective['MODULE_PAYMENT_PAYXCOMMERCE_PUBLIC_KEY'] ?? '', $mode === 'test' ? 'pk_test_' : 'pk_live_')
                || ($effective['MODULE_PAYMENT_PAYXCOMMERCE_SECRET_KEY'] ?? '') === ''))
            || ($auth === 'bearer' && (($effective['MODULE_PAYMENT_PAYXCOMMERCE_CLIENT_ID'] ?? '') === ''
                || ($effective['MODULE_PAYMENT_PAYXCOMMERCE_CLIENT_SECRET'] ?? '') === ''))) {
            self::rejectSettingsSave('PayXCommerce credentials or webhook signing secret are incomplete for this mode. No settings were changed.');
        }
        $statusKeys = ['PENDING', 'SUCCESS', 'FAILED', 'CANCELLED', 'PARTIAL_REFUNDED', 'REFUNDED', 'CHARGEBACK'];
        $ids = [];
        foreach ($statusKeys as $kind) {
            $value = $effective['MODULE_PAYMENT_PAYXCOMMERCE_' . $kind . '_STATUS_ID'] ?? '';
            if (!ctype_digit($value) || (int) $value <= 0) {
                self::rejectSettingsSave('Every PayXCommerce order status must be selected before enabling. No settings were changed.');
            }
            $ids[] = (int) $value;
        }
        if (count(array_unique($ids)) !== count($ids)) {
            self::rejectSettingsSave('PayXCommerce order statuses must be distinct. No settings were changed.');
        }
        $found = \Yii::$app->db->createCommand('SELECT DISTINCT orders_status_id FROM orders_status WHERE orders_status_id IN (' . implode(',', $ids) . ')')->queryColumn();
        if (count($found) !== count($ids)) {
            self::rejectSettingsSave('A PayXCommerce order status does not exist. No settings were changed.');
        }
    }

    private static function rejectSettingsSave(string $message): never
    {
        // The osCommerce edit form saves over AJAX. Use a real failure status;
        // the edit-page handler displays this safe, explicit message.
        \Yii::$app->response->statusCode = 422;
        \Yii::$app->response->content = $message;
        \Yii::$app->end();
    }

    public static function selectOrderStatus($value, $key): string
    {
        $languageId = (int) \Yii::$app->settings->get('languages_id');
        $rows = \Yii::$app->db->createCommand(
            'SELECT orders_status_id, COALESCE(MAX(CASE WHEN language_id = :language THEN orders_status_name END), MIN(orders_status_name)) AS orders_status_name '
            . 'FROM orders_status GROUP BY orders_status_id ORDER BY orders_status_name, orders_status_id',
            [':language' => $languageId]
        )->queryAll();
        $choices = ['0' => 'Select an order status'];
        foreach ($rows as $row) {
            // Include group 0: the core grouped control silently drops it.
            $choices[(string) $row['orders_status_id']] = $row['orders_status_name'];
        }
        return \yii\helpers\Html::dropDownList('configuration[' . $key . ']', (string) $value, $choices, ['class' => 'form-control']);
    }

    public function confValueBeforeSave($key, $value, $platform_id)
    {
        if (in_array($key, $this->encrypted_keys, true) && $value === '') {
            // The admin renders encrypted values as a placeholder. An empty
            // ordinary save must never erase the previously stored secret.
            return (new \common\classes\platform_config($platform_id))->const_value($key, '');
        }
        return parent::confValueBeforeSave($key, $value, $platform_id);
    }

    public function checkout_initialization_method(): string
    {
        if (!$this->enabled || \Yii::$app->id !== 'app-frontend'
            || \Yii::$app->controller->id !== 'shopping-cart'
            || !defined('MODULE_PAYMENT_PAYXCOMMERCE_EXPRESS_CHECKOUT')
            || MODULE_PAYMENT_PAYXCOMMERCE_EXPRESS_CHECKOUT !== 'True'
            || !$this->allowedForExpress()) {
            return '';
        }
        global $cart;
        if (!$cart || $cart->count_contents() < 1 || $cart->show_total() <= 0) {
            return '';
        }
        $guest = \Yii::$app->user->isGuest
            && !\Yii::$app->get('platform')->getConfig(\common\classes\platform::currentId())->checkNeedLogged();
        $query = ($guest ? 'guest=1&' : '') . 'payxcommerce_express=1';
        $url = tep_href_link('checkout', $query, 'SSL');
        $logoUrl = rtrim((string) \Yii::getAlias('@web'), '/') . '/images/payxcommerce/logo-icon-dark-64.png';
        return self::expressButtonHtml($url, $logoUrl);
    }

    private static function expressButtonHtml(string $url, string $logoUrl): string
    {
        $checkout = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<style>' . self::buttonCss() . '</style>'
            . '<a class="btn-2 payxcommerce-express-button" aria-label="Continue to checkout with PayXCommerce" href="' . $checkout . '">'
            . self::buttonContents($logoUrl, 'Pay with PayXCommerce') . '</a>';
    }

    private static function buttonCss(): string
    {
        return <<<'CSS'
a.payxcommerce-express-button{position:relative;display:flex;align-items:center;justify-content:center;gap:9px;width:100%;max-width:750px;height:50px;padding:5px 12px;box-sizing:border-box;overflow:hidden;border:none;border-radius:999px;background:linear-gradient(110deg,#071839 0%,#082b56 70%,#064b65 100%);box-shadow:0 4px 12px rgba(5,29,67,.2),inset 0 1px 1px rgba(255,255,255,.1);color:#fff;font:700 15px/1.2 Arial,Helvetica,sans-serif;text-decoration:none;white-space:nowrap;cursor:pointer;transition:transform .2s ease,box-shadow .2s ease}
a.payxcommerce-express-button:hover{transform:translateY(-2px);box-shadow:0 7px 16px rgba(5,29,67,.28),0 0 0 1px rgba(0,185,181,.15);color:#fff;text-decoration:none}
a.payxcommerce-express-button:active{transform:translateY(0)}
a.payxcommerce-express-button:focus-visible{outline:3px solid #00b9b5;outline-offset:3px}
a.payxcommerce-express-button .payxcommerce-wave{position:absolute;right:0;bottom:0;width:42%;height:100%;pointer-events:none}
a.payxcommerce-express-button .payxcommerce-logo{position:relative;z-index:1;display:flex;align-items:center;justify-content:center;width:33px;height:33px;flex-shrink:0;background:#fff;border-radius:9px}
a.payxcommerce-express-button .payxcommerce-logo img{width:27px;height:27px;object-fit:contain}
a.payxcommerce-express-button .payxcommerce-text{position:relative;z-index:1;letter-spacing:-.2px;color:#ffffff}
@media(max-width:480px){a.payxcommerce-express-button{gap:7px;padding:5px 9px;font-size:14px}a.payxcommerce-express-button .payxcommerce-logo{width:31px;height:31px}a.payxcommerce-express-button .payxcommerce-logo img{width:25px;height:25px}}
@media(prefers-reduced-motion:reduce){a.payxcommerce-express-button{transition:none}}
CSS;
    }

    private static function buttonContents(string $logoUrl, string $label): string
    {
        $icon = htmlspecialchars($logoUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<svg class="payxcommerce-wave" viewBox="0 0 200 70" preserveAspectRatio="none" aria-hidden="true">'
            . '<path d="M0 70 C90 67 165 50 200 12 L200 70 Z" fill="#007e8b" opacity="0.55"/>'
            . '<path d="M65 70 C135 65 183 41 200 23 L200 43 C175 62 148 70 125 70 Z" fill="#00c5bf"/>'
            . '</svg><span class="payxcommerce-logo"><img src="' . $icon . '" alt=""></span>'
            . '<span class="payxcommerce-text">' . $text . '</span>';
    }

    private function allowedForExpress(): bool
    {
        $platformId = \common\classes\platform::currentId();
        $groupId = (int) \Yii::$app->storage->get('customer_groups_id');
        if ($groupId === 0 && \Yii::$app->user->isGuest && defined('DEFAULT_USER_GROUP')) {
            $groupId = (int) DEFAULT_USER_GROUP;
        }
        return $this->getVisibily($platformId, ['shop_order'])
            && $this->getGroupVisibily($platformId, $groupId);
    }

    public function selection()
    {
        $logoUrl = rtrim((string) \Yii::getAlias('@web'), '/') . '/images/payxcommerce/logo-icon-dark-64.png';
        self::registerCheckoutButton($logoUrl);
        return ['id' => $this->code, 'module' => $this->public_title];
    }

    private static function registerCheckoutButton(string $logoUrl): void
    {
        $selector = '#frmCheckout .w-checkout-continue-btn button.payxcommerce-checkout-button';
        $css = str_replace('a.payxcommerce-express-button', $selector, self::buttonCss());
        // PayPal restores the shared submit with an inline display:inline-block.
        // Override that only while PayX is selected; removing this class lets
        // the other payment module control its own button again.
        $css .= "\n" . $selector . '{display:flex!important}';
        $css .= "\n" . $selector . '.disabled-area,' . $selector
            . '[disabled]{opacity:.5;cursor:not-allowed;transform:none}';
        \Yii::$app->getView()->registerCss($css, [], 'payxcommerce-checkout-button');

        $contents = json_encode(
            self::buttonContents($logoUrl, 'Pay with PayXCommerce'),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $js = str_replace('__PAYX_MARKUP__', $contents, <<<'JS'
(function () {
    if (window.payxcommerceCheckoutButtonBound) return;
    window.payxcommerceCheckoutButtonBound = true;
    var originalMarkup = new WeakMap();
    var payxMarkup = __PAYX_MARKUP__;

    function updateButton() {
        var form = document.getElementById('frmCheckout');
        if (!form) return;
        var button = form.querySelector('.w-checkout-continue-btn button[type="submit"].btn-next');
        if (!button) return;
        var selected = form.querySelector('input[name="payment"]:checked');
        var isPayx = selected && selected.value === 'payxcommerce';
        if (isPayx && !button.classList.contains('payxcommerce-checkout-button')) {
            originalMarkup.set(button, button.innerHTML);
            button.innerHTML = payxMarkup;
            button.classList.add('payxcommerce-checkout-button');
        } else if (!isPayx && button.classList.contains('payxcommerce-checkout-button')) {
            button.innerHTML = originalMarkup.get(button) || button.innerHTML;
            button.classList.remove('payxcommerce-checkout-button');
            originalMarkup.delete(button);
        }
    }

    function onPaymentChoice(event) {
        if (event.target.matches('input[name="payment"]')) {
            setTimeout(updateButton, 0);
        }
    }
    document.addEventListener('click', onPaymentChoice);
    document.addEventListener('change', onPaymentChoice);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateButton, { once: true });
    } else {
        updateButton();
    }
})();
JS);
        \Yii::$app->getView()->registerJs($js, \common\components\View::POS_END, 'payxcommerce-checkout-button');
    }

    public function isOnline()
    {
        return true;
    }

    public function before_process()
    {
        if (!$this->enabled) {
            throw new \RuntimeException('PayXCommerce payment is not fully configured.');
        }
        $order = $this->manager->getOrderInstance();
        $order->info['order_status'] = $this->order_status;
        $order->isPaidUpdated = true;
        $this->resumeFailedCheckout($order);
    }

    /** osCommerce saves the order before after_process. Reuse that same order
     * after a failed PayX initiation instead of creating another on retry. */
    private function resumeFailedCheckout($order): void
    {
        global $cart;
        $markedId = (int) $this->manager->get('payxcommerce_retry_order_id');
        // A fatal error in an older plugin version may have left cart->order_id
        // but no PayX retry marker. Reuse only after checking the saved order.
        $id = $markedId > 0 ? $markedId : (int) ($cart->order_id ?? 0);
        if ($id <= 0) return;

        $fingerprint = (string) $this->manager->get('payxcommerce_retry_fingerprint');
        if ($markedId > 0 && ($fingerprint === '' || !hash_equals($fingerprint, $this->retryFingerprint($order)))) {
            $this->clearRetry(); // A changed checkout is a new order, not a payment retry.
            return;
        }
        $saved = \Yii::$app->db->createCommand(
            'SELECT orders_id, customers_id, basket_id, platform_id, payment_class, orders_status, currency FROM orders WHERE orders_id = :id',
            [':id' => $id]
        )->queryOne();
        $savedAmount = \Yii::$app->db->createCommand(
            "SELECT value_inc_tax FROM orders_total WHERE orders_id = :id AND class = 'ot_total' AND is_removed = 0 ORDER BY orders_total_id DESC LIMIT 1",
            [':id' => $id]
        )->queryScalar();
        if (!$saved || (int) $saved['customers_id'] !== (int) \Yii::$app->user->id
            || (int) $saved['basket_id'] !== (int) ($cart->basketID ?? 0)
            || (int) $saved['platform_id'] !== (int) \common\classes\platform::currentId()
            || $saved['payment_class'] !== $this->code
            || $saved['currency'] !== (string) ($order->info['currency'] ?? '')
            || $savedAmount === false
            || self::normalizeDecimal((string) $savedAmount) !== self::normalizeDecimal((string) ($order->info['total_inc_tax'] ?? ''))) {
            $this->clearRetry();
            return;
        }
        if ((int) $saved['orders_status'] !== $this->order_status) {
            $this->clearRetry();
            $this->checkoutError('This order is no longer awaiting payment. Check your order history before trying again.');
        }
        $order->order_id = $id;
        $this->after_process(); // Success and failure both redirect; core must not save a second order.
        throw new \LogicException('PayX checkout retry did not redirect.');
    }

    private function retryFingerprint($order): string
    {
        global $cart;
        return hash('sha256', json_encode([
            (int) \Yii::$app->user->id,
            (int) \common\classes\platform::currentId(),
            (int) ($cart->basketID ?? 0),
            (string) ($cart->cartID ?? ''),
            (string) ($order->info['currency'] ?? ''),
            (string) ($order->info['total_inc_tax'] ?? ''),
            $this->manager->get('shipping'),
            $this->manager->get('billto'),
            $this->manager->get('sendto'),
        ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
    }

    private function clearRetry(): void
    {
        $this->manager->remove('payxcommerce_retry_order_id');
        $this->manager->remove('payxcommerce_retry_fingerprint');
    }

    private function checkoutError(string $message): never
    {
        $this->manager->set('payxcommerce_checkout_error', $message);
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code, 'SSL'));
    }

    public function get_error(): array
    {
        $message = (string) $this->manager->get('payxcommerce_checkout_error');
        $this->manager->remove('payxcommerce_checkout_error');
        return [
            'title' => 'PayXCommerce payment could not start',
            'error' => $message ?: 'No payment was taken. Please try again or contact the store for help.',
        ];
    }

    public function after_process()
    {
        $order = $this->manager->getOrderInstance();
        $id = (int) ($order->order_id ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('osCommerce order was not saved before PayX checkout.');
        }
        try {
            $checkoutUrl = $this->startCheckoutForSavedOrder($order, $id);
            // The offsite redirect exits before CheckoutController performs these steps.
            $this->trackCredits();
            $this->manager->clearAfterProcess();
            $this->clearRetry();
            $this->manager->remove('payxcommerce_checkout_error');
            $this->redirectCheckout($checkoutUrl);
        } catch (\Throwable $exception) {
            // Do not expose an API response, credentials, or a stack trace to the buyer.
            $failure = preg_match('/^PayX request failed \(HTTP ([0-9]{3})/', $exception->getMessage(), $match)
                ? 'API HTTP ' . $match[1] : get_class($exception);
            \Yii::error('PayX checkout initiation failed for order #' . $id . ' (' . $failure . ')', 'payxcommerce');
            $this->manager->set('payxcommerce_retry_order_id', $id);
            $this->manager->set('payxcommerce_retry_fingerprint', $this->retryFingerprint($order));
            $this->checkoutError(self::checkoutFailureMessage($id, $exception));
        }
    }

    private static function checkoutFailureMessage(int $orderId, \Throwable $exception): string
    {
        if ($exception instanceof \common\modules\orderPayment\lib\PayxCountryRestrictionException) {
            return 'Order #' . $orderId . ' was saved, but its billing country is not accepted for PayXCommerce payment. No payment was taken. Please contact the store with this order number.';
        }
        return 'Order #' . $orderId . ' was saved, but secure payment could not start. No payment was taken. Please try again or contact the store with this order number.';
    }

    private function startCheckoutForSavedOrder($order, int $id): string
    {
        $db = \Yii::$app->db;
        $lock = 'pxc_osc4_order_' . $id;
        if ((int) $db->createCommand('SELECT GET_LOCK(:name, 10)', [':name' => $lock])->queryScalar() !== 1) {
            throw new \RuntimeException('PayX checkout is already being prepared.');
        }
        $checkoutUrl = '';
        try {
            $row = $db->createCommand('SELECT * FROM payxcommerce_order WHERE orders_id = :id', [':id' => $id])->queryOne();
            if ($row && $row['payment_status'] === 'paid') {
                throw new \RuntimeException('This order has already been paid.');
            }
            $orderRow = $db->createCommand('SELECT platform_id, currency, currency_value, customers_name, customers_email_address, customers_telephone, billing_street_address, billing_city, billing_country FROM orders WHERE orders_id = :id', [':id' => $id])->queryOne();
            $totalRow = $db->createCommand("SELECT value_inc_tax, currency, currency_value FROM orders_total WHERE orders_id = :id AND class = 'ot_total' AND is_removed = 0 ORDER BY orders_total_id DESC LIMIT 1", [':id' => $id])->queryOne();
            if (!$orderRow || !$totalRow) {
                throw new \RuntimeException('Order currency or exact decimal total is unavailable.');
            }
            $scale = $db->createCommand('SELECT decimal_places FROM currencies WHERE code = :currency AND status = 1 LIMIT 1', [':currency' => $orderRow['currency']])->queryScalar();
            if ($scale === false || !ctype_digit((string) $scale) || (int) $scale > 2) {
                throw new \RuntimeException('Currency precision is unsupported by this osCommerce payment ledger.');
            }
            $amount = self::payableAmountForSavedOrder($orderRow, $totalRow, (int) $scale,
                defined('USE_MARKET_PRICES') && USE_MARKET_PRICES === 'True');
            $country = $db->createCommand('SELECT countries_iso_code_2 FROM countries WHERE countries_name = :name LIMIT 1', [':name' => $orderRow['billing_country']])->queryScalar();
            if (!preg_match('/^[A-Z]{2}$/', (string) $country)) {
                throw new \RuntimeException('A valid billing country is required for hosted checkout.');
            }
            $environment = $this->environment();
            $reference = 'OSC4-' . (int) $orderRow['platform_id'] . '-' . $id;
            $key = $row['idempotency_key'] ?? ('osc4-' . bin2hex(random_bytes(20)));
            if (!$row) {
                $db->createCommand()->insert('payxcommerce_order', [
                    'orders_id' => $id, 'platform_id' => (int) $orderRow['platform_id'], 'merchant_reference' => $reference,
                    'idempotency_key' => $key, 'amount' => $amount, 'currency' => $orderRow['currency'],
                    'environment' => $environment, 'payment_status' => 'creating',
                ])->execute();
            } elseif (self::normalizeDecimal((string) $row['amount']) !== self::normalizeDecimal($amount) || $row['currency'] !== $orderRow['currency'] || $row['environment'] !== $environment) {
                throw new \RuntimeException('Order changed after PayX checkout was prepared.');
            }
            if ($row && $row['checkout_url'] !== '' && $row['payment_status'] === 'pending') {
                $this->ensurePaymentLedger($order, (string) $row['request_number'], $amount, $orderRow);
                $checkoutUrl = (string) $row['checkout_url'];
            } else {
                // A return URL is only a read-only order-status capability; it
                // never confirms payment. It also works when a guest session ends.
                $returnUrl = tep_href_link('callback/webhooks.payment.payxcommerce',
                    'action=return&orders_id=' . $id . '&token=' . self::returnToken($id, (string) $key), 'SSL');
                $payload = [
                    'amount' => $amount,
                    'currency' => $orderRow['currency'],
                    'purpose' => 'osCommerce Order #' . $id,
                    'customer' => self::customerPayload($orderRow, $country),
                    'merchant_reference' => $reference,
                    'merchant_order_id' => (string) $id,
                    'success_url' => $returnUrl,
                    'failed_url' => $returnUrl,
                    'cancel_url' => $returnUrl,
                    'webhook_url' => tep_href_link('callback/webhooks.payment.payxcommerce', '', 'SSL'),
                    'ipn_events' => ['payment.success', 'payment.failed', 'payment.cancelled', 'payment.expired', 'refund.success', 'payment.refunded', 'dispute.lost'],
                    'metadata' => ['platform' => 'oscommerce', 'platform_version' => '4', 'order_id' => (string) $id],
                    'environment' => $environment,
                    'is_test' => $environment === 'test',
                ];
                $response = (new PayxClient($this->settings()))->create($payload, $key);
                $checkoutUrl = (string) ($response['checkout_url'] ?? '');
                $requestNumber = (string) ($response['request_number'] ?? '');
                if ($requestNumber === '' || !$this->safeCheckoutUrl($checkoutUrl)) {
                    throw new \RuntimeException('PayX did not return a valid request and hosted checkout URL.');
                }
                $db->createCommand()->update('payxcommerce_order', [
                    'request_number' => $requestNumber,
                    'invoice_number' => (string) ($response['invoice_number'] ?? ''),
                    'checkout_url' => $checkoutUrl,
                    'payment_status' => 'pending',
                ], ['orders_id' => $id])->execute();
                $this->ensurePaymentLedger($order, $requestNumber, $amount, $orderRow);
            }
        } finally {
            $db->createCommand('SELECT RELEASE_LOCK(:name)', [':name' => $lock])->queryScalar();
        }
        return $checkoutUrl;
    }

    private static function customerPayload(array $orderRow, string $country): array
    {
        $customer = [
            'name' => trim((string) $orderRow['customers_name']) ?: 'Customer',
            'email' => (string) $orderRow['customers_email_address'],
            'address' => (string) $orderRow['billing_street_address'],
            'city' => (string) $orderRow['billing_city'],
            'country' => $country,
        ];
        $phone = trim((string) $orderRow['customers_telephone']);
        if ($phone !== '') $customer['mobile'] = $phone;
        return $customer;
    }

    private function redirectCheckout(string $url): void
    {
        if (!$this->safeCheckoutUrl($url)) {
            throw new \RuntimeException('PayX checkout URL is not HTTPS.');
        }
        tep_redirect($url);
    }

    private function ensurePaymentLedger($order, string $requestNumber, string $amount, array $orderRow): void
    {
        $db = \Yii::$app->db;
        $existing = $db->createCommand("SELECT orders_payment_id FROM orders_payment WHERE orders_payment_module = 'payxcommerce' AND orders_payment_transaction_id = :ref AND orders_payment_order_id = :id LIMIT 1", [
            ':ref' => $requestNumber, ':id' => (int) $order->order_id,
        ])->queryScalar();
        if ($existing !== false) {
            return;
        }
        $snapshot = json_encode(\common\helpers\OrderPayment::getOrderPaymentSnapshot($order), JSON_THROW_ON_ERROR);
        $db->createCommand()->insert('orders_payment', [
            'orders_payment_order_id' => (int) $order->order_id,
            'orders_payment_module' => 'payxcommerce',
            'orders_payment_module_name' => $this->title,
            'orders_payment_status' => \common\helpers\OrderPayment::OPYS_PENDING,
            'orders_payment_amount' => $amount,
            'orders_payment_currency' => $orderRow['currency'],
            'orders_payment_currency_rate' => $db->createCommand('SELECT currency_value FROM orders WHERE orders_id = :id', [':id' => (int) $order->order_id])->queryScalar(),
            'orders_payment_snapshot' => $snapshot,
            'orders_payment_transaction_id' => $requestNumber,
            'orders_payment_transaction_status' => 'pending',
            'orders_payment_transaction_date' => date('Y-m-d H:i:s'),
        ])->execute();
    }

    private function safeCheckoutUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        return ($parts['scheme'] ?? '') === 'https' && ($host === 'payxcommerce.com' || str_ends_with($host, '.payxcommerce.com'))
            && !isset($parts['user']) && !isset($parts['pass']);
    }

    private static function returnToken(int $orderId, string $idempotencyKey): string
    {
        return hash_hmac('sha256', 'payxcommerce-oscommerce-return-v1:' . $orderId, $idempotencyKey);
    }

    private static function browserPaymentState(string $state): string
    {
        return in_array($state, ['paid', 'failed', 'cancelled', 'expired', 'partially_refunded', 'refunded', 'chargeback'], true)
            ? $state : 'pending';
    }

    /** Browser status is read-only and is scoped to the order owner or its
     * unguessable return capability. A bare orders_id is never sufficient. */
    private function browserOrder(): ?array
    {
        $rawId = \Yii::$app->request->get('orders_id', '');
        if (!is_string($rawId) && !is_int($rawId)) return null;
        $rawId = (string) $rawId;
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $rawId)) return null;
        $id = (int) $rawId;
        $row = \Yii::$app->db->createCommand(
            'SELECT p.orders_id, p.platform_id, p.idempotency_key, p.request_number, p.amount, p.currency, '
            . 'p.payment_status, p.created_at, o.customers_id, o.platform_id AS order_platform_id, o.payment_class '
            . 'FROM payxcommerce_order p JOIN orders o ON o.orders_id = p.orders_id WHERE p.orders_id = :id',
            [':id' => $id]
        )->queryOne();
        if (!$row || $row['payment_class'] !== $this->code || (int) $row['platform_id'] !== (int) $row['order_platform_id']
            || (int) $row['platform_id'] !== (int) \common\classes\platform::currentId()
            || !is_string($row['request_number']) || $row['request_number'] === '') return null;

        $owner = !\Yii::$app->user->isGuest && (int) \Yii::$app->user->getId() === (int) $row['customers_id'];
        $token = \Yii::$app->request->get('token', '');
        if (!is_string($token)) $token = '';
        $created = strtotime((string) $row['created_at']);
        $validToken = preg_match('/^[a-f0-9]{64}$/D', $token) === 1 && $created !== false
            && $created >= time() - 7 * 86400 && $created <= time() + 300
            && hash_equals(self::returnToken($id, (string) $row['idempotency_key']), $token);
        if (!$owner && !$validToken) return null;
        $row['owner'] = $owner;
        $row['token'] = $validToken ? $token : '';
        $row['browser_state'] = self::browserPaymentState((string) $row['payment_status']);
        return $row;
    }

    private function browserReturn(): string|array
    {
        $response = \Yii::$app->response;
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'");
        $order = $this->browserOrder();
        if (!$order) {
            $response->statusCode = 404;
            return 'Order not found or return access expired.';
        }
        $action = (string) \Yii::$app->request->get('action');
        if ($action === 'status') {
            $response->format = \yii\web\Response::FORMAT_JSON;
            return ['state' => $order['browser_state']];
        }
        if ($action === 'return' && $order['browser_state'] === 'paid' && $order['owner']) {
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, 'order_id=' . $order['orders_id'], 'SSL'));
        }
        if ($action === 'order' && $order['owner']) {
            tep_redirect(tep_href_link('account/history-info', 'order_id=' . $order['orders_id'], 'SSL'));
        }
        $response->format = \yii\web\Response::FORMAT_HTML;
        $id = (int) $order['orders_id'];
        $params = 'orders_id=' . $id . ($order['token'] !== '' ? '&token=' . $order['token'] : '');
        $statusUrl = tep_href_link('callback/webhooks.payment.payxcommerce', 'action=status&' . $params, 'SSL');
        $orderUrl = $order['owner']
            ? tep_href_link('account/history-info', 'order_id=' . $id, 'SSL')
            : tep_href_link('callback/webhooks.payment.payxcommerce', 'action=order&' . $params, 'SSL');
        $homeUrl = tep_href_link(FILENAME_DEFAULT, '', 'SSL');
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $state = $order['browser_state'];
        if ($action === 'order') {
            // The return capability is short-lived and order-bound. Show only
            // non-PII order items; never expose addresses or account details.
            $items = \Yii::$app->db->createCommand(
                'SELECT products_name, products_quantity FROM orders_products WHERE orders_id = :id ORDER BY orders_products_id',
                [':id' => $id]
            )->queryAll();
            $itemHtml = '';
            foreach ($items as $item) {
                $itemHtml .= '<li><span>' . $escape((string) $item['products_name']) . '</span><span>Qty '
                    . (int) $item['products_quantity'] . '</span></li>';
            }
            if ($itemHtml === '') $itemHtml = '<li>Order items are unavailable. Contact the store with your order number.</li>';
            $stateLabel = match ($state) {
                'paid' => 'Paid',
                'failed' => 'Payment failed',
                'cancelled' => 'Payment cancelled',
                'expired' => 'Payment expired',
                'partially_refunded' => 'Partially refunded',
                'refunded' => 'Refunded',
                'chargeback' => 'Payment disputed',
                default => 'Awaiting payment confirmation',
            };
            $returnUrl = $escape(tep_href_link('callback/webhooks.payment.payxcommerce', 'action=return&' . $params, 'SSL'));
            $continueUrl = $escape($homeUrl);
            return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<title>Order details — Order #' . $id . '</title><style>body{margin:0;background:#f3f7f9;color:#153448;font:16px/1.5 system-ui,sans-serif}'
                . 'main{max-width:640px;margin:8vh auto;padding:32px;background:white;border-radius:12px;box-shadow:0 8px 28px #15344818}'
                . 'h1{margin:0 0 12px}ul{padding:0;list-style:none}li{display:flex;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid #dce5e9}'
                . 'nav{display:flex;gap:12px;flex-wrap:wrap;margin-top:28px}a{display:inline-block;padding:10px 16px;border-radius:7px;background:#073d55;color:white;text-decoration:none}'
                . 'a.secondary{background:#e7eef2;color:#153448}</style></head><body><main><h1>Order details</h1>'
                . '<p>Order #' . $id . ' · ' . $escape($stateLabel) . '</p><h2>Items</h2><ul>' . $itemHtml . '</ul>'
                . '<p><strong>Total: ' . $escape((string) $order['currency'] . ' ' . self::normalizeDecimal((string) $order['amount'])) . '</strong></p>'
                . '<nav><a href="' . $returnUrl . '">Payment status</a><a class="secondary" href="' . $continueUrl
                . '">Continue Shopping</a></nav></main></body></html>';
        }
        $message = match ($state) {
            'paid' => ['Payment confirmed', 'Your secure payment was verified and your order is confirmed.'],
            'failed' => ['Payment failed', 'Your payment was not confirmed. Please review your order or contact the store.'],
            'cancelled' => ['Payment cancelled', 'Your payment was cancelled. Your order was not paid.'],
            'expired' => ['Payment expired', 'The payment window expired. Your order was not paid.'],
            'partially_refunded' => ['Payment partially refunded', 'Payment was confirmed and a partial refund was recorded.'],
            'refunded' => ['Payment refunded', 'Payment was confirmed and then refunded.'],
            'chargeback' => ['Payment disputed', 'The payment is under dispute. Contact the store for help.'],
            default => ['Confirming payment', 'Your order was saved. We are waiting for secure payment confirmation.'],
        };
        $title = $escape($message[0]);
        $detail = $escape($message[1]);
        $amount = $escape((string) $order['currency'] . ' ' . self::normalizeDecimal((string) $order['amount']));
        $viewUrl = $escape($orderUrl);
        $continueUrl = $escape($homeUrl);
        $poll = $state === 'pending' ? '<p id="payx-wait">Checking for confirmation for up to 90 seconds. If it takes longer, use View Order later or contact the store with your order number.</p>' : '';
        $script = '';
        if ($state === 'pending') {
            $statusJson = json_encode($statusUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
            $returnJson = json_encode(tep_href_link('callback/webhooks.payment.payxcommerce', 'action=return&' . $params, 'SSL'),
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
            $script = '<script>(function(){var end=Date.now()+90000;var statusUrl=' . $statusJson . ',returnUrl=' . $returnJson
                . ';function check(){if(Date.now()>=end)return;fetch(statusUrl,{credentials:"same-origin",cache:"no-store"})'
                . '.then(function(r){if(!r.ok)throw Error("status");return r.json()})'
                . '.then(function(d){if(d.state!=="pending"){location.replace(returnUrl);return}setTimeout(check,3000)})'
                . '.catch(function(){setTimeout(check,5000)})}setTimeout(check,3000)})();</script>';
        }
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $title . ' — Order #' . $id . '</title><style>body{margin:0;background:#f3f7f9;color:#153448;font:16px/1.5 system-ui,sans-serif}'
            . 'main{max-width:640px;margin:8vh auto;padding:32px;background:white;border-radius:12px;box-shadow:0 8px 28px #15344818}'
            . 'h1{margin:0 0 12px}p{margin:10px 0}nav{display:flex;gap:12px;flex-wrap:wrap;margin-top:28px}'
            . 'a{display:inline-block;padding:10px 16px;border-radius:7px;background:#073d55;color:white;text-decoration:none}'
            . 'a.secondary{background:#e7eef2;color:#153448}</style></head><body><main><h1>' . $title . '</h1>'
            . '<p>Order #' . $id . ' · ' . $amount . '</p><p>' . $detail . '</p>' . $poll
            . '<nav><a href="' . $viewUrl . '">View Order</a><a class="secondary" href="' . $continueUrl
            . '">Continue Shopping</a></nav></main>' . $script . '</body></html>';
    }

    public function call_webhooks()
    {
        if (in_array(\Yii::$app->request->get('action'), ['return', 'status', 'order'], true)) {
            // Browser redirects are unauthenticated and are never payment proof.
            return $this->browserReturn();
        }
        $raw = file_get_contents('php://input') ?: '';
        try {
            $payload = (new PayxClient($this->settings()))->verifyWebhook($raw, $_SERVER);
        } catch (\Throwable $exception) {
            \Yii::warning('PayX webhook rejected: ' . $exception->getMessage(), 'payxcommerce');
            \Yii::$app->response->statusCode = 401;
            return 'Invalid webhook';
        }
        $eventId = (string) $_SERVER['HTTP_X_PXC_EVENT_ID'];
        $type = (string) ($payload['event_type'] ?? $payload['type'] ?? '');
        $db = \Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            $hash = hash('sha256', $raw);
            try {
                $db->createCommand()->insert('payxcommerce_event', [
                    'event_id' => $eventId, 'orders_id' => 0, 'payload_hash' => $hash, 'event_type' => $type,
                ])->execute();
            } catch (\yii\db\IntegrityException $duplicate) {
                $prior = $db->createCommand('SELECT payload_hash FROM payxcommerce_event WHERE event_id = :id', [':id' => $eventId])->queryOne();
                if (!$prior) {
                    throw $duplicate;
                }
                if (!hash_equals($prior['payload_hash'], $hash)) {
                    throw new \RuntimeException('Webhook event ID reused with a different body.');
                }
                $transaction->commit();
                return 'Duplicate acknowledged';
            }
            $request = self::field($payload, 'request_number')
                ?: (self::field($payload, 'payment_request_number') ?: self::field($payload, 'payment_request_reference'));
            $reference = self::field($payload, 'merchant_reference');
            $transactionReference = self::field($payload, 'transaction_reference');
            if ($request !== '') {
                $mapping = $db->createCommand('SELECT * FROM payxcommerce_order WHERE request_number = :ref FOR UPDATE', [':ref' => $request])->queryOne();
            } elseif ($transactionReference !== '') {
                $mapping = $db->createCommand('SELECT * FROM payxcommerce_order WHERE transaction_reference = :ref FOR UPDATE', [':ref' => $transactionReference])->queryOne();
            } else {
                throw new \RuntimeException('Webhook lacks a bound request or transaction reference.');
            }
            if (!$mapping || ($reference !== '' && $mapping['merchant_reference'] !== $reference)) {
                throw new \RuntimeException('Webhook cannot be bound to a saved PayX order.');
            }
            if (self::field($payload, 'environment') !== $mapping['environment']) {
                throw new \RuntimeException('Webhook payment mode does not match the saved order.');
            }
            if ($transactionReference !== '' && $mapping['transaction_reference'] !== null
                && $mapping['transaction_reference'] !== $transactionReference) {
                throw new \RuntimeException('Webhook transaction reference does not match the saved order.');
            }
            if (in_array($type, ['refund.success', 'refund.succeeded', 'payment.refunded'], true)) {
                $this->applyRefund($mapping, $payload, $eventId);
                $db->createCommand()->update('payxcommerce_event', ['orders_id' => $mapping['orders_id']], ['event_id' => $eventId])->execute();
                $transaction->commit();
                return 'OK';
            }
            if (in_array($type, ['payment.success', 'payment.succeeded'], true)) {
                // A gateway may charge in a different currency. Match the
                // merchant-requested amount, not the provider charge.
                $amount = self::field($payload, 'request_amount') ?: self::field($payload, 'amount');
                $currency = self::field($payload, 'request_currency') ?: self::field($payload, 'currency');
                $environment = self::field($payload, 'environment');
                if ($amount === '' || self::normalizeDecimal($amount) !== self::normalizeDecimal($mapping['amount']) || strtoupper($currency) !== $mapping['currency'] || $environment !== $mapping['environment']) {
                    throw new \RuntimeException('Webhook amount, currency, or mode does not match the order.');
                }
            }
            $status = match ($type) {
                'payment.success', 'payment.succeeded' => $this->status('SUCCESS'),
                'payment.failed' => $this->status('FAILED'),
                'payment.cancelled', 'payment.canceled', 'payment.expired' => $this->status('CANCELLED'),
                'refund.success', 'refund.succeeded', 'payment.refunded' => $this->status('REFUNDED'),
                'dispute.lost' => $this->status('CHARGEBACK'),
                default => 0,
            };
            $settledStates = ['paid', 'partially_refunded', 'refunded', 'chargeback'];
            if (in_array($mapping['payment_status'], $settledStates, true)
                && in_array($type, ['payment.failed', 'payment.cancelled', 'payment.canceled', 'payment.expired'], true)) {
                $status = 0; // A delayed failure/cancel must not undo settled state.
            }
            $state = match ($type) {
                'payment.success', 'payment.succeeded' => 'paid',
                'refund.success', 'refund.succeeded', 'payment.refunded' => 'refunded',
                'dispute.lost' => 'chargeback',
                default => $type,
            };
            if ($state === $mapping['payment_status']
                || (in_array($mapping['payment_status'], ['partially_refunded', 'refunded'], true) && $state === 'paid')
                || ($mapping['payment_status'] === 'chargeback' && in_array($state, ['paid', 'refunded'], true))) {
                $status = 0; // Different event IDs for the same/older state are also idempotent.
            }
            $ledgerStatus = match ($type) {
                'payment.success', 'payment.succeeded' => \common\helpers\OrderPayment::OPYS_SUCCESSFUL,
                'payment.failed' => \common\helpers\OrderPayment::OPYS_REFUSED,
                'payment.cancelled', 'payment.canceled', 'payment.expired' => \common\helpers\OrderPayment::OPYS_CANCELLED,
                'refund.success', 'refund.succeeded', 'payment.refunded' => \common\helpers\OrderPayment::OPYS_REFUNDED,
                default => null,
            };
            if ($ledgerStatus !== null && $status !== 0) {
                $changed = $db->createCommand()->update('orders_payment', [
                    'orders_payment_status' => $ledgerStatus,
                    'orders_payment_transaction_status' => $type,
                    'orders_payment_transaction_date' => date('Y-m-d H:i:s'),
                ], [
                    'orders_payment_order_id' => $mapping['orders_id'],
                    'orders_payment_module' => 'payxcommerce',
                    'orders_payment_transaction_id' => $mapping['request_number'],
                ])->execute();
                if ($changed !== 1) {
                    throw new \RuntimeException('Bound osCommerce payment ledger record was not updated.');
                }
            }
            if ($status > 0) {
                \common\helpers\Order::setStatus((int) $mapping['orders_id'], $status, [
                    'comments' => 'PayXCommerce event ' . $type . ' (' . $eventId . ')',
                    'customer_notified' => 0,
                ]);
                $actual = $db->createCommand('SELECT orders_status FROM orders WHERE orders_id = :id', [':id' => $mapping['orders_id']])->queryScalar();
                if ((int) $actual !== $status) {
                    throw new \RuntimeException('osCommerce order status did not persist.');
                }
            }
            if (in_array($type, ['payment.success', 'payment.succeeded'], true) && $transactionReference !== ''
                && $mapping['transaction_reference'] === null) {
                $db->createCommand()->update('payxcommerce_order', ['transaction_reference' => $transactionReference], ['orders_id' => $mapping['orders_id']])->execute();
            }
            if ($status > 0) {
                $db->createCommand()->update('payxcommerce_order', ['payment_status' => $state], ['orders_id' => $mapping['orders_id']])->execute();
            }
            $db->createCommand()->update('payxcommerce_event', ['orders_id' => $mapping['orders_id']], ['event_id' => $eventId])->execute();
            $transaction->commit();
            return 'OK';
        } catch (\Throwable $exception) {
            $transaction->rollBack();
            \Yii::error('PayX webhook processing failed: ' . $exception->getMessage(), 'payxcommerce');
            \Yii::$app->response->statusCode = 503;
            return 'Webhook processing failed';
        }
    }

    private static function field(array $payload, string $name): string
    {
        foreach ([$payload, $payload['data'] ?? [], $payload['payload'] ?? [], $payload['resource'] ?? []] as $source) {
            if (is_array($source) && isset($source[$name]) && is_scalar($source[$name])) {
                return (string) $source[$name];
            }
        }
        return '';
    }

    private function applyRefund(array $mapping, array $payload, string $eventId): void
    {
        if (!in_array($mapping['payment_status'], ['paid', 'partially_refunded', 'refunded'], true)) {
            throw new \RuntimeException('Refund arrived before the order was paid or after a terminal dispute.');
        }
        $refundReference = self::field($payload, 'refund_reference');
        $amount = self::field($payload, 'amount');
        $currency = strtoupper(self::field($payload, 'currency'));
        if (!preg_match('/^[A-Za-z0-9._:-]{1,191}$/D', $refundReference)
            || self::moneyCents($amount) <= 0 || $currency !== $mapping['currency']
            || self::field($payload, 'environment') !== $mapping['environment']) {
            throw new \RuntimeException('Refund reference, amount, currency, or mode is invalid for this order.');
        }
        $db = \Yii::$app->db;
        $debit = $db->createCommand("SELECT * FROM orders_payment WHERE orders_payment_order_id = :id
            AND orders_payment_module = 'payxcommerce' AND orders_payment_transaction_id = :ref
            AND orders_payment_is_credit = 0 FOR UPDATE", [
            ':id' => $mapping['orders_id'], ':ref' => $mapping['request_number'],
        ])->queryOne();
        if (!$debit || (int) $debit['orders_payment_status'] !== \common\helpers\OrderPayment::OPYS_SUCCESSFUL) {
            throw new \RuntimeException('Refund has no successful bound original payment.');
        }
        $existing = $db->createCommand("SELECT orders_payment_amount, orders_payment_currency FROM orders_payment
            WHERE orders_payment_order_id = :id AND orders_payment_module = 'payxcommerce'
            AND orders_payment_is_credit = 1 AND orders_payment_transaction_id = :ref LIMIT 1", [
            ':id' => $mapping['orders_id'], ':ref' => $refundReference,
        ])->queryOne();
        if ($existing) {
            if (self::moneyCents($existing['orders_payment_amount']) !== self::moneyCents($amount)
                || $existing['orders_payment_currency'] !== $currency) {
                throw new \RuntimeException('Refund reference was reused for a different amount or currency.');
            }
            return; // Another signed event for the same refund is not a second credit.
        }
        if ($mapping['payment_status'] === 'refunded') {
            throw new \RuntimeException('A fully refunded order cannot receive another refund.');
        }
        $prior = $db->createCommand("SELECT COALESCE(SUM(orders_payment_amount), 0) FROM orders_payment
            WHERE orders_payment_order_id = :id AND orders_payment_module = 'payxcommerce'
            AND orders_payment_is_credit = 1 AND orders_payment_id_parent = :parent", [
            ':id' => $mapping['orders_id'], ':parent' => $debit['orders_payment_id'],
        ])->queryScalar();
        $refundedCents = self::moneyCents((string) $prior) + self::moneyCents($amount);
        $orderCents = self::moneyCents((string) $mapping['amount']);
        if ($refundedCents > $orderCents) {
            throw new \RuntimeException('Cumulative refunds exceed the saved order payment.');
        }
        $db->createCommand()->insert('orders_payment', [
            'orders_payment_id_parent' => $debit['orders_payment_id'],
            'orders_payment_order_id' => $mapping['orders_id'],
            'orders_payment_module' => 'payxcommerce',
            'orders_payment_module_name' => $this->title,
            'orders_payment_is_credit' => 1,
            'orders_payment_status' => \common\helpers\OrderPayment::OPYS_REFUNDED,
            'orders_payment_amount' => $amount,
            'orders_payment_currency' => $currency,
            'orders_payment_currency_rate' => $debit['orders_payment_currency_rate'],
            'orders_payment_snapshot' => $debit['orders_payment_snapshot'],
            'orders_payment_transaction_id' => $refundReference,
            'orders_payment_transaction_status' => 'refund.success',
            'orders_payment_transaction_date' => date('Y-m-d H:i:s'),
        ])->execute();
        $full = $refundedCents === $orderCents;
        $newStatus = $this->status($full ? 'REFUNDED' : 'PARTIAL_REFUNDED');
        \common\helpers\Order::setStatus((int) $mapping['orders_id'], $newStatus, [
            'comments' => 'PayXCommerce refund ' . $refundReference . ' (' . $eventId . ')',
            'customer_notified' => 0,
        ]);
        $actual = $db->createCommand('SELECT orders_status FROM orders WHERE orders_id = :id', [':id' => $mapping['orders_id']])->queryScalar();
        if ((int) $actual !== $newStatus) {
            throw new \RuntimeException('Refunded osCommerce order status did not persist.');
        }
        $db->createCommand()->update('payxcommerce_order', [
            'payment_status' => $full ? 'refunded' : 'partially_refunded',
        ], ['orders_id' => $mapping['orders_id']])->execute();
    }

    private static function moneyCents(string $amount): int
    {
        if (!preg_match('/^(\d+)(?:\.(\d{1,6}))?$/', $amount, $parts)
            || trim(substr($parts[2] ?? '', 2), '0') !== '') {
            throw new \RuntimeException('Amount is not a valid two-decimal monetary value.');
        }
        $integer = ltrim($parts[1], '0') ?: '0';
        if (strlen($integer) > 13) {
            throw new \RuntimeException('Amount exceeds the osCommerce payment ledger range.');
        }
        return (int) $integer * 100 + (int) str_pad(substr($parts[2] ?? '', 0, 2), 2, '0');
    }

    public static function normalizeDecimal(string $value): ?string
    {
        if (!preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            return null;
        }
        $integer = ltrim($matches[1], '0') ?: '0';
        $fraction = rtrim($matches[2] ?? '', '0');
        return $integer . ($fraction === '' ? '' : '.' . $fraction);
    }

    /** Half-up currency rounding using decimal digits only; never binary floating point. */
    public static function roundDecimal(string $value, int $scale): string
    {
        if ($scale < 0 || $scale > 2 || !preg_match('/^(\d+)\.(\d{6}|\d{12})$/', $value, $matches)) {
            throw new \InvalidArgumentException('Invalid osCommerce decimal amount or currency scale.');
        }
        $digits = ltrim($matches[1], '0') . substr($matches[2], 0, $scale);
        $digits = ltrim($digits, '0') ?: '0';
        if ((int) $matches[2][$scale] >= 5) {
            $carry = 1;
            for ($i = strlen($digits) - 1; $i >= 0 && $carry; $i--) {
                $sum = (int) $digits[$i] + $carry;
                $digits[$i] = (string) ($sum % 10);
                $carry = intdiv($sum, 10);
            }
            if ($carry) $digits = '1' . $digits;
        }
        if ($scale === 0) return $digits;
        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        return substr($digits, 0, -$scale) . '.' . substr($digits, -$scale);
    }

    /** Multiply two saved DECIMAL(…,6) values without PHP floats or BCMath.
     * Round once, after multiplication, to avoid double-rounding a payment. */
    public static function convertOrderAmount(string $baseAmount, string $rate, int $scale): string
    {
        if (!preg_match('/^\d{1,9}\.\d{6}$/', $baseAmount)
            || !preg_match('/^\d{1,8}\.\d{6}$/', $rate)
            || self::normalizeDecimal($rate) === '0') {
            throw new \InvalidArgumentException('Invalid saved order total or conversion rate.');
        }
        $left = str_replace('.', '', $baseAmount);
        $right = str_replace('.', '', $rate);
        $digits = array_fill(0, strlen($left) + strlen($right), 0);
        for ($i = strlen($left) - 1; $i >= 0; $i--) {
            $carry = 0;
            for ($j = strlen($right) - 1; $j >= 0; $j--) {
                $index = $i + $j + 1;
                $sum = $digits[$index] + (int) $left[$i] * (int) $right[$j] + $carry;
                $digits[$index] = $sum % 10;
                $carry = intdiv($sum, 10);
            }
            $digits[$i] += $carry;
        }
        $product = str_pad(ltrim(implode('', $digits), '0') ?: '0', 13, '0', STR_PAD_LEFT);
        return self::roundDecimal(substr($product, 0, -12) . '.' . substr($product, -12), $scale);
    }

    /** Determine the selected-currency amount from the saved order snapshot.
     * Do not consult current FX rates when preparing an already-saved order. */
    private static function payableAmountForSavedOrder(array $order, array $total, int $scale, bool $marketPrices): string
    {
        $baseAmount = (string) ($total['value_inc_tax'] ?? '');
        $currency = (string) ($order['currency'] ?? '');
        $rate = (string) ($order['currency_value'] ?? '');
        $totalRate = self::normalizeDecimal((string) ($total['currency_value'] ?? ''));
        if (!preg_match('/^\d{1,9}\.\d{6}$/', $baseAmount)
            || !preg_match('/^[A-Z]{3}$/', $currency)
            || ($total['currency'] ?? null) !== $currency
            || $totalRate === null || $totalRate !== self::normalizeDecimal($rate)) {
            throw new \RuntimeException('Order currency or exact decimal total is unavailable.');
        }
        // osCommerce's selected-currency display multiplies base totals by
        // the order rate unless market pricing already supplied local amounts.
        $amount = $marketPrices ? self::roundDecimal($baseAmount, $scale)
            : self::convertOrderAmount($baseAmount, $rate, $scale);
        if (strlen(explode('.', $amount, 2)[0]) > 9) {
            throw new \RuntimeException('Converted order total exceeds the osCommerce payment ledger range.');
        }
        if (self::normalizeDecimal($amount) === '0') {
            throw new \RuntimeException('Zero-value orders cannot use hosted checkout.');
        }
        return $amount;
    }

    private function status(string $kind): int
    {
        $key = 'MODULE_PAYMENT_PAYXCOMMERCE_' . $kind . '_STATUS_ID';
        return defined($key) ? (int) constant($key) : 0;
    }

    private function environment(): string
    {
        return defined('MODULE_PAYMENT_PAYXCOMMERCE_ENVIRONMENT') && MODULE_PAYMENT_PAYXCOMMERCE_ENVIRONMENT === 'live' ? 'live' : 'test';
    }

    private function configured(): bool
    {
        if (!defined('MODULE_PAYMENT_PAYXCOMMERCE_WEBHOOK_SECRET') || $this->decryptConst('MODULE_PAYMENT_PAYXCOMMERCE_WEBHOOK_SECRET') === '') {
            return false;
        }
        try {
            new PayxClient($this->settings());
        } catch (\InvalidArgumentException $exception) {
            return false;
        }
        $method = defined('MODULE_PAYMENT_PAYXCOMMERCE_AUTH_METHOD') ? MODULE_PAYMENT_PAYXCOMMERCE_AUTH_METHOD : 'hmac';
        if (!in_array($method, ['hmac', 'bearer'], true)) {
            return false;
        }
        if ($method === 'bearer') {
            return defined('MODULE_PAYMENT_PAYXCOMMERCE_CLIENT_ID') && MODULE_PAYMENT_PAYXCOMMERCE_CLIENT_ID !== ''
                && $this->decryptConst('MODULE_PAYMENT_PAYXCOMMERCE_CLIENT_SECRET') !== '';
        }
        $prefix = $this->environment() === 'test' ? 'pk_test_' : 'pk_live_';
        return defined('MODULE_PAYMENT_PAYXCOMMERCE_PUBLIC_KEY')
            && str_starts_with(MODULE_PAYMENT_PAYXCOMMERCE_PUBLIC_KEY, $prefix)
            && $this->decryptConst('MODULE_PAYMENT_PAYXCOMMERCE_SECRET_KEY') !== '';
    }

    private function settings(): array
    {
        return [
            'base_url' => defined('MODULE_PAYMENT_PAYXCOMMERCE_API_URL') ? MODULE_PAYMENT_PAYXCOMMERCE_API_URL : '',
            'environment' => $this->environment(),
            'auth_method' => defined('MODULE_PAYMENT_PAYXCOMMERCE_AUTH_METHOD') ? MODULE_PAYMENT_PAYXCOMMERCE_AUTH_METHOD : 'hmac',
            'public_key' => defined('MODULE_PAYMENT_PAYXCOMMERCE_PUBLIC_KEY') ? MODULE_PAYMENT_PAYXCOMMERCE_PUBLIC_KEY : '',
            'secret_key' => $this->decryptConst('MODULE_PAYMENT_PAYXCOMMERCE_SECRET_KEY'),
            'client_id' => defined('MODULE_PAYMENT_PAYXCOMMERCE_CLIENT_ID') ? MODULE_PAYMENT_PAYXCOMMERCE_CLIENT_ID : '',
            'client_secret' => $this->decryptConst('MODULE_PAYMENT_PAYXCOMMERCE_CLIENT_SECRET'),
            'webhook_secret' => $this->decryptConst('MODULE_PAYMENT_PAYXCOMMERCE_WEBHOOK_SECRET'),
        ];
    }

    public function install($platform_id)
    {
        \Yii::$app->db->createCommand("CREATE TABLE IF NOT EXISTS payxcommerce_order (
            orders_id INT NOT NULL PRIMARY KEY, platform_id INT NOT NULL, merchant_reference VARCHAR(191) NOT NULL UNIQUE,
            idempotency_key VARCHAR(191) NOT NULL, request_number VARCHAR(191) NULL UNIQUE,
            transaction_reference VARCHAR(191) NULL UNIQUE,
            invoice_number VARCHAR(191) NULL, checkout_url TEXT NULL, amount DECIMAL(15,6) NOT NULL,
            currency CHAR(3) NOT NULL, environment VARCHAR(8) NOT NULL, payment_status VARCHAR(40) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")->execute();
        \Yii::$app->db->createCommand("CREATE TABLE IF NOT EXISTS payxcommerce_event (
            event_id VARCHAR(191) NOT NULL PRIMARY KEY, orders_id INT NOT NULL, payload_hash CHAR(64) NOT NULL,
            event_type VARCHAR(80) NOT NULL, processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (orders_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")->execute();
        return parent::install($platform_id);
    }

    public function describe_status_key() { return new ModuleStatus('MODULE_PAYMENT_PAYXCOMMERCE_STATUS', 'True', 'False'); }
    public function describe_sort_key() { return new ModuleSortOrder('MODULE_PAYMENT_PAYXCOMMERCE_SORT_ORDER'); }

    public function check($platform_id)
    {
        // The core uses the first displayed key as the installation sentinel.
        // Express must appear first in settings, but older installs only have
        // STATUS. Detect by STATUS and let the normal module upgrader add new keys.
        $platformId = (int) $platform_id;
        if ($platformId <= 0) {
            return 0;
        }
        $keys = $this->configure_keys();
        $existing = \Yii::$app->db->createCommand(
            'SELECT configuration_key FROM platforms_configuration WHERE platform_id = :platform AND configuration_key LIKE :prefix',
            [':platform' => $platformId, ':prefix' => 'MODULE_PAYMENT_PAYXCOMMERCE_%']
        )->queryColumn();
        if (!in_array('MODULE_PAYMENT_PAYXCOMMERCE_STATUS', $existing, true)) {
            return 0;
        }
        foreach ($keys as $key => $data) {
            if (!in_array($key, $existing, true)) {
                if ($key === 'MODULE_PAYMENT_PAYXCOMMERCE_EXPRESS_CHECKOUT') {
                    // Upgrades do not silently change an existing store's cart.
                    // New installs use configure_keys() and default to True.
                    $data['value'] = 'False';
                }
                $this->add_config_key($platformId, $key, $data);
            }
        }
        foreach (['PENDING', 'SUCCESS', 'FAILED', 'CANCELLED', 'PARTIAL_REFUNDED', 'REFUNDED', 'CHARGEBACK'] as $kind) {
            $key = 'MODULE_PAYMENT_PAYXCOMMERCE_' . $kind . '_STATUS_ID';
            \Yii::$app->db->createCommand()->update('platforms_configuration',
                ['set_function' => 'selectOrderStatus('],
                ['platform_id' => $platformId, 'configuration_key' => $key,
                    'set_function' => 'tep_cfg_pull_down_order_statuses(']
            )->execute();
        }
        return 1;
    }

    public function configure_keys()
    {
        $keys = [
            'EXPRESS_CHECKOUT' => ['Enable PayXCommerce express checkout', 'True', 'Show a cart shortcut that preselects PayXCommerce and completes the normal checkout steps.'],
            'STATUS' => ['Enable PayXCommerce', 'False', 'Enable hosted checkout only after credentials, webhook secret, and status IDs are configured.'],
            'ENVIRONMENT' => ['Payment mode', 'test', 'Use test or live credentials matching this mode.'],
            'API_URL' => ['PayX API URL', 'https://payxcommerce.com/api/v1', 'HTTPS PayX API root. Use the approved DEV endpoint only on a DEV test store.'],
            'AUTH_METHOD' => ['API authentication method', 'hmac', 'HMAC API keys or Developer App bearer credentials.'],
            'PUBLIC_KEY' => ['API public key', '', 'pk_test_ or pk_live_ key matching mode.'],
            'SECRET_KEY' => ['API secret key', '', 'HMAC secret; stored encrypted.'],
            'CLIENT_ID' => ['Developer App client ID', '', 'Used only with bearer authentication.'],
            'CLIENT_SECRET' => ['Developer App client secret', '', 'Stored encrypted; used only with bearer authentication.'],
            'WEBHOOK_SECRET' => ['Webhook signing secret', '', 'Required for verified payment status; stored encrypted.'],
            'PENDING_STATUS_ID' => ['Pending order status', '0', 'Status before redirect.'],
            'SUCCESS_STATUS_ID' => ['Paid order status', '0', 'Status for signed payment success.'],
            'FAILED_STATUS_ID' => ['Failed order status', '0', 'Status for signed failure.'],
            'CANCELLED_STATUS_ID' => ['Cancelled order status', '0', 'Status for signed cancellation/expiry.'],
            'PARTIAL_REFUNDED_STATUS_ID' => ['Partially refunded order status', '0', 'Status after a signed partial refund.'],
            'REFUNDED_STATUS_ID' => ['Refunded order status', '0', 'Status for signed refund success.'],
            'CHARGEBACK_STATUS_ID' => ['Chargeback order status', '0', 'Status for signed dispute loss.'],
            'SORT_ORDER' => ['Display sort order', '1', 'Lower numbers appear first.'],
        ];
        $result = [];
        foreach ($keys as $name => [$title, $value, $description]) {
            $key = 'MODULE_PAYMENT_PAYXCOMMERCE_' . $name;
            $result[$key] = ['title' => $title, 'value' => $value, 'description' => $description];
            if (in_array($name, ['SECRET_KEY', 'CLIENT_SECRET', 'WEBHOOK_SECRET'], true)) {
                $result[$key]['set_function'] = 'setConf(';
                $result[$key]['use_function'] = '\\common\\classes\\modules\\Module::useConf';
            } elseif (str_ends_with($name, '_STATUS_ID')) {
                $result[$key]['set_function'] = 'selectOrderStatus(';
                $result[$key]['use_function'] = '\\common\\helpers\\Order::get_order_status_name';
            } elseif ($name === 'STATUS' || $name === 'EXPRESS_CHECKOUT') {
                $result[$key]['set_function'] = "tep_cfg_select_option(array('True', 'False'), ";
            } elseif ($name === 'ENVIRONMENT') {
                $result[$key]['set_function'] = "tep_cfg_select_option(array('test', 'live'), ";
            } elseif ($name === 'AUTH_METHOD') {
                $result[$key]['set_function'] = "tep_cfg_select_option(array('hmac', 'bearer'), ";
            }
        }
        return $result;
    }
}
