from payxcommerce import BearerTokenAuth, Client, ClientCredentials, Config
from payxcommerce.webhooks import event_types

credentials = ClientCredentials("YOUR_DEVELOPER_APP_CLIENT_ID", "YOUR_DEVELOPER_APP_CLIENT_SECRET")
access_token = credentials.access_token("payment_requests.write transactions.read balances.read refunds.write")
client = Client(Config(auth=BearerTokenAuth(access_token)))

response = client.payment_requests().create({
    "amount": 125.50,
    "currency": "USD",
    "purpose": "SDK bearer example order",
    "customer": {"name": "Jane Customer", "email": "customer@example.com", "country": "United States"},
    "webhook_url": "https://example.com/payxcommerce/webhook/order-1001",
    "ipn_events": event_types.default_subscriptions(),
    "metadata": {"source": "python-sdk-bearer-example"},
    "is_test": True,
})

print(response.get("checkout_url"))
