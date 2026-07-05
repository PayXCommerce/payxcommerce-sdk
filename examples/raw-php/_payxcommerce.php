<?php

declare(strict_types=1);

function payx_json_request(string $method, string $url, array $headers = [], ?array $payload = null): array
{
    $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        throw new RuntimeException('Unable to encode JSON request body.');
    }

    $headerLines = ['Accept: application/json', 'Content-Type: application/json'];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headerLines,
        CURLOPT_TIMEOUT => 30,
    ]);

    if ($body !== '') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }

    $responseBody = curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($responseBody === false) {
        throw new RuntimeException($error ?: 'cURL request failed.');
    }

    return [
        'status' => $statusCode,
        'body' => json_decode($responseBody, true) ?: $responseBody,
    ];
}

function payx_hmac_headers(string $publicKey, string $secretKey, string $body, ?string $idempotencyKey = null): array
{
    $timestamp = (string) time();
    $nonce = bin2hex(random_bytes(16));
    $signature = hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $secretKey);

    $headers = [
        'X-PXC-Public-Key' => $publicKey,
        'X-PXC-Timestamp' => $timestamp,
        'X-PXC-Nonce' => $nonce,
        'X-PXC-Signature' => $signature,
    ];

    if ($idempotencyKey !== null) {
        $headers['Idempotency-Key'] = $idempotencyKey;
    }

    return $headers;
}

function payx_default_ipn_events(): array
{
    return [
        'payment.succeeded',
        'payment.failed',
        'payment.cancelled',
        'payment.expired',
        'refund.succeeded',
        'payment.refunded',
        'chargeback.created',
        'dispute.created',
    ];
}

function payx_redact(string $message): string
{
    return preg_replace('/(secret|token|signature|authorization|password|key|client_secret|secret_key|webhook_secret)([^\s:=]*)?([:=]\s*)?([A-Za-z0-9_\-.!@$%^&*+\/=]+)/i', '$1$2$3[redacted]', $message) ?: $message;
}

function payx_print_response(array $response): void
{
    echo "HTTP Status: {$response['status']}\n";
    echo json_encode($response['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
