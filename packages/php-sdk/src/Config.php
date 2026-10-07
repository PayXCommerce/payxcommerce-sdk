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
        $parts = parse_url($baseUrl);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test');
        if (!$host || ($scheme !== 'https' && !($scheme === 'http' && $local))) {
            throw new \InvalidArgumentException('PayXCommerce API base URL must use HTTPS (HTTP is allowed only for localhost or .test development hosts).');
        }
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
