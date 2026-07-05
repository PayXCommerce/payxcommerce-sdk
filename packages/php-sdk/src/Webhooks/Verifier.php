<?php

declare(strict_types=1);

namespace PayXCommerce\Webhooks;

use PayXCommerce\Exceptions\WebhookVerificationException;

final class Verifier
{
    public function __construct(
        private readonly string $webhookSecret,
        private readonly int $toleranceSeconds = 300,
    ) {
    }

    public function verify(string $rawBody, array $headers): array
    {
        $eventId = $this->header($headers, 'X-PXC-Event-ID');
        $timestamp = $this->header($headers, 'X-PXC-Timestamp');
        $signature = $this->header($headers, 'X-PXC-Signature');

        if (!$eventId || !$timestamp || !$signature) {
            throw new WebhookVerificationException('Missing PayXCommerce webhook signature headers.');
        }

        if (!ctype_digit((string) $timestamp)) {
            throw new WebhookVerificationException('Invalid PayXCommerce webhook timestamp.');
        }

        if (abs(time() - (int) $timestamp) > $this->toleranceSeconds) {
            throw new WebhookVerificationException('PayXCommerce webhook timestamp is outside the allowed tolerance.');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new WebhookVerificationException('PayXCommerce webhook body is not valid JSON.');
        }

        $expected = self::signature($eventId, $rawBody, $this->webhookSecret);
        if (!hash_equals($expected, $signature)) {
            throw new WebhookVerificationException('Invalid PayXCommerce webhook signature.');
        }

        return $payload;
    }

    public static function signature(string $eventId, string $rawBody, string $webhookSecret): string
    {
        $payload = json_decode($rawBody, true);
        $canonicalBody = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_SLASHES) : $rawBody;

        return hash_hmac('sha256', $eventId . '.' . $canonicalBody, $webhookSecret);
    }

    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
            }
        }

        $serverName = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($headers[$serverName])) {
            return (string) $headers[$serverName];
        }

        return null;
    }
}

