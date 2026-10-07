from __future__ import annotations

from dataclasses import dataclass
from typing import Optional
from urllib.parse import urlparse

from .auth.base import AuthInterface


@dataclass(frozen=True)
class Config:
    base_url: str = "https://payxcommerce.com/api/v1"
    auth: Optional[AuthInterface] = None
    timeout_seconds: int = 30
    debug: bool = False
    api_header_prefix: str = "PXC"

    def __post_init__(self) -> None:
        parsed = urlparse(self.base_url)
        host = (parsed.hostname or "").lower()
        local = host in {"localhost", "127.0.0.1", "::1"} or host.endswith(".test")
        if not host or (parsed.scheme != "https" and not (parsed.scheme == "http" and local)):
            raise ValueError("PayXCommerce API base URL must use HTTPS (HTTP is allowed only for localhost or .test development hosts).")

    def endpoint(self, path: str) -> str:
        return self.base_url.rstrip("/") + "/" + path.lstrip("/")

    def api_header(self, name: str) -> str:
        return "X-" + self.api_header_prefix.upper() + "-" + name
