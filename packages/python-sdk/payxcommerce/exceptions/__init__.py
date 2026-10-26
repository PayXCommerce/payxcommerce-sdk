from typing import Any, Optional
from ..http.response_metadata import safe_headers, retry_after


class ApiException(RuntimeError):
    def __init__(
        self,
        message: str,
        status_code: Optional[int] = None,
        payx_error_code: Optional[str] = None,
        raw_response_body: Optional[str] = None,
        errors: Optional[dict[str, Any]] = None,
        response_headers=None,
    ):
        super().__init__(message)
        self.status_code = status_code
        self.payx_error_code = payx_error_code
        self.raw_response_body = raw_response_body
        self.errors = errors or {}
        self.response_headers = safe_headers(response_headers)
        self.retry_after_seconds = retry_after(self.response_headers.get("retry-after"))


class AuthException(ApiException):
    pass


class ValidationException(ApiException):
    pass


class RateLimitException(ApiException):
    pass

class WebhookVerificationException(RuntimeError):
    pass
