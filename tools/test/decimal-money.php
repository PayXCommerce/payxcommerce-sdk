<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require_once $root . '/plugins/woocommerce/includes/Support/Decimal.php';
require_once $root . '/plugins/magento2/Model/Decimal.php';
require_once $root . '/plugins/opencart3/upload/system/library/payxcommerce_decimal.php';
require_once $root . '/plugins/opencart4/upload/extension/payxcommerce/system/library/payxcommerce_decimal.php';

$implementations = [
    'WooCommerce' => \PayXCommerce\WooCommerce\Support\Decimal::class,
    'Magento' => \PayXCommerce\Payment\Model\Decimal::class,
    'OpenCart 3' => \PayXCommerceDecimal::class,
    'OpenCart 4' => \Opencart\System\Library\PayxcommerceDecimal::class,
];

$comparisons = [
    ['0.1', '0.10000000000000001', -1],
    ['9007199254740993.01', '9007199254740993.00', 1],
    ['10.000', '10', 0],
    ['-0.0001', '0', -1],
    ['0.00000000000000000001', '0', 1],
    ['00042.5000', '+42.5', 0],
];

$tests = 0;
foreach ($implementations as $label => $class) {
    foreach ($comparisons as [$left, $right, $expected]) {
        $actual = $class::compare($left, $right);
        if ($actual !== $expected) {
            throw new RuntimeException("{$label} compared {$left} to {$right} as " . var_export($actual, true) . "; expected {$expected}.");
        }
        $tests++;
    }

    foreach (['1e-8', '1,000.00', '', 'NaN', '--1'] as $invalid) {
        if ($class::isValid($invalid)) {
            throw new RuntimeException("{$label} accepted invalid decimal {$invalid}.");
        }
        $tests++;
    }

    if (!$class::isPositive('0.00000000000000000001') || $class::isPositive('0.000')) {
        throw new RuntimeException("{$label} positive-value test is not exact.");
    }
    $tests += 2;
}

echo "Exact decimal money contracts passed ({$tests} assertions).\n";
