from __future__ import annotations

from typing import Any, Optional


class PaymentRequests:
    def __init__(self, client):
        self.client = client

    def create(self, payload: dict[str, Any], idempotency_key: Optional[str] = None) -> dict[str, Any]:
        headers = {"Idempotency-Key": idempotency_key} if idempotency_key else {}
        return self.client.request("POST", "/payment-requests", payload, headers)
