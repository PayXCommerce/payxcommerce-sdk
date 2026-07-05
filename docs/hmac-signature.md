# HMAC Signature

HMAC auth protects API requests from tampering and replay.

## Algorithm

1. JSON encode the request body without changing it after signing.
2. Generate a Unix timestamp.
3. Generate a unique nonce.
4. Build the message as `timestamp.nonce.raw_body`.
5. Sign the message using HMAC-SHA256 and the merchant secret key.

```php
$timestamp = (string) time();
$nonce = bin2hex(random_bytes(16));
$body = json_encode($payload, JSON_UNESCAPED_SLASHES);
$signature = hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $secretKey);
```

Timestamp tolerance is five minutes. Nonces must not be reused.

