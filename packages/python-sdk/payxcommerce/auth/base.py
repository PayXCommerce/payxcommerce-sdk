from __future__ import annotations

from typing import Protocol


class AuthInterface(Protocol):
    def headers(self, method: str, path: str, body: str, headers: dict[str, str], config) -> dict[str, str]:
        ...
