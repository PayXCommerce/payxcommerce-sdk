from payxcommerce.webhooks.verifier import Verifier

raw_body = '{"event_id":"PXEVT-TEST","event_type":"payment.succeeded"}'
headers = {
    "X-PXC-Event-ID": "PXEVT-TEST",
    "X-PXC-Timestamp": "REPLACE_WITH_UNIX_TIMESTAMP",
    "X-PXC-Signature": "REPLACE_WITH_SIGNATURE",
}

payload = Verifier("YOUR_WEBHOOK_SECRET").verify(raw_body, headers)
print(payload["event_type"])
