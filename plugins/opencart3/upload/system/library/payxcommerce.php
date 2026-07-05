<?php

class PayXCommerce
{
    private const TOKEN_SCOPE = 'payment_requests.write transactions.read balances.read refunds.write';

    private array $settings;
    private string $accessToken = '';

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function validateCredentials(): bool
    {
        $this->request('GET', '/balance');
        return true;
    }

    public function createPaymentRequest(array $payload, string $idempotencyKey): array
    {
        return $this->request('POST', '/payment-requests', $payload, $idempotencyKey);
    }

    public function request(string $method, string $path, ?array $payload = null, ?string $idempotencyKey = null): array
    {
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('Unable to encode request payload.');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        if ($this->setting('auth_method', 'hmac') === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . $this->bearerToken();
        } else {
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));
            $headers[] = 'X-PXC-Public-Key: ' . $this->setting('public_key');
            $headers[] = 'X-PXC-Timestamp: ' . $timestamp;
            $headers[] = 'X-PXC-Nonce: ' . $nonce;
            $headers[] = 'X-PXC-Signature: ' . hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $this->setting('secret_key'));
        }

        return $this->curl($method, $this->endpoint($path), $headers, $body);
    }

    public function verifyWebhook(string $rawBody, array $server): array
    {
        $eventId = (string) ($server['HTTP_X_PXC_EVENT_ID'] ?? '');
        $timestamp = (string) ($server['HTTP_X_PXC_TIMESTAMP'] ?? '');
        $signature = (string) ($server['HTTP_X_PXC_SIGNATURE'] ?? '');

        if ($eventId === '' || $timestamp === '' || $signature === '' || !ctype_digit($timestamp)) {
            throw new RuntimeException('Missing or invalid webhook signature headers.');
        }

        if (abs(time() - (int) $timestamp) > 300) {
            throw new RuntimeException('Webhook timestamp is outside the allowed tolerance.');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Webhook body is not valid JSON.');
        }

        $canonicalBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $expected = hash_hmac('sha256', $eventId . '.' . $canonicalBody, $this->setting('webhook_secret'));
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid webhook signature.');
        }

        return $payload;
    }

    public function isConfigured(): bool
    {
        if ($this->setting('webhook_secret') === '') {
            return false;
        }

        if ($this->setting('auth_method', 'hmac') === 'bearer') {
            return $this->setting('client_id') !== '' && $this->setting('client_secret') !== '';
        }

        return $this->setting('public_key') !== '' && $this->setting('secret_key') !== '';
    }

    public static function redact(string $message): string
    {
        return preg_replace('/(secret|token|signature|authorization|password|key)([^\s:=]*)?([:=]\s*)?([A-Za-z0-9_\-.!@$%^&*+\/=]+)/i', '$1$2$3[redacted]', $message) ?: $message;
    }

    private function bearerToken(): string
    {
        if ($this->accessToken !== '') {
            return $this->accessToken;
        }

        $payload = json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => $this->setting('client_id'),
            'client_secret' => $this->setting('client_secret'),
            'scope' => self::TOKEN_SCOPE,
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            throw new RuntimeException('Unable to encode token payload.');
        }

        $response = $this->curl('POST', $this->endpoint('/oauth/token'), ['Accept: application/json', 'Content-Type: application/json'], $payload);
        $this->accessToken = (string) ($response['access_token'] ?? '');
        if ($this->accessToken === '') {
            throw new RuntimeException('Token response did not include an access token.');
        }

        return $this->accessToken;
    }

    private function curl(string $method, string $url, array $headers, string $body): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($body !== '') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($responseBody === false) {
            throw new RuntimeException($error ?: 'API request failed.');
        }

        $decoded = json_decode((string) $responseBody, true);
        if ($status >= 400) {
            throw new RuntimeException((string) ($decoded['message'] ?? $decoded['error'] ?? 'API request failed.'));
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->setting('base_url', 'https://payxcommerce.com/api/v1'), '/') . '/' . ltrim($path, '/');
    }

    private function setting(string $key, string $default = ''): string
    {
        return (string) ($this->settings['payment_payxcommerce_' . $key] ?? $this->settings[$key] ?? $default);
    }
}
