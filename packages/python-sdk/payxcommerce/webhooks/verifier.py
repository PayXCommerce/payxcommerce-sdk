from __future__ import annotations

import hashlib
import hmac
import json
import time
from typing import Any

from ..exceptions import WebhookVerificationException


class Verifier:
    def __init__(self, webhook_secret: str, tolerance_seconds: int = 300):
        self.webhook_secret = webhook_secret
        self.tolerance_seconds = tolerance_seconds

    def verify(self, raw_body: str, headers: dict[str, str]) -> dict[str, Any]:
        event_id = self._header(headers, "X-PXC-Event-ID")
        timestamp = self._header(headers, "X-PXC-Timestamp")
        signature = self._header(headers, "X-PXC-Signature")
        if not event_id or not timestamp or not signature:
            raise WebhookVerificationException("Missing PayXCommerce webhook signature headers.")
        if not timestamp.isdigit():
            raise WebhookVerificationException("Invalid PayXCommerce webhook timestamp.")
        if abs(int(time.time()) - int(timestamp)) > self.tolerance_seconds:
            raise WebhookVerificationException("PayXCommerce webhook timestamp is outside the allowed tolerance.")
        try:
            payload = json.loads(raw_body)
        except json.JSONDecodeError as exc:
            raise WebhookVerificationException("PayXCommerce webhook body is not valid JSON.") from exc
        expected = self.signature(event_id, raw_body, self.webhook_secret)
        if not hmac.compare_digest(expected, signature):
            raise WebhookVerificationException("Invalid PayXCommerce webhook signature.")
        return payload

    @staticmethod
    def signature(event_id: str, raw_body: str, webhook_secret: str) -> str:
        try:
            payload = json.loads(raw_body)
            canonical_body = json.dumps(payload, separators=(",", ":"), ensure_ascii=False)
        except json.JSONDecodeError:
            canonical_body = raw_body
        return hmac.new(webhook_secret.encode(), f"{event_id}.{canonical_body}".encode(), hashlib.sha256).hexdigest()

    def _header(self, headers: dict[str, str], name: str) -> str:
        normalized = name.lower()
        for key, value in headers.items():
            if key.lower() == normalized:
                return str(value)
        server_name = "HTTP_" + name.upper().replace("-", "_")
        return str(headers.get(server_name, ""))
