from __future__ import annotations

import json
import urllib.error
import urllib.request
from typing import Any, Optional

from ..exceptions import ApiException, AuthException, RateLimitException, ValidationException


class HttpClient:
    def __init__(self, timeout_seconds: int = 30):
        self.timeout_seconds = timeout_seconds

    def send(self, method: str, url: str, headers: Optional[dict[str, str]] = None, body: str = "") -> dict[str, Any]:
        request = urllib.request.Request(url, data=body.encode() if body else None, headers=headers or {}, method=method.upper())
        try:
            with urllib.request.urlopen(request, timeout=self.timeout_seconds) as response:
                return self._decode(response.read().decode())
        except urllib.error.HTTPError as error:
            response_body = error.read().decode()
            decoded = self._decode(response_body)
            self._throw_for_status(error.code, decoded, response_body)
        except urllib.error.URLError as error:
            raise ApiException(str(error.reason)) from error

    def _decode(self, response_body: str) -> dict[str, Any]:
        if response_body == "":
            return {}
        try:
            decoded = json.loads(response_body)
        except json.JSONDecodeError:
            return {"raw_body": response_body}
        return decoded if isinstance(decoded, dict) else {"data": decoded}

    def _throw_for_status(self, status: int, decoded: dict[str, Any], raw_body: str):
        message = str(decoded.get("message") or decoded.get("error") or "PayXCommerce API error.")
        code = decoded.get("error_code")
        exception_type = ApiException
        if code in {"authentication_failed", "signature_invalid", "timestamp_expired", "nonce_reused"} or status in {401, 403}:
            exception_type = AuthException
        elif code in {"validation_failed", "currency_not_supported", "amount_out_of_range"} or status == 422:
            exception_type = ValidationException
        elif code == "rate_limit_exceeded" or status == 429:
            exception_type = RateLimitException
        raise exception_type(message, status, str(code) if code else None, raw_body)
