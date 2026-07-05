from time import time
from payxcommerce import DEFAULT_BASE_URL, endpoint, hmac_headers, json_body, json_request, print_response

PUBLIC_KEY = "YOUR_PUBLIC_KEY"
SECRET_KEY = "YOUR_SECRET_KEY"

payload = {"transaction_reference": "PXTRX-YYYYMMDD-XXXXXX", "amount": 25.00, "reason": "Customer requested partial refund"}
headers = hmac_headers(PUBLIC_KEY, SECRET_KEY, json_body(payload), f"raw-python-refund-{int(time())}")
print_response(json_request("POST", endpoint(DEFAULT_BASE_URL, "/refunds"), headers, payload))
