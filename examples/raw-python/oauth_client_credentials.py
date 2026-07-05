from payxcommerce import DEFAULT_BASE_URL, oauth_client_credentials, print_response

CLIENT_ID = "YOUR_DEVELOPER_APP_CLIENT_ID"
CLIENT_SECRET = "YOUR_DEVELOPER_APP_CLIENT_SECRET"

print_response(oauth_client_credentials(DEFAULT_BASE_URL, CLIENT_ID, CLIENT_SECRET))
