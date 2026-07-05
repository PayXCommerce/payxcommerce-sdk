# API Authentication

PayXCommerce API v1 supports two server-side authentication methods.

## HMAC API Key

Use HMAC when a merchant owns the API credentials directly.

Required headers:

```text
X-PXC-Public-Key
X-PXC-Timestamp
X-PXC-Nonce
X-PXC-Signature
Idempotency-Key for POST endpoints
```

Signature payload:

```text
timestamp + "." + nonce + "." + raw_json_body
```

Signature:

```text
hash_hmac("sha256", payload, secret_key)
```

## Developer App Bearer Token

Use Bearer tokens when an integration is represented by a Developer App.

Token endpoint:

```text
POST /api/v1/oauth/token
```

Request:

```json
{
  "grant_type": "client_credentials",
  "client_id": "developer_app_client_id",
  "client_secret": "developer_app_client_secret",
  "scope": "payment_requests.write transactions.read balances.read refunds.write"
}
```

Authenticated API calls then send:

```text
Authorization: Bearer <access_token>
```

