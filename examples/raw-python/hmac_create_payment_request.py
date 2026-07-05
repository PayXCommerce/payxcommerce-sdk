from time import time
from payxcommerce import DEFAULT_BASE_URL, default_ipn_events, endpoint, hmac_headers, json_body, json_request, print_response

PUBLIC_KEY = "YOUR_PUBLIC_KEY"
SECRET_KEY = "YOUR_SECRET_KEY"

payload = {
    "amount": 125.50,
    "currency": "USD",
    "purpose": "Invoice INV-1001",
    "customer": {"name": "Jane Customer", "email": "customer@example.com", "mobile": "+15551234567", "country": "United States"},
    "merchant_reference": "CRM-1001",
    "merchant_order_id": "ORDER-1001",
    "success_url": "https://example.com/payment/success",
    "failed_url": "https://example.com/payment/failed",
    "cancel_url": "https://example.com/payment/cancel",
    "webhook_url": "https://example.com/payxcommerce/webhook",
    "ipn_events": default_ipn_events(),
    "metadata": {"source": "raw-python-example"},
    "is_test": True,
}

headers = hmac_headers(PUBLIC_KEY, SECRET_KEY, json_body(payload), f"raw-python-order-1001-{int(time())}")
print_response(json_request("POST", endpoint(DEFAULT_BASE_URL, "/payment-requests"), headers, payload))
