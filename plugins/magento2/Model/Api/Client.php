<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model\Api;

use PayXCommerce\Payment\Model\Config;
use PayXCommerce\Payment\Model\Logger;

class Client
{
    private array $bearerTokens = [];

    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger
    ) {
    }

    public function createPaymentRequest(array $payload, string $idempotencyKey, ?int $storeId = null): array
    {
        return $this->request('POST', '/payment-requests', $payload, ['Idempotency-Key' => $idempotencyKey], $storeId);
    }

    public function request(string $method, string $path, ?array $payload = null, array $headers = [], ?int $storeId = null): array
    {
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new \RuntimeException('Unable to encode API request payload.');
        }

        $headers = array_merge(['Accept: application/json'], $this->formatHeaders($headers));
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        if ($path === '/oauth/token') {
            // Token endpoint authenticates with client credentials in the JSON body.
        } elseif ($this->config->value('auth_method', $storeId) === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . $this->bearerToken($storeId);
        } else {
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));
            $headers[] = 'X-PXC-Public-Key: ' . $this->config->secret('public_key', $storeId);
            $headers[] = 'X-PXC-Timestamp: ' . $timestamp;
            $headers[] = 'X-PXC-Nonce: ' . $nonce;
            $headers[] = 'X-PXC-Signature: ' . hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $this->config->secret('secret_key', $storeId));
        }

        $curl = curl_init(rtrim($this->config->value('base_url', $storeId), '/') . '/' . ltrim($path, '/'));
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30]);
        if ($body !== '') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($responseBody === false) {
            throw new \RuntimeException($error ?: 'PayXCommerce API request failed.');
        }
        $decoded = json_decode((string) $responseBody, true);
        if ($status >= 400) {
            $message = (string) ($decoded['message'] ?? $decoded['error'] ?? 'PayXCommerce API error.');
            $this->logger->error('API request failed: ' . $message, ['status' => $status, 'path' => $path]);
            throw new \RuntimeException($message);
        }
        return is_array($decoded) ? $decoded : [];
    }

    public function validateCredentials(?int $storeId = null): bool
    {
        $this->request('GET', '/balance', null, [], $storeId);
        return true;
    }

    public function verifyWebhook(string $eventId, string $timestamp, string $signature, string $rawBody, ?int $storeId = null): bool
    {
        if ($eventId === '' || $timestamp === '' || $signature === '' || !ctype_digit($timestamp)) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return false;
        }
        $expected = hash_hmac('sha256', $eventId . '.' . json_encode($payload, JSON_UNESCAPED_SLASHES), $this->config->secret('webhook_secret', $storeId));
        return hash_equals($expected, $signature);
    }

    public function config(string $key, ?int $storeId = null): string
    {
        return $this->config->value($key, $storeId);
    }

    private function bearerToken(?int $storeId = null): string
    {
        $cacheKey = (string) ($storeId ?? 0);
        if (!empty($this->bearerTokens[$cacheKey])) {
            return $this->bearerTokens[$cacheKey];
        }

        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config->secret('client_id', $storeId),
            'client_secret' => $this->config->secret('client_secret', $storeId),
            'scope' => 'payment_requests.write transactions.read balances.read refunds.write',
        ];
        $response = $this->request('POST', '/oauth/token', $payload, [], $storeId);
        if (empty($response['access_token'])) {
            throw new \RuntimeException('PayXCommerce token response did not include an access token.');
        }
        $this->bearerTokens[$cacheKey] = (string) $response['access_token'];
        return $this->bearerTokens[$cacheKey];
    }

    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $value) {
            $formatted[] = $name . ': ' . $value;
        }
        return $formatted;
    }
}
