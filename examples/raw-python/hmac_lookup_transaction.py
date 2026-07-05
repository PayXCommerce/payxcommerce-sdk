from urllib.parse import quote
from payxcommerce import DEFAULT_BASE_URL, endpoint, hmac_headers, json_request, print_response

PUBLIC_KEY = "YOUR_PUBLIC_KEY"
SECRET_KEY = "YOUR_SECRET_KEY"
TRANSACTION_REFERENCE = "PXTRX-YYYYMMDD-XXXXXX"

path = "/transactions/" + quote(TRANSACTION_REFERENCE, safe="")
print_response(json_request("GET", endpoint(DEFAULT_BASE_URL, path), hmac_headers(PUBLIC_KEY, SECRET_KEY, "")))
