from time import time
from payxcommerce import DEFAULT_BASE_URL, default_ipn_events, endpoint, json_request, print_response

ACCESS_TOKEN = "YOUR_DEVELOPER_APP_ACCESS_TOKEN"

payload = {
    "amount": 125.50,
    "currency": "USD",
    "purpose": "Invoice INV-1002",
    "customer": {"name": "Jane Customer", "email": "customer@example.com", "country": "United States"},
    "merchant_reference": "CRM-1002",
    "merchant_order_id": "ORDER-1002",
    "success_url": "https://example.com/payment/success",
    "failed_url": "https://example.com/payment/failed",
    "cancel_url": "https://example.com/payment/cancel",
    "webhook_url": "https://example.com/payxcommerce/webhook/order-1001",
    "ipn_events": default_ipn_events(),
    "metadata": {"source": "raw-python-bearer-example"},
    "is_test": True,
}

headers = {"Authorization": f"Bearer {ACCESS_TOKEN}", "Idempotency-Key": f"raw-python-bearer-order-1002-{int(time())}"}
print_response(json_request("POST", endpoint(DEFAULT_BASE_URL, "/payment-requests"), headers, payload))
