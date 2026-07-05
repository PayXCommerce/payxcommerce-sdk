from payxcommerce import DEFAULT_BASE_URL, endpoint, hmac_headers, json_request, print_response

PUBLIC_KEY = "YOUR_PUBLIC_KEY"
SECRET_KEY = "YOUR_SECRET_KEY"

print_response(json_request("GET", endpoint(DEFAULT_BASE_URL, "/balance"), hmac_headers(PUBLIC_KEY, SECRET_KEY, "")))
