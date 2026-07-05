<?php

declare(strict_types=1);

namespace PayXCommerce\Auth;

use PayXCommerce\Config;
use PayXCommerce\Util\Idempotency;

final class BearerTokenAuth implements AuthInterface
{
    public function __construct(
        private readonly string $accessToken,
        private readonly bool $autoIdempotency = true,
    ) {
    }

    public function headers(string $method, string $path, string $body, array $headers, Config $config): array
    {
        $headers['Authorization'] = 'Bearer ' . $this->accessToken;

        if ($this->autoIdempotency && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true) && empty($headers['Idempotency-Key'])) {
            $headers['Idempotency-Key'] = Idempotency::generate();
        }

        return $headers;
    }
}

