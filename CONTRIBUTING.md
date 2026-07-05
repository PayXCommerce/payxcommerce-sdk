# Contributing

Thank you for improving PayXCommerce integrations.

## Development Rules

- Keep PayXCommerce API logic in the SDK.
- Keep ecommerce-platform logic in the plugin layer.
- Do not duplicate HMAC or webhook verification logic in plugins.
- Add tests for signature generation, webhook verification, and API error mapping.
- Redact secrets in logs and examples.

## Commit Style

Use focused commits by feature area, for example:

- `Add PHP SDK HMAC authentication`
- `Add raw PHP webhook verification example`
- `Add WooCommerce payment gateway skeleton`

