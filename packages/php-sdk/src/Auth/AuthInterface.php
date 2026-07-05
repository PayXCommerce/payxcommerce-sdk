<?php

declare(strict_types=1);

namespace PayXCommerce\Auth;

use PayXCommerce\Config;

interface AuthInterface
{
    /**
     * @param array<string,string> $headers
     * @return array<string,string>
     */
    public function headers(string $method, string $path, string $body, array $headers, Config $config): array;
}

