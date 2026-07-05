import os
import sys
from payxcommerce import verify_webhook

WEBHOOK_SECRET = "YOUR_WEBHOOK_SECRET"

raw_body = sys.stdin.read()
headers = {
    "X-PXC-Event-ID": os.environ.get("HTTP_X_PXC_EVENT_ID", ""),
    "X-PXC-Timestamp": os.environ.get("HTTP_X_PXC_TIMESTAMP", ""),
    "X-PXC-Signature": os.environ.get("HTTP_X_PXC_SIGNATURE", ""),
}

try:
    payload = verify_webhook(raw_body, headers, WEBHOOK_SECRET)
except Exception as exc:
    print(f"Invalid webhook: {exc}")
    raise SystemExit(1)

# Store event_id and skip duplicates before updating local order status.
# Map event_type to your local order lifecycle.
print("PayXCommerce webhook verified:", payload.get("event_type", "unknown"))
