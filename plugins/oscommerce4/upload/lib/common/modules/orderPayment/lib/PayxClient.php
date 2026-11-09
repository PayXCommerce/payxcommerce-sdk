<?php

declare(strict_types=1);

namespace common\modules\orderPayment\lib;

final class PayxCountryRestrictionException extends \RuntimeException {}

/** Transport used by the osCommerce 4 payment module. No card data passes through the store. */
final class PayxClient
{
    private string $token = '';

    public function __construct(private array $settings)
    {
        $url = (string) ($settings['base_url'] ?? '');
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (($host !== 'payxcommerce.com' && !str_ends_with($host, '.payxcommerce.com'))
            || $scheme !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('PayX API URL must be an HTTPS PayXCommerce host without embedded credentials.');
        }
    }

    public function create(array $payload, string $key): array
    {
        if ($key === '' || strlen($key) > 191) {
            throw new \InvalidArgumentException('Invalid idempotency key.');
        }
        $environment = $this->settings['environment'] ?? 'test';
        if (!in_array($environment, ['test', 'live'], true) || ($payload['environment'] ?? null) !== $environment) {
            throw new \RuntimeException('PayX environment mismatch.');
        }
        $public = (string) ($this->settings['public_key'] ?? '');
        if (($this->settings['auth_method'] ?? 'hmac') === 'hmac') {
            $expected = $environment === 'test' ? 'pk_test_' : 'pk_live_';
            if (!str_starts_with($public, $expected)) {
                throw new \RuntimeException('PayX API key does not match selected mode.');
            }
        }
        return $this->request('POST', '/payment-requests', $payload, $key);
    }

    public function verifyWebhook(string $raw, array $server): array
    {
        $id = (string) ($server['HTTP_X_PXC_EVENT_ID'] ?? '');
        $time = (string) ($server['HTTP_X_PXC_TIMESTAMP'] ?? '');
        $sig = (string) ($server['HTTP_X_PXC_SIGNATURE'] ?? '');
        $secret = (string) ($this->settings['webhook_secret'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._:-]{1,191}$/D', $id)
            || !ctype_digit($time) || abs(time() - (int) $time) > 300 || $secret === '') {
            throw new \RuntimeException('Invalid PayX webhook headers.');
        }
        if (!hash_equals(hash_hmac('sha256', $id . '.' . $raw, $secret), $sig)) {
            throw new \RuntimeException('Invalid PayX webhook signature.');
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid PayX webhook JSON.');
        }
        return $payload;
    }

    private function request(string $method, string $path, array $payload, string $key): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $headers = ['Accept: application/json', 'Content-Type: application/json', 'Idempotency-Key: ' . $key];
        if (($this->settings['auth_method'] ?? 'hmac') === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . $this->token();
        } else {
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));
            $secret = (string) ($this->settings['secret_key'] ?? '');
            if ($secret === '' || ($this->settings['public_key'] ?? '') === '') {
                throw new \RuntimeException('PayX HMAC credentials are incomplete.');
            }
            $headers[] = 'X-PXC-Public-Key: ' . $this->settings['public_key'];
            $headers[] = 'X-PXC-Timestamp: ' . $timestamp;
            $headers[] = 'X-PXC-Nonce: ' . $nonce;
            $headers[] = 'X-PXC-Signature: ' . hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $secret);
        }
        return $this->send($method, $path, $headers, $body);
    }

    private function token(): string
    {
        if ($this->token !== '') {
            return $this->token;
        }
        $id = (string) ($this->settings['client_id'] ?? '');
        $secret = (string) ($this->settings['client_secret'] ?? '');
        if ($id === '' || $secret === '') {
            throw new \RuntimeException('PayX Developer App credentials are incomplete.');
        }
        $body = json_encode(['grant_type' => 'client_credentials', 'client_id' => $id, 'client_secret' => $secret, 'scope' => 'payment_requests.write transactions.read'], JSON_THROW_ON_ERROR);
        $result = $this->send('POST', '/oauth/token', ['Accept: application/json', 'Content-Type: application/json'], $body);
        $this->token = (string) ($result['access_token'] ?? '');
        if ($this->token === '') {
            throw new \RuntimeException('PayX token response was incomplete.');
        }
        return $this->token;
    }

    private function send(string $method, string $path, array $headers, string $body): array
    {
        $url = rtrim($this->settings['base_url'], '/') . $path;
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($raw === false || $status < 200 || $status >= 300) {
            // Never log provider response bodies: they may contain tokens or customer data.
            if (is_string($raw) && self::isCountryRestrictionResponse($status, $raw)) {
                throw new PayxCountryRestrictionException('Billing country is restricted by the merchant payment policy.');
            }
            throw new \RuntimeException('PayX request failed (HTTP ' . $status . ($error !== '' ? ', transport error' : '') . ').');
        }
        $result = json_decode($raw, true);
        if (!is_array($result)) {
            throw new \RuntimeException('PayX returned invalid JSON.');
        }
        return $result;
    }

    private static function isCountryRestrictionResponse(int $status, string $raw): bool
    {
        if ($status !== 422) return false;
        $response = json_decode($raw, true);
        return is_array($response)
            && ($response['error_code'] ?? null) === 'payment_request_limit_failed'
            && ($response['message'] ?? null) === 'Customer country is not allowed for this merchant.';
    }
}
