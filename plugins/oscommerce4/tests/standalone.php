<?php

declare(strict_types=1);

namespace common\classes\modules {
    class ModulePayment {}
    class ModuleSortOrder {}
    class ModuleStatus {}
}

namespace common\components {
    class View { public const POS_END = 3; }
}

namespace common\classes {
    class platform { public static function currentId(): int { return 7; } }
}

namespace yii\web {
    class Response { public const FORMAT_HTML = 'html'; public const FORMAT_JSON = 'json'; }
}

namespace {
    const FILENAME_DEFAULT = 'index';
    const FILENAME_CHECKOUT_SUCCESS = 'checkout/success';
    function tep_href_link(string $path, string $params = '', string $mode = ''): string
    {
        return '/store/' . $path . ($params !== '' ? '?' . $params : '');
    }
    class RedirectProbe extends RuntimeException {}
    function tep_redirect(string $url): never { throw new RedirectProbe($url); }

    class Yii {
        public static object $app;
        public static function getAlias(string $alias): string { return '/store'; }
    }

    class ButtonTestView {
        public array $css = [];
        public array $js = [];
        public function registerCss(string $css, array $options = [], ?string $key = null): void { $this->css[$key] = $css; }
        public function registerJs(string $js, ?int $position = null, ?string $key = null): void { $this->js[$key] = $js; }
    }

    require __DIR__ . '/../upload/lib/common/modules/orderPayment/payxcommerce.php';

    use common\modules\orderPayment\lib\PayxClient;
    use common\modules\orderPayment\payxcommerce;

    function check(bool $condition, string $name): void
    {
        if (!$condition) throw new RuntimeException($name);
        echo "PASS $name\n";
    }

    function rejects(callable $operation, string $name): void
    {
        try { $operation(); } catch (Throwable $exception) {
            echo "PASS $name\n";
            return;
        }
        throw new RuntimeException($name . ' was not rejected');
    }

    $module = (new ReflectionClass(payxcommerce::class))->newInstanceWithoutConstructor();
    $billingCountry = new ReflectionMethod(payxcommerce::class, 'checkoutBillingCountry');
    $module->manager = new class {
        public array $values = [];
        public function getBillingAddress(): array { return ['country' => ['iso_code_2' => 'GB']]; }
        public function set(string $key, string $value): void { $this->values[$key] = $value; }
    };
    check($billingCountry->invoke($module) === 'GB', 'current checkout billing ISO code drives PayX country eligibility');
    $module->enabled = true;
    rejects(function () use ($module) {
        $module->manager = new class {
            public array $values = [];
            public function getBillingAddress(): array { return []; }
            public function set(string $key, string $value): void { $this->values[$key] = $value; }
        };
        $module->before_process();
    }, 'missing billing country is stopped before order persistence');
    check(str_contains($module->manager->values['payxcommerce_checkout_error'] ?? '', 'No order was placed'),
        'missing billing-country checkout error is explicit');
    $configuration = $module->configure_keys();
    check(array_key_first($configuration) === 'MODULE_PAYMENT_PAYXCOMMERCE_EXPRESS_CHECKOUT', 'express toggle first in settings');
    check($configuration['MODULE_PAYMENT_PAYXCOMMERCE_EXPRESS_CHECKOUT']['value'] === 'True', 'new-install express default on');
    check($configuration['MODULE_PAYMENT_PAYXCOMMERCE_SORT_ORDER']['value'] === '1', 'new-install sort default one');
    check($configuration['MODULE_PAYMENT_PAYXCOMMERCE_REFUNDED_STATUS_ID']['set_function'] === 'selectOrderStatus(',
        'status selector includes ungrouped statuses');

