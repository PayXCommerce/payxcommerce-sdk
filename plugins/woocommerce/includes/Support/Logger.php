<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce\Support;

use PayXCommerce\Util\Redactor;

final class Logger
{
    public function __construct(private readonly bool $enabled)
    {
    }

    public function info(string $message, array $context = []): void
    {
        if (!$this->enabled || !function_exists('wc_get_logger')) {
            return;
        }
        wc_get_logger()->info(Redactor::text($message), ['source' => 'payxcommerce'] + Redactor::context($context));
    }
}
