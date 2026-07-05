from __future__ import annotations

import hashlib
import hmac
import time

from ..util import idempotency, nonce


class HmacAuth:
    def __init__(self, public_key: str, secret_key: str, auto_idempotency: bool = True):
        self.public_key = public_key
        self.secret_key = secret_key
        self.auto_idempotency = auto_idempotency

    def headers(self, method: str, path: str, body: str, headers: dict[str, str], config) -> dict[str, str]:
        timestamp = str(int(time.time()))
        nonce_value = nonce.generate()
        headers[config.api_header("Public-Key")] = self.public_key
        headers[config.api_header("Timestamp")] = timestamp
        headers[config.api_header("Nonce")] = nonce_value
        headers[config.api_header("Signature")] = self.sign(timestamp, nonce_value, body, self.secret_key)
        if self.auto_idempotency and method.upper() in {"POST", "PUT", "PATCH"} and "Idempotency-Key" not in headers:
            headers["Idempotency-Key"] = idempotency.generate()
        return headers

    @staticmethod
    def sign(timestamp: str, nonce_value: str, body: str, secret_key: str) -> str:
        return hmac.new(secret_key.encode(), f"{timestamp}.{nonce_value}.{body}".encode(), hashlib.sha256).hexdigest()