    $buttonRenderer = new ReflectionMethod(payxcommerce::class, 'expressButtonHtml');
    $button = $buttonRenderer->invoke(null, '/store/checkout?payxcommerce_express=1&guest=1',
        '/store/images/payxcommerce/logo-icon-dark-64.png');
    check(str_contains($button, '<style>') && str_contains($button, '</style>')
        && str_contains($button, 'a.payxcommerce-express-button{'), 'button styles stay with scoped markup');
    check(str_contains($button, 'height:50px') && str_contains($button, 'border:none')
        && str_contains($button, 'width:100%'), 'PayPal-sized button without border');
    check(str_contains($button, 'a.payxcommerce-express-button .payxcommerce-text{position:relative;z-index:1;letter-spacing:-.2px;color:#ffffff}'),
        'button text is explicitly white');
    check(str_contains($button, 'href="/store/checkout?payxcommerce_express=1&amp;guest=1"')
        && str_contains($button, 'Pay with PayXCommerce'), 'express checkout destination and text retained');
    check(str_contains($button, 'src="/store/images/payxcommerce/logo-icon-dark-64.png"')
        && !str_contains($button, 'https://payxcommerce.com'), 'bundled relative icon URL');
    check(str_contains($button, 'aria-hidden="true"') && str_contains($button, 'prefers-reduced-motion'),
        'decorative wave and reduced-motion support');
    $escapedButton = $buttonRenderer->invoke(null, '/checkout?next=" onmouseover="bad', '/images/icon" onerror="bad');
    check(!str_contains($escapedButton, 'onmouseover="bad') && !str_contains($escapedButton, 'onerror="bad'),
        'express button attributes escaped');
    check(is_file(__DIR__ . '/../upload/images/payxcommerce/logo-icon-dark-64.png'), 'bundled express icon exists');

    $view = new ButtonTestView();
    Yii::$app = new class($view) {
        public function __construct(private ButtonTestView $view) {}
        public function getView(): ButtonTestView { return $this->view; }
    };
    check($module->selection() === ['id' => 'payxcommerce', 'module' => 'Pay securely with PayXCommerce'],
        'checkout payment selection retained');
    $checkoutCss = $view->css['payxcommerce-checkout-button'] ?? '';
    $checkoutJs = $view->js['payxcommerce-checkout-button'] ?? '';
    check(str_contains($checkoutCss, '#frmCheckout .w-checkout-continue-btn button.payxcommerce-checkout-button')
        && str_contains($checkoutCss, 'height:50px') && str_contains($checkoutCss, 'border:none')
        && !str_contains($checkoutCss, 'a.payxcommerce-express-button'), 'checkout styles scoped to selected submit button');
    check(str_contains($checkoutCss,
        '#frmCheckout .w-checkout-continue-btn button.payxcommerce-checkout-button{display:flex!important}'),
        'PayX button keeps flex layout after PayPal restores inline display');
    check(str_contains($checkoutJs, 'input[name="payment"]:checked')
        && str_contains($checkoutJs, 'Pay with PayXCommerce')
        && str_contains($checkoutJs, '/store/images/payxcommerce/logo-icon-dark-64.png')
        && str_contains($checkoutJs, 'originalMarkup.get(button)'), 'checkout label, relative icon and other payment restoration');

