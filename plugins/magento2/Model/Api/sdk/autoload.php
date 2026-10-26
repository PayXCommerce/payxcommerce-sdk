<?php
declare(strict_types=1);

// Standalone CMS distribution; Composer users retain normal PSR-4 loading.
spl_autoload_register(static function (string $class): void {
    $prefix = 'PayXCommerce\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    if (!preg_match('/^[A-Za-z0-9_\\\\]+$/D', $relative)) return;
    $path = __DIR__.'/src/'.str_replace('\\', '/', $relative).'.php';
    if (is_file($path)) require_once $path;
}, true, true);
