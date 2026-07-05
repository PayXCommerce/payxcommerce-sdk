<?php

declare(strict_types=1);

namespace PayXCommerce\Http;

interface HttpClientInterface
{
    /**
     * @param array<string,string> $headers
     * @return array<string,mixed>
     */
    public function send(string $method, string $url, array $headers = [], string $body = ''): array;
}