    check(payxcommerce::roundDecimal('699.782609', 2) === '699.78', 'currency rounding down');
    check(payxcommerce::roundDecimal('699.785000', 2) === '699.79', 'currency rounding half up');
    check(payxcommerce::roundDecimal('0.005000', 2) === '0.01', 'sub-cent rounding');
    check(payxcommerce::roundDecimal('9999999999999.995000', 2) === '10000000000000.00', 'decimal carry');
    check(payxcommerce::convertOrderAmount('873.695652', '1.396950', 2) === '1220.51',
        'saved GBP-base order converts to displayed USD payable amount');
    check(payxcommerce::convertOrderAmount('873.695652', '1.000000', 2) === '873.70',
        'base currency keeps the same rounded payable amount');
    check(payxcommerce::convertOrderAmount('0.004999', '1.000000', 2) === '0.00'
        && payxcommerce::convertOrderAmount('0.005000', '1.000000', 2) === '0.01',
        'conversion rounds once without sub-cent double rounding');
    check(payxcommerce::convertOrderAmount('1.250000', '2.000000', 0) === '3',
        'zero-decimal currency rounds converted amount once');
    rejects(fn () => payxcommerce::convertOrderAmount('1.000000', '0.000000', 2), 'zero order rate');
    rejects(fn () => payxcommerce::convertOrderAmount('1.000000', '-1.000000', 2), 'negative order rate');
    $payable = new ReflectionMethod(payxcommerce::class, 'payableAmountForSavedOrder');
    $savedOrder = ['currency' => 'USD', 'currency_value' => '1.396950'];
    $savedTotal = ['value_inc_tax' => '873.695652', 'currency' => 'USD', 'currency_value' => '1.396950'];
    check($payable->invoke(null, $savedOrder, $savedTotal, 2, false) === '1220.51',
        'hosted request uses displayed selected-currency order snapshot');
    check($payable->invoke(null, $savedOrder, $savedTotal, 2, true) === '873.70',
        'market-pricing order does not convert its already local amount');
    rejects(fn () => $payable->invoke(null, $savedOrder, array_replace($savedTotal, ['currency' => 'GBP']), 2, false),
        'mismatched saved total currency');
    rejects(fn () => $payable->invoke(null, $savedOrder, array_replace($savedTotal, ['currency_value' => '1.400000']), 2, false),
        'mismatched saved conversion rate');
    rejects(fn () => $payable->invoke(null, $savedOrder, array_replace($savedTotal, ['value_inc_tax' => '999999999.000000']), 2, false),
        'converted amount beyond payment ledger range');
    check(payxcommerce::normalizeDecimal('000699.780000') === '699.78', 'canonical comparison');
    rejects(fn () => payxcommerce::roundDecimal('1.000001', 3), 'unsupported currency scale');
    $cents = new ReflectionMethod(payxcommerce::class, 'moneyCents');
    check($cents->invoke(null, '699.780000') === 69978, 'database decimal cents');
    rejects(fn () => $cents->invoke(null, '1.001000'), 'fractional-cent rejection');

    $customerPayload = new ReflectionMethod(payxcommerce::class, 'customerPayload');
    $customer = [
        'customers_name' => 'Synthetic Buyer', 'customers_email_address' => 'buyer@example.test',
        'customers_telephone' => '', 'billing_street_address' => '1 Test Street', 'billing_city' => 'Test City',
    ];
    check(!array_key_exists('mobile', $customerPayload->invoke(null, $customer, 'US')),
        'optional blank phone omitted from PayX customer payload');
    check($customerPayload->invoke(null, array_replace($customer, ['customers_telephone' => ' +12025550100 ']), 'US')['mobile'] === '+12025550100',
        'provided phone retained and trimmed for PayX customer payload');

    rejects(fn () => new PayxClient(['base_url' => 'http://payxcommerce.com/api/v1']), 'insecure API URL');
    rejects(fn () => new PayxClient(['base_url' => 'https://user@payxcommerce.com/api/v1']), 'credential-bearing API URL');
    $countryRestriction = new ReflectionMethod(PayxClient::class, 'isCountryRestrictionResponse');
    $countryDenied = json_encode(['error_code' => 'payment_request_limit_failed',
        'message' => 'Customer country is not allowed for this merchant.']);
    check($countryRestriction->invoke(null, 422, $countryDenied), 'merchant billing-country denial has safe checkout classification');
    check(!$countryRestriction->invoke(null, 422, json_encode(['error_code' => 'payment_request_limit_failed',
        'message' => 'Amount exceeds merchant limit.']))
        && !$countryRestriction->invoke(null, 403, $countryDenied)
        && !$countryRestriction->invoke(null, 422, '{invalid'),
        'other API failures and invalid responses never get country-denial classification');
    $failureMessage = new ReflectionMethod(payxcommerce::class, 'checkoutFailureMessage');
    $countryMessage = $failureMessage->invoke(null, 21,
        new \common\modules\orderPayment\lib\PayxCountryRestrictionException('Billing country is restricted.'));
    check(str_contains($countryMessage, 'Order #21') && str_contains($countryMessage, 'billing country')
        && str_contains($countryMessage, 'No payment was taken'), 'checkout explains denied billing country and saved order');
    $otherMessage = $failureMessage->invoke(null, 21, new RuntimeException('Sensitive provider response'));
    check(str_contains($otherMessage, 'Order #21') && !str_contains($otherMessage, 'Sensitive provider response'),
        'checkout keeps other provider failures generic');
    $client = new PayxClient(['base_url' => 'https://dev.payxcommerce.com/api/v1', 'webhook_secret' => 'test-only-signing-key']);
    $eventId = 'PXEV-test-1';
    $body = '{"event_type":"payment.success","environment":"test"}';
    $time = (string) time();
    $headers = [
        'HTTP_X_PXC_EVENT_ID' => $eventId,
        'HTTP_X_PXC_TIMESTAMP' => $time,
        'HTTP_X_PXC_SIGNATURE' => hash_hmac('sha256', $eventId . '.' . $body, 'test-only-signing-key'),
    ];
    check($client->verifyWebhook($body, $headers)['event_type'] === 'payment.success', 'signed webhook');
    rejects(fn () => $client->verifyWebhook($body . ' ', $headers), 'tampered webhook');
    rejects(fn () => $client->verifyWebhook($body, array_replace($headers, [
        'HTTP_X_PXC_TIMESTAMP' => (string) (time() - 301),
    ])), 'stale webhook');
    rejects(fn () => $client->verifyWebhook($body, array_replace($headers, [
        'HTTP_X_PXC_EVENT_ID' => 'bad event id',
    ])), 'unsafe event identifier');

