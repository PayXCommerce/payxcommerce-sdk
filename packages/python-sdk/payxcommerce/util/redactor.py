from __future__ import annotations

import re
from typing import Any

_PATTERN = re.compile(r"(secret|token|signature|authorization|password|key|client_secret|secret_key|webhook_secret)([^\s:=]*)?([:=]\s*)?([A-Za-z0-9_\-.!@$%^&*+/=]+)", re.IGNORECASE)


def text(message: str) -> str:
    return _PATTERN.sub(r"\1\2\3[redacted]", message)


def context(values: dict[str, Any]) -> dict[str, Any]:
    redacted: dict[str, Any] = {}
    for key, value in values.items():
        if re.search(r"secret|token|signature|authorization|password|key", str(key), re.IGNORECASE):
            redacted[key] = "[redacted]"
        elif isinstance(value, str):
            redacted[key] = text(value)
        elif isinstance(value, dict):
            redacted[key] = context(value)
        else:
            redacted[key] = value
    return redacted
