<?php

declare(strict_types=1);

namespace PayXCommerce\Auth;

use PayXCommerce\Config;
use PayXCommerce\Util\Idempotency;
use PayXCommerce\Util\Nonce;

final class HmacAuth implements AuthInterface
{
    public function __construct(
        private readonly string $publicKey,
        private readonly string $secretKey,
        private readonly bool $autoIdempotency = true,
    ) {
    }

    public function headers(string $method, string $path, string $body, array $headers, Config $config): array
    {
        $timestamp = (string) time();
        $nonce = Nonce::generate();
        $signature = self::sign($timestamp, $nonce, $body, $this->secretKey);

        $headers[$config->apiHeader('Public-Key')] = $this->publicKey;
        $headers[$config->apiHeader('Timestamp')] = $timestamp;
        $headers[$config->apiHeader('Nonce')] = $nonce;
        $headers[$config->apiHeader('Signature')] = $signature;

        if ($this->autoIdempotency && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true) && empty($headers['Idempotency-Key'])) {
            $headers['Idempotency-Key'] = Idempotency::generate();
        }

        return $headers;
    }

    public static function sign(string $timestamp, string $nonce, string $body, string $secretKey): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $secretKey);
    }
}

