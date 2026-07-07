from __future__ import annotations

import json
import pathlib
import sys
import time
import hmac
import hashlib

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1]))

from payxcommerce.auth.hmac import HmacAuth
from payxcommerce.exceptions import ValidationException, WebhookVerificationException
from payxcommerce.http.client import HttpClient
from payxcommerce.util import redactor
from payxcommerce.webhooks import event_types
from payxcommerce.webhooks.verifier import Verifier


tests = 0


def assert_same(expected, actual, message):
    global tests
    tests += 1
    if expected != actual:
        raise RuntimeError(f"{message}\nExpected: {expected!r}\nActual: {actual!r}")


def assert_true(condition, message):
    global tests
    tests += 1
    if not condition:
        raise RuntimeError(message)


body = '{"amount":100,"currency":"USD"}'
expected_signature = hmac.new(b"secret123", f"1710000000.nonce123.{body}".encode(), hashlib.sha256).hexdigest()
assert_same(expected_signature, HmacAuth.sign("1710000000", "nonce123", body, "secret123"), "HMAC signature should match expected hash.")

event_id = "PXEVT-TEST"
payload = {"event_id": event_id, "event_type": event_types.PAYMENT_SUCCEEDED, "amount": 100}
raw_body = json.dumps(payload, separators=(",", ":"))
signature = Verifier.signature(event_id, raw_body, "webhook_secret")
decoded = Verifier("webhook_secret").verify(raw_body, {"X-PXC-Event-ID": event_id, "X-PXC-Timestamp": str(int(time.time())), "X-PXC-Signature": signature})
assert_same(event_types.PAYMENT_SUCCEEDED, decoded["event_type"], "Webhook verifier should return decoded payload.")
assert_true(event_types.is_successful_payment("payment.success"), "Legacy success event should be recognized.")
assert_same("secret=[redacted]", redactor.text("secret=abc123"), "Redactor should hide secrets in text.")
assert_same("[redacted]", redactor.context({"client_secret": "abc123"})["client_secret"], "Redactor should hide secret context values.")

try:
    Verifier("webhook_secret").verify(raw_body, {"X-PXC-Event-ID": event_id, "X-PXC-Timestamp": str(int(time.time())), "X-PXC-Signature": "invalid"})
    raise RuntimeError("Invalid webhook signature should fail.")
except WebhookVerificationException:
    assert_true(True, "Invalid webhook signature failed as expected.")

try:
    HttpClient()._throw_for_status(422, {
        "message": "The given data was invalid.",
        "errors": {"customer.country": ["The customer.country field is required."]},
    }, '{"message":"The given data was invalid."}')
    raise RuntimeError("Validation response should fail.")
except ValidationException as error:
    assert_true("customer.country: The customer.country field is required." in str(error), "Validation error should include field details.")
    assert_same("The customer.country field is required.", error.errors["customer.country"][0], "Validation exception should expose structured errors.")

print(f"PayXCommerce Python SDK tests passed ({tests} assertions).")
