<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Client
{
    private const PATH = 'payment/payxcommerce/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function createPaymentRequest(array $payload, string $idempotencyKey, ?int $storeId = null): array
    {
        return $this->request('POST', '/payment-requests', $payload, ['Idempotency-Key' => $idempotencyKey], $storeId);
    }

    public function request(string $method, string $path, ?array $payload = null, array $headers = [], ?int $storeId = null): array
    {
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
        $headers = array_merge(['Accept: application/json'], $this->formatHeaders($headers));
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        if ($path === '/oauth/token') {
            // Token endpoint authenticates with client credentials in the JSON body.
        } elseif ($this->config('auth_method', $storeId) === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . $this->bearerToken($storeId);
        } else {
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));
            $headers[] = 'X-PXC-Public-Key: ' . $this->secretConfig('public_key', $storeId);
            $headers[] = 'X-PXC-Timestamp: ' . $timestamp;
            $headers[] = 'X-PXC-Nonce: ' . $nonce;
            $headers[] = 'X-PXC-Signature: ' . hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $this->secretConfig('secret_key', $storeId));
        }

        $curl = curl_init(rtrim($this->config('base_url', $storeId), '/') . '/' . ltrim($path, '/'));
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
            throw new \RuntimeException((string) ($decoded['message'] ?? $decoded['error'] ?? 'PayXCommerce API error.'));
        }
        return is_array($decoded) ? $decoded : [];
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
        $expected = hash_hmac('sha256', $eventId . '.' . json_encode($payload, JSON_UNESCAPED_SLASHES), $this->secretConfig('webhook_secret', $storeId));
        return hash_equals($expected, $signature);
    }

    public function config(string $key, ?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::PATH . $key, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function secretConfig(string $key, ?int $storeId = null): string
    {
        $value = $this->config($key, $storeId);
        return $value !== '' ? (string) $this->encryptor->decrypt($value) : '';
    }

    private function bearerToken(?int $storeId = null): string
    {
        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->secretConfig('client_id', $storeId),
            'client_secret' => $this->secretConfig('client_secret', $storeId),
            'scope' => 'payment_requests.write transactions.read balances.read refunds.write',
        ];
        $response = $this->request('POST', '/oauth/token', $payload, [], $storeId);
        if (empty($response['access_token'])) {
            throw new \RuntimeException('PayXCommerce token response did not include an access token.');
        }
        return (string) $response['access_token'];
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
