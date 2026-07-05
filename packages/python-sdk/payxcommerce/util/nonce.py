from __future__ import annotations

import secrets


def generate(bytes_count: int = 16) -> str:
    return secrets.token_hex(bytes_count)
