from __future__ import annotations

import secrets


def generate(prefix: str = "pxc") -> str:
    return prefix + "_" + secrets.token_hex(16)
