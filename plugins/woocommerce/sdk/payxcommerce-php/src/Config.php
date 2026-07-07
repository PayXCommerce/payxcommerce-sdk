<?php

declare(strict_types=1);

namespace PayXCommerce;

use PayXCommerce\Auth\AuthInterface;

final class Config
{
    public function __construct(
        public readonly string $baseUrl = 'https://payxcommerce.com/api/v1',
        public readonly ?AuthInterface $auth = null,
        public readonly int $timeoutSeconds = 30,
        public readonly bool $debug = false,
        public readonly string $apiHeaderPrefix = 'PXC',
    ) {
    }

    public function endpoint(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    public function apiHeader(string $name): string
    {
        return 'X-' . strtoupper($this->apiHeaderPrefix) . '-' . $name;
    }
}

