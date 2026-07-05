# Bearer Token Auth

Developer Apps use OAuth client credentials to obtain short-lived Bearer tokens.

## Token Request

```http
POST /api/v1/oauth/token
Content-Type: application/json
```

```json
{
  "grant_type": "client_credentials",
  "client_id": "YOUR_CLIENT_ID",
  "client_secret": "YOUR_CLIENT_SECRET",
  "scope": "payment_requests.write transactions.read balances.read refunds.write"
}
```

## Token Response

```json
{
  "token_type": "Bearer",
  "access_token": "pxc_access_...",
  "expires_in": 3600,
  "scope": "payment_requests.write transactions.read balances.read refunds.write"
}
```

Cache the token server-side and refresh it shortly before expiry.

