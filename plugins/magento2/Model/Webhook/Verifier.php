<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model\Webhook;

use PayXCommerce\Payment\Model\Config;

class Verifier
{
    public function __construct(private readonly Config $config)
    {
    }

    public function verify(string $eventId, string $timestamp, string $signature, string $rawBody, ?int $storeId = null): array
    {
        if ($eventId === '' || $timestamp === '' || $signature === '' || !ctype_digit($timestamp)) {
            throw new \RuntimeException('Missing or invalid webhook signature headers.');
        }

        if (abs(time() - (int) $timestamp) > 300) {
            throw new \RuntimeException('Webhook timestamp is outside the allowed tolerance.');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Webhook body is not valid JSON.');
        }

        $canonicalBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $expected = hash_hmac('sha256', $eventId . '.' . $canonicalBody, $this->config->secret('webhook_secret', $storeId));
        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Invalid webhook signature.');
        }

        return $payload;
    }
}
