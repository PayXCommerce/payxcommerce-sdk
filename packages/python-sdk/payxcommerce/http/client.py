from __future__ import annotations

import json
import urllib.error
import urllib.request
import http.client
from typing import Any, Optional

from ..exceptions import ApiException, AuthException, RateLimitException, ValidationException
import re


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, file, code, message, headers, new_url):
        return None


class HttpClient:
    def __init__(self, timeout_seconds: int = 30):
        self.timeout_seconds = timeout_seconds

    def send(self, method: str, url: str, headers: Optional[dict[str, str]] = None, body: str = "") -> dict[str, Any]:
        request = urllib.request.Request(url, data=body.encode() if body else None, headers=headers or {}, method=method.upper())
        try:
            with urllib.request.build_opener(_NoRedirect()).open(request, timeout=self.timeout_seconds) as response:
                return self._decode(response.read().decode(errors="replace"))
        except urllib.error.HTTPError as error:
            try:
                response_body = error.read().decode(errors="replace")
            except (OSError, http.client.HTTPException):
                raise ApiException("PayXCommerce API response was interrupted; the operation outcome may be unknown.") from None
            decoded = self._decode(response_body)
            self._throw_for_status(error.code, decoded, response_body, error.headers)
        except urllib.error.URLError as error:
            raise ApiException("PayXCommerce API transport failed; the operation outcome may be unknown.") from None
        except (OSError, http.client.HTTPException):
            raise ApiException("PayXCommerce API response was interrupted; the operation outcome may be unknown.") from None

    def _decode(self, response_body: str) -> dict[str, Any]:
        if response_body == "":
            return {}
        try:
            decoded = json.loads(response_body)
        except json.JSONDecodeError:
            return {"raw_body": response_body}
        return decoded if isinstance(decoded, dict) else {"data": decoded}

    def _throw_for_status(self, status: int, decoded: dict[str, Any], raw_body: str, headers=None):
        errors = decoded.get("errors") if isinstance(decoded.get("errors"), dict) else {}
        text = decoded.get("message") or decoded.get("error")
        message = self._message_with_errors(text if isinstance(text, str) else "PayXCommerce API error.", errors)
        code = decoded.get("error_code")
        if not isinstance(code, str) or not re.fullmatch(r"[a-z][a-z0-9_]{0,63}", code):
            code = None
        if status == 429:
            message = "Payment service is busy. Please retry after the indicated delay."
        elif status >= 500:
            message = "Payment service is unavailable; the operation outcome may be unknown."
        elif status < 400:
            message = "PayXCommerce API redirect was not followed."
        exception_type = ApiException
        if status >= 500:
            exception_type = ApiException
        elif status == 429:
            exception_type = RateLimitException
        elif code in {"authentication_failed", "signature_invalid", "timestamp_expired", "nonce_reused"} or status in {401, 403}:
            exception_type = AuthException
        elif code in {"validation_failed", "currency_not_supported", "amount_out_of_range"} or status == 422:
            exception_type = ValidationException
        elif code == "rate_limit_exceeded" or status == 429:
            exception_type = RateLimitException
        raise exception_type(message, status, str(code) if code else None, raw_body, errors, headers)

    def _message_with_errors(self, message: str, errors: dict[str, Any]) -> str:
        details: list[str] = []
        for field, field_errors in errors.items():
            if not isinstance(field_errors, list):
                field_errors = [field_errors]
            for field_error in field_errors:
                text = str(field_error or "").strip()
                if text:
                    details.append(f"{field}: {text}")

        if not details:
            return message

        return message.rstrip(".") + ". " + " ".join(details[:8])
