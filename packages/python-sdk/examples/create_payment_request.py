from payxcommerce import Client, Config, HmacAuth
from payxcommerce.webhooks import event_types

client = Client(Config(auth=HmacAuth("YOUR_PUBLIC_KEY", "YOUR_SECRET_KEY")))

response = client.payment_requests().create({
    "amount": 125.50,
    "currency": "USD",
    "purpose": "SDK example order",
    "customer": {"name": "Jane Customer", "email": "customer@example.com", "country": "United States"},
    "merchant_reference": "PYSDK-1001",
    "merchant_order_id": "ORDER-1001",
    "success_url": "https://example.com/payment/success",
    "failed_url": "https://example.com/payment/failed",
    "cancel_url": "https://example.com/payment/cancel",
    "webhook_url": "https://example.com/payxcommerce/webhook",
    "ipn_events": event_types.default_subscriptions(),
    "metadata": {"source": "python-sdk-example"},
    "is_test": True,
})

print(response.get("checkout_url"))
