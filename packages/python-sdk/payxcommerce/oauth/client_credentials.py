from __future__ import annotations

import time
from typing import Optional

from ..client import Client
from ..config import Config
from ..http.client import HttpClient


class ClientCredentials:
    def __init__(self, client_id: str, client_secret: str, config: Optional[Config] = None, http_client: Optional[HttpClient] = None):
        self.client_id = client_id
        self.client_secret = client_secret
        self.config = config or Config()
        self.http_client = http_client
        self._access_token: Optional[str] = None
        self._expires_at: Optional[int] = None

    def token(self, scope: Optional[str] = None):
        payload = {"grant_type": "client_credentials", "client_id": self.client_id, "client_secret": self.client_secret}
        if scope:
            payload["scope"] = scope
        response = Client(Config(self.config.base_url, None, self.config.timeout_seconds, self.config.debug, self.config.api_header_prefix), self.http_client).request("POST", "/oauth/token", payload)
        self._access_token = str(response.get("access_token", ""))
        self._expires_at = int(time.time()) + int(response.get("expires_in", 3600))
        return response

    def access_token(self, scope: Optional[str] = None) -> str:
        if not self._access_token or not self._expires_at or self._expires_at <= int(time.time()) + 60:
            self.token(scope)
        return self._access_token or ""
    def revoke(self, token: str):
        payload = {"client_id": self.client_id, "client_secret": self.client_secret, "token": token}
        return Client(Config(self.config.base_url, None, self.config.timeout_seconds, self.config.debug, self.config.api_header_prefix), self.http_client).request("POST", "/oauth/revoke", payload)
