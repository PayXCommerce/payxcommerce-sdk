from __future__ import annotations

from dataclasses import dataclass
from typing import Optional

from .auth.base import AuthInterface


@dataclass(frozen=True)
class Config:
    base_url: str = "https://payxcommerce.com/api/v1"
    auth: Optional[AuthInterface] = None
    timeout_seconds: int = 30
    debug: bool = False
    api_header_prefix: str = "PXC"

    def endpoint(self, path: str) -> str:
        return self.base_url.rstrip("/") + "/" + path.lstrip("/")

    def api_header(self, name: str) -> str:
        return "X-" + self.api_header_prefix.upper() + "-" + name
