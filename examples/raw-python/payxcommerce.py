"""Dependency-free PayXCommerce API helpers for raw Python integrations."""

from __future__ import annotations

import hashlib
import hmac
import json
import re
import secrets
import time
import urllib.error
import urllib.parse
import urllib.request
from typing import Any, Optional

DEFAULT_BASE_URL = "https://payxcommerce.com/api/v1"
TOKEN_SCOPE = "payment_requests.write transactions.read balances.read refunds.write"


def default_ipn_events() -> list[str]:
    return [
        "payment.succeeded",
        "payment.failed",
        "payment.cancelled",
        "payment.expired",
        "refund.succeeded",
        "payment.refunded",
        "chargeback.created",
        "dispute.created",
    ]


def json_body(payload: Optional[dict[str, Any]]) -> str:
    return "" if payload is None else json.dumps(payload, separators=(",", ":"), ensure_ascii=False)


def hmac_headers(public_key: str, secret_key: str, body: str, idempotency_key: Optional[str] = None) -> dict[str, str]:
    timestamp = str(int(time.time()))
    nonce = secrets.token_hex(16)
    signature = hmac.new(secret_key.encode(), f"{timestamp}.{nonce}.{body}".encode(), hashlib.sha256).hexdigest()
    headers = {
        "X-PXC-Public-Key": public_key,
        "X-PXC-Timestamp": timestamp,
        "X-PXC-Nonce": nonce,
        "X-PXC-Signature": signature,
    }
    if idempotency_key:
        headers["Idempotency-Key"] = idempotency_key
    return headers


def json_request(method: str, url: str, headers: Optional[dict[str, str]] = None, payload: Optional[dict[str, Any]] = None) -> dict[str, Any]:
    body = json_body(payload)
    request_headers = {"Accept": "application/json", "Content-Type": "application/json"}
    request_headers.update(headers or {})
    request = urllib.request.Request(url, data=body.encode() if body else None, headers=request_headers, method=method.upper())

    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            response_body = response.read().decode()
            return {"status": response.status, "body": parse_json(response_body)}
    except urllib.error.HTTPError as error:
        response_body = error.read().decode()
        return {"status": error.code, "body": parse_json(response_body)}


def parse_json(value: str) -> Any:
    if value == "":
        return {}
    try:
        return json.loads(value)
    except json.JSONDecodeError:
        return value


def oauth_client_credentials(base_url: str, client_id: str, client_secret: str, scope: str = TOKEN_SCOPE) -> dict[str, Any]:
    return json_request(
        "POST",
        endpoint(base_url, "/oauth/token"),
        payload={"grant_type": "client_credentials", "client_id": client_id, "client_secret": client_secret, "scope": scope},
    )


def verify_webhook(raw_body: str, headers: dict[str, str], webhook_secret: str, tolerance_seconds: int = 300) -> dict[str, Any]:
    event_id = header(headers, "X-PXC-Event-ID")
    timestamp = header(headers, "X-PXC-Timestamp")
    signature = header(headers, "X-PXC-Signature")

    if not event_id or not timestamp or not signature:
        raise ValueError("Missing PayXCommerce webhook signature headers.")
    if not timestamp.isdigit() or abs(int(time.time()) - int(timestamp)) > tolerance_seconds:
        raise ValueError("Invalid or expired PayXCommerce webhook timestamp.")

    payload = json.loads(raw_body)
    canonical_body = json.dumps(payload, separators=(",", ":"), ensure_ascii=False)
    expected = hmac.new(webhook_secret.encode(), f"{event_id}.{canonical_body}".encode(), hashlib.sha256).hexdigest()
    if not hmac.compare_digest(expected, signature):
        raise ValueError("Invalid PayXCommerce webhook signature.")
    return payload


def header(headers: dict[str, str], name: str) -> str:
    normalized = name.lower()
    for key, value in headers.items():
        if key.lower() == normalized:
            return value
    return ""


def endpoint(base_url: str, path: str) -> str:
    return base_url.rstrip("/") + "/" + path.lstrip("/")


def print_response(response: dict[str, Any]) -> None:
    print(f"HTTP Status: {response['status']}")
    print(json.dumps(response["body"], indent=2, ensure_ascii=False))


def redact(message: str) -> str:
    return re.sub(
        r"(secret|token|signature|authorization|password|key|client_secret|secret_key|webhook_secret)([^\s:=]*)?([:=]\s*)?([A-Za-z0-9_\-.!@$%^&*+/=]+)",
        r"\1\2\3[redacted]",
        message,
        flags=re.IGNORECASE,
    )
