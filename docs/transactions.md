# Transactions

Lookup a transaction by public transaction reference.

Endpoint:

```text
GET /api/v1/transactions/{transaction_reference}
```

The response keeps legacy `amount` and `currency` fields for compatibility, but new integrations should use explicit currency roles:

- `request_amount` / `request_currency` — customer-facing amount from the payment request.
- `gateway_charge_amount` / `gateway_charge_currency` — amount prepared for the selected payment processor.
- `gateway_requested_amount` / `gateway_requested_currency` — processor amount including configured gateway fee where applicable.
- `gateway_received_amount` / `gateway_received_currency` — amount reported back by the provider or reconciliation import.
- `ledger_amount` / `ledger_currency` — amount posted to merchant balance and settlement ledger.
- `conversion` — conversion quote details when checkout converted from request currency to gateway currency.
- `currency_reconciliation_status` — review flag when provider currency data is incomplete or needs finance review.

Example response:

```json
{
  "transaction_reference": "PXTRX-YYYYMMDD-ABC123",
  "payment_id": "PXPAY-YYYYMMDD-ABC123",
  "request_number": "PXRQ-YYYYMMDD-ABC123",
  "amount": 35.07,
  "currency": "USD",
  "request_amount": 60.00,
  "request_currency": "NZD",
  "gateway_charge_amount": 35.07,
  "gateway_charge_currency": "USD",
  "gateway_requested_amount": 35.07,
  "gateway_requested_currency": "USD",
  "gateway_received_amount": 35.07,
  "gateway_received_currency": "USD",
  "ledger_amount": 60.00,
  "ledger_currency": "NZD",
  "conversion": {
    "quote_reference": "PXFX-YYYYMMDD-ABC123",
    "from_amount": 60.00,
    "from_currency": "NZD",
    "to_amount": 35.07,
    "to_currency": "USD",
    "applied_rate": 0.5845,
    "rate_type": "mid_plus_markup"
  },
  "currency_reconciliation_status": "not_required",
  "status": "successful",
  "gateway": "Credit Card",
  "paid_at": "2026-04-12T10:30:00+00:00",
  "settlement_status": "not_settled"
}
```
