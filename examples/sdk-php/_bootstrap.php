<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'PayXCommerce\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = __DIR__ . '/../../packages/php-sdk/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($path)) {
        require $path;
    }
});


function payx_print_sdk_exception(\PayXCommerce\Exceptions\ApiException $exception): void
{
    fwrite(STDERR, 'PayXCommerce API error: ' . $exception->getMessage() . PHP_EOL);
    if ($exception->statusCode() !== null) {
        fwrite(STDERR, 'HTTP status: ' . $exception->statusCode() . PHP_EOL);
    }
    if ($exception->payxErrorCode() !== null) {
        fwrite(STDERR, 'Error code: ' . $exception->payxErrorCode() . PHP_EOL);
    }
    if ($exception->errors()) {
        fwrite(STDERR, 'Validation details:' . PHP_EOL);
        foreach ($exception->errors() as $field => $messages) {
            foreach ((array) $messages as $message) {
                fwrite(STDERR, ' - ' . $field . ': ' . $message . PHP_EOL);
            }
        }
    }
    exit(1);
}
