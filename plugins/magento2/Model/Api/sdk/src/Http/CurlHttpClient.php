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
    private int $timeoutSeconds;

    public function __construct(Config|int $config)
    {
        // Integer form lets existing PHP8.0 CMS adapters reuse only this
        // transport, without importing PHP8.1-only SDK configuration/auth.
        $this->timeoutSeconds = is_int($config) ? max(1,$config) : $config->timeoutSeconds;
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

        $responseHeaders = [];
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                // Discard interim/proxy blocks; only the final response counts.
                if (preg_match('~^HTTP/\S+\s+\d{3}\b~i', $line)) $responseHeaders = [];
                elseif (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $name = strtolower(trim($name));
                    // Store only the allowlist, not raw response headers.
                    if (in_array($name, ResponseMetadata::HEADER_NAMES, true)) {
                        $safe = ResponseMetadata::headers([$name => trim($value)]);
                        // Duplicate metadata is ambiguous, including an invalid
                        // first value. Do not retain arbitrary header names.
                        $responseHeaders[$name] = array_key_exists($name, $responseHeaders) ? null : ($safe[$name] ?? null);
                    }
                }
                return strlen($line);
            },
        ]);

        if ($body !== '') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($curl);
        if ($raw === false) {
            curl_close($curl);
            throw new ApiException('PayXCommerce API transport failed; the operation outcome may be unknown.');
        }

        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $responseBody = $raw;
        $decoded = $responseBody === '' ? [] : json_decode($responseBody, true);
        if (!is_array($decoded)) {
            $decoded = ['raw_body' => $responseBody];
        }

        if ($status >= 300) {
            $this->throwForStatus($status, $decoded, $responseBody, $responseHeaders);
        }

        return $decoded;
    }

    private function throwForStatus(int $status, array $decoded, string $rawBody, array $headers = []): void
    {
        $errors = isset($decoded['errors']) && is_array($decoded['errors']) ? $decoded['errors'] : [];
        $text = $decoded['message'] ?? $decoded['error'] ?? null;
        $message = $this->messageWithErrors(is_string($text) ? $text : 'PayXCommerce API error.', $errors);
        $code = $decoded['error_code'] ?? null;
        if (!is_string($code) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $code)) $code = null;
        if ($status === 429) $message = 'Payment service is busy. Please retry after the indicated delay.';
        elseif ($status >= 500) $message = 'Payment service is unavailable; the operation outcome may be unknown.';
        elseif ($status < 400) $message = 'PayXCommerce API redirect was not followed.';

        if ($status >= 500) throw new ApiException($message,$status,$code,$rawBody,$errors,$headers);

        $exception = match ($status) {
            429 => new RateLimitException($message, $status, $code, $rawBody, $errors, $headers),
            401, 403 => new AuthException($message, $status, $code, $rawBody, $errors, $headers),
            422 => new ValidationException($message, $status, $code, $rawBody, $errors, $headers),
            default => match ($code) {
            'authentication_failed', 'signature_invalid', 'timestamp_expired', 'nonce_reused' => new AuthException($message, $status, $code, $rawBody, $errors, $headers),
            'validation_failed', 'currency_not_supported', 'amount_out_of_range' => new ValidationException($message, $status, $code, $rawBody, $errors, $headers),
            'rate_limit_exceeded' => new RateLimitException($message, $status, $code, $rawBody, $errors, $headers),
            default => match ($status) {
                401, 403 => new AuthException($message, $status, $code, $rawBody, $errors, $headers),
                422 => new ValidationException($message, $status, $code, $rawBody, $errors, $headers),
                429 => new RateLimitException($message, $status, $code, $rawBody, $errors, $headers),
                default => new ApiException($message, $status, $code, $rawBody, $errors, $headers),
            },
            },
        };

        throw $exception;
    }

    private function messageWithErrors(string $message, array $errors): string
    {
        $details = [];
        foreach ($errors as $field => $fieldErrors) {
            foreach ((array) $fieldErrors as $fieldError) {
                if (!is_scalar($fieldError)) continue;
                $fieldError = substr(trim((string) $fieldError),0,256);
                if ($fieldError !== '') {
                    $details[] = $field . ': ' . $fieldError;
                }
            }
        }

        if (!$details) {
            return $message;
        }

        return rtrim($message, '.') . '. ' . implode(' ', array_slice($details, 0, 8));
    }
}
