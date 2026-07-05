# Security Policy

## Supported Versions

Security fixes target the latest maintained release. Merchants and integration partners should keep SDKs and plugins updated to the newest available version.

## Reporting a Vulnerability

Report security issues privately to support@payxcommerce.com. Do not open public issues for vulnerabilities involving credentials, webhook signing, payment status updates, or checkout redirects.

## Secret Handling Rules

- Do not commit API keys, client secrets, Bearer tokens, webhook secrets, or real customer data.
- Do not log secret keys, Authorization headers, webhook signatures, or raw card data.
- Verify webhook signatures before changing order status.
- Use idempotency keys for payment request and refund creation.
- Keep SDK/plugin credentials server-side only.

