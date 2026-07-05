from __future__ import annotations

import json
from typing import Any, Optional

from .auth.base import AuthInterface
from .config import Config
from .http.client import HttpClient
from .resources.balance import Balance
from .resources.payment_requests import PaymentRequests
from .resources.refunds import Refunds
from .resources.transactions import Transactions


class Client:
    def __init__(self, config: Optional[Config] = None, http_client: Optional[HttpClient] = None):
        self.config = config or Config()
        self.http = http_client or HttpClient(self.config.timeout_seconds)

    def request(self, method: str, path: str, payload: Optional[dict[str, Any]] = None, headers: Optional[dict[str, str]] = None, auth: Optional[AuthInterface] = None) -> dict[str, Any]:
        body = "" if payload is None else json.dumps(payload, separators=(",", ":"), ensure_ascii=False)
        request_headers = {"Accept": "application/json", "Content-Type": "application/json"}
        request_headers.update(headers or {})
        selected_auth = auth if auth is not None else self.config.auth
        if selected_auth is not None:
            request_headers = selected_auth.headers(method, path, body, request_headers, self.config)
        return self.http.send(method, self.config.endpoint(path), request_headers, body)

    def payment_requests(self) -> PaymentRequests:
        return PaymentRequests(self)

    def balance(self) -> Balance:
        return Balance(self)

    def transactions(self) -> Transactions:
        return Transactions(self)

    def refunds(self) -> Refunds:
        return Refunds(self)
