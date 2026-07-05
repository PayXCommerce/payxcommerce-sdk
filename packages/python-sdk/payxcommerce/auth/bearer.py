from __future__ import annotations

from ..util import idempotency


class BearerTokenAuth:
    def __init__(self, access_token: str, auto_idempotency: bool = True):
        self.access_token = access_token
        self.auto_idempotency = auto_idempotency

    def headers(self, method: str, path: str, body: str, headers: dict[str, str], config) -> dict[str, str]:
        headers["Authorization"] = "Bearer " + self.access_token
        if self.auto_idempotency and method.upper() in {"POST", "PUT", "PATCH"} and "Idempotency-Key" not in headers:
            headers["Idempotency-Key"] = idempotency.generate()
        return headers
