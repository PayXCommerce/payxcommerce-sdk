# Error Handling

The PHP SDK maps PayXCommerce API errors to exceptions.

| Error Code | Exception |
| --- | --- |
| `authentication_failed` | `AuthException` |
| `signature_invalid` | `AuthException` |
| `timestamp_expired` | `AuthException` |
| `nonce_reused` | `AuthException` |
| `validation_failed` | `ValidationException` |
| `currency_not_supported` | `ValidationException` |
| `amount_out_of_range` | `ValidationException` |
| `rate_limit_exceeded` | `RateLimitException` |
| other API errors | `ApiException` |

Each exception exposes the HTTP status code, PayXCommerce error code, and raw response body when available.

