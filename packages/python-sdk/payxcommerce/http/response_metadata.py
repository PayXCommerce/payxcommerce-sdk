import re
import time
from email.utils import parsedate_to_datetime, format_datetime

MAX_RETRY_SECONDS = 300
HEADER_NAMES = {"retry-after", "x-ratelimit-limit", "x-ratelimit-remaining", "x-ratelimit-reset", "x-request-id", "x-error-group-id"}


def retry_after(value, now=None):
    if not isinstance(value, str) or len(value) > 128:
        return None
    value = value.strip()
    if re.fullmatch(r"[0-9]+", value):
        return min(MAX_RETRY_SECONDS, int(value))
    try:
        date = parsedate_to_datetime(value)
        if format_datetime(date, usegmt=True) != value:
            return None
        seconds = int(date.timestamp()) - (int(time.time()) if now is None else now)
        return None if seconds < 0 else min(MAX_RETRY_SECONDS, seconds)
    except (ValueError, OverflowError, TypeError):
        return None


def safe_headers(headers=None):
    safe, seen = {}, set()
    items = headers.items() if headers is not None else []
    for raw_name, raw_value in items:
        name = str(raw_name).lower().strip()
        if name not in HEADER_NAMES:
            continue
        if name in seen:
            safe.pop(name, None)
            continue
        seen.add(name)
        if not isinstance(raw_value, str) or len(raw_value) > 128 or re.search(r"[\x00-\x1f\x7f]", raw_value):
            continue
        value = raw_value.strip()
        valid = retry_after(value) is not None if name == "retry-after" else bool(re.fullmatch(
            r"[0-9]{1,12}" if name.startswith("x-ratelimit-") else r"[A-Za-z0-9_.:-]{1,128}", value))
        if valid:
            safe[name] = value
    return safe
