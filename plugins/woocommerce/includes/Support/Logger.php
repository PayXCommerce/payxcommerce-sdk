<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce\Support;

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
        wc_get_logger()->info($this->redact($message), ['source' => 'payxcommerce'] + $context);
    }

    private function redact(string $message): string
    {
        return preg_replace('/(secret|token|signature|authorization|client_secret|secret_key|webhook_secret)([^\s]*)/i', '$1[redacted]', $message) ?: $message;
    }
}
