from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "packages" / "python-sdk"))

from payxcommerce import Client, Config, HmacAuth
from payxcommerce.webhooks import event_types

client = Client(Config(auth=HmacAuth("YOUR_PUBLIC_KEY", "YOUR_SECRET_KEY")))

response = client.payment_requests().create({
    "amount": 125.50,
    "currency": "USD",
    "purpose": "SDK example order",
    "customer": {"name": "Jane Customer", "email": "customer@example.com", "country": "United States"},
    "ipn_events": event_types.default_subscriptions(),
    "metadata": {"source": "sdk-python-example"},
    "is_test": True,
})

print(response.get("checkout_url"))