    $tokenMethod = new ReflectionMethod(payxcommerce::class, 'returnToken');
    $token = $tokenMethod->invoke(null, 20, 'test-only-idempotency-key');
    check(strlen($token) === 64 && ctype_xdigit($token), 'guest return token is a fixed-size HMAC');
    check($token !== $tokenMethod->invoke(null, 21, 'test-only-idempotency-key')
        && $token !== $tokenMethod->invoke(null, 20, 'other-test-key'), 'guest return token is order and key bound');
    $stateMethod = new ReflectionMethod(payxcommerce::class, 'browserPaymentState');
    check($stateMethod->invoke(null, 'creating') === 'pending'
        && $stateMethod->invoke(null, 'paid') === 'paid'
        && $stateMethod->invoke(null, 'failed') === 'failed'
        && $stateMethod->invoke(null, 'cancelled') === 'cancelled', 'browser state follows verified backend state');

    $orderRow = [
        'orders_id' => 20, 'platform_id' => 7, 'order_platform_id' => 7,
        'idempotency_key' => 'test-only-idempotency-key', 'request_number' => 'PXRQ-TEST-20',
        'amount' => '55.880000', 'currency' => 'USD', 'payment_status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'), 'customers_id' => 30, 'customers_name' => 'Private Buyer',
        'payment_class' => 'payxcommerce',
    ];
    $request = new class {
        public array $query = [];
        public function get(string $key, $default = null) { return $this->query[$key] ?? $default; }
    };
    $user = new class {
        public bool $isGuest = true;
        public int $id = 30;
        public function getId(): int { return $this->id; }
    };
    $database = new class($orderRow) {
        public function __construct(public array $row) {}
        public array $items = [
            ['products_name' => 'Test item <private>', 'products_quantity' => '2'],
        ];
        public function createCommand(string $sql, array $params): object
        {
            return new class($this, $sql, $params) {
                public function __construct(private object $db, private string $sql, private array $params) {}
                public function queryOne(): array|false
                {
                    return (int) $this->params[':id'] === (int) $this->db->row['orders_id'] ? $this->db->row : false;
                }
                public function queryAll(): array
                {
                    if (!str_contains($this->sql, 'FROM orders_products')
                        || (int) $this->params[':id'] !== (int) $this->db->row['orders_id']) return [];
                    return $this->db->items;
                }
            };
        }
    };
    $headers = new class {
        public array $values = [];
        public function set(string $name, string $value): void { $this->values[$name] = $value; }
    };
    $response = new class($headers) {
        public int $statusCode = 200;
        public ?string $format = null;
        public function __construct(public object $headers) {}
    };
    Yii::$app = (object) compact('request', 'user', 'database', 'response');
    Yii::$app->db = $database;
    $request->query = ['action' => 'return', 'orders_id' => '20', 'token' => $token];
    $browserOrder = new ReflectionMethod(payxcommerce::class, 'browserOrder');
    check($browserOrder->invoke($module)['browser_state'] === 'pending', 'guest return capability grants only its bound order');
    $request->query['token'] = str_repeat('0', 64);
    check($browserOrder->invoke($module) === null, 'wrong guest token denies order status');
    $request->query['orders_id'] = '21';
    check($browserOrder->invoke($module) === null, 'cross-order access denied');
    $request->query['orders_id'] = ['20'];
    check($browserOrder->invoke($module) === null, 'array-valued order identifier denied');
    $request->query = ['action' => 'return', 'orders_id' => '20', 'token' => $token];
    $database->row['created_at'] = date('Y-m-d H:i:s', time() - 8 * 86400);
    check($browserOrder->invoke($module) === null, 'expired guest token denied');
    $database->row['created_at'] = date('Y-m-d H:i:s');
    $user->isGuest = false;
    $request->query['token'] = '';
    check($browserOrder->invoke($module)['owner'] === true, 'order owner can use legacy token-free return');
    $user->id = 31;
    check($browserOrder->invoke($module) === null, 'other customer cannot use legacy return');

    $user->isGuest = true;
    $request->query['token'] = $token;
    $render = new ReflectionMethod(payxcommerce::class, 'browserReturn');
    $pending = $render->invoke($module);
    check(str_contains($pending, 'Confirming payment') && str_contains($pending, 'Order #20')
        && str_contains($pending, 'Checking for confirmation') && str_contains($pending, 'action=status'),
        'guest pending page polls read-only backend state');
    $request->query['action'] = 'order';
    $pendingDetails = $render->invoke($module);
    check(str_contains($pendingDetails, 'Awaiting payment confirmation')
        && !str_contains($pendingDetails, 'Order #20 · Paid'), 'pending order details never claim payment');
    $request->query['action'] = 'return';
    $database->row['payment_status'] = 'paid';
    $paid = $render->invoke($module);
    check(str_contains($paid, 'Payment confirmed') && str_contains($paid, 'View Order')
        && str_contains($paid, 'Continue Shopping') && !str_contains($paid, 'Checking for confirmation'),
        'guest confirmed page shows order links only after verified state');
    $request->query['action'] = 'order';
    $details = $render->invoke($module);
    check(str_contains($details, '<h1>Order details</h1>') && str_contains($details, 'Test item &lt;private&gt;')
        && str_contains($details, 'Qty 2') && str_contains($details, 'Total: USD 55.88')
        && str_contains($details, 'Order #20 · Paid') && !str_contains($details, 'Payment confirmed')
        && !str_contains($details, 'Private Buyer')
        && $details !== $paid, 'guest View Order opens distinct escaped order details');
    $request->query['token'] = str_repeat('0', 64);
    $response->statusCode = 200;
    check($render->invoke($module) === 'Order not found or return access expired.'
        && $response->statusCode === 404, 'guest order details reject invalid capability');
    $request->query['token'] = $token;
    $database->row['created_at'] = date('Y-m-d H:i:s', time() - 8 * 86400);
    $response->statusCode = 200;
    check($render->invoke($module) === 'Order not found or return access expired.'
        && $response->statusCode === 404, 'guest order details reject expired capability');
    $database->row['created_at'] = date('Y-m-d H:i:s');
    $user->isGuest = false;
    $user->id = 30;
    $request->query['token'] = '';
    try {
        $render->invoke($module);
        throw new RuntimeException('owned order details did not redirect');
    } catch (RedirectProbe $redirect) {
        check($redirect->getMessage() === '/store/account/history-info?order_id=20',
            'owner View Order redirects to native account history');
    }
    $request->query['action'] = 'return';
    try {
        $render->invoke($module);
        throw new RuntimeException('owned paid order did not redirect');
    } catch (RedirectProbe $redirect) {
        check($redirect->getMessage() === '/store/checkout/success?order_id=20',
            'owned paid order redirects to native success page');
    }
    $user->isGuest = true;
    $request->query['token'] = $token;
    $database->row['payment_status'] = 'failed';
    $failed = $render->invoke($module);
    check(str_contains($failed, 'Payment failed') && !str_contains($failed, 'Payment confirmed'),
        'failed payment never renders success');
    $request->query['action'] = 'status';
    check($render->invoke($module) === ['state' => 'failed'], 'status endpoint returns backend state only');
    check(($headers->values['Cache-Control'] ?? '') === 'no-store, private'
        && ($headers->values['Referrer-Policy'] ?? '') === 'no-referrer', 'return capability is not cached or leaked by referrer');
}
