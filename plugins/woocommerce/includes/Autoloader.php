<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce;

final class Autoloader
{
    public static function register(string $pluginPath): void
    {
        $pluginPath = rtrim($pluginPath, '/') . '/';

        $composer = $pluginPath . 'vendor/autoload.php';
        if (is_file($composer)) {
            require_once $composer;
        }

        spl_autoload_register(static function (string $class) use ($pluginPath): void {
            $pluginPrefix = 'PayXCommerce\\WooCommerce\\';
            if (str_starts_with($class, $pluginPrefix)) {
                $relative = substr($class, strlen($pluginPrefix));
                $path = $pluginPath . 'includes/' . str_replace('\\', '/', $relative) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
                return;
            }

            if (class_exists('PayXCommerce\\Client', false)) {
                return;
            }

            $sdkPrefix = 'PayXCommerce\\';
            if (str_starts_with($class, $sdkPrefix)) {
                $relative = substr($class, strlen($sdkPrefix));
                $candidates = [
                    $pluginPath . 'vendor/payxcommerce/payxcommerce-php/src/' . str_replace('\\', '/', $relative) . '.php',
                    dirname($pluginPath, 2) . '/packages/php-sdk/src/' . str_replace('\\', '/', $relative) . '.php',
                ];
                foreach ($candidates as $path) {
                    if (is_file($path)) {
                        require_once $path;
                        return;
                    }
                }
            }
        });
    }
}
