<?php

declare(strict_types=1);

namespace PayXCommerce\Http;

use PayXCommerce\Config;
use PayXCommerce\Exceptions\ApiException;
use PayXCommerce\Exceptions\AuthException;
use PayXCommerce\Exceptions\RateLimitException;
use PayXCommerce\Exceptions\ValidationException;

final class CurlHttpClient implements HttpClientInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public function send(string $method, string $url, array $headers = [], string $body = ''): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new ApiException('Unable to initialize cURL request.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => $this->config->timeoutSeconds,
            CURLOPT_HEADER => true,
        ]);

        if ($body !== '') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($curl);
        if ($raw === false) {
            $message = curl_error($curl) ?: 'PayXCommerce API request failed.';
            curl_close($curl);
            throw new ApiException($message);
        }

        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);

        $responseBody = substr($raw, $headerSize);
        $decoded = $responseBody === '' ? [] : json_decode($responseBody, true);
        if (!is_array($decoded)) {
            $decoded = ['raw_body' => $responseBody];
        }

        if ($status >= 400) {
            $this->throwForStatus($status, $decoded, $responseBody);
        }

        return $decoded;
    }

    private function throwForStatus(int $status, array $decoded, string $rawBody): never
    {
        $message = (string) ($decoded['message'] ?? $decoded['error'] ?? 'PayXCommerce API error.');
        $code = isset($decoded['error_code']) ? (string) $decoded['error_code'] : null;

        $exception = match ($code) {
            'authentication_failed', 'signature_invalid', 'timestamp_expired', 'nonce_reused' => new AuthException($message, $status, $code, $rawBody),
            'validation_failed', 'currency_not_supported', 'amount_out_of_range' => new ValidationException($message, $status, $code, $rawBody),
            'rate_limit_exceeded' => new RateLimitException($message, $status, $code, $rawBody),
            default => match ($status) {
                401, 403 => new AuthException($message, $status, $code, $rawBody),
                422 => new ValidationException($message, $status, $code, $rawBody),
                429 => new RateLimitException($message, $status, $code, $rawBody),
                default => new ApiException($message, $status, $code, $rawBody),
            },
        };

        throw $exception;
    }
}

