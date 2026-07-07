from typing import Any, Optional


class ApiException(RuntimeError):
    def __init__(
        self,
        message: str,
        status_code: Optional[int] = None,
        payx_error_code: Optional[str] = None,
        raw_response_body: Optional[str] = None,
        errors: Optional[dict[str, Any]] = None,
    ):
        super().__init__(message)
        self.status_code = status_code
        self.payx_error_code = payx_error_code
        self.raw_response_body = raw_response_body
        self.errors = errors or {}


class AuthException(ApiException):
    pass


class ValidationException(ApiException):
    pass


class RateLimitException(ApiException):
    pass

class WebhookVerificationException(RuntimeError):
    pass
