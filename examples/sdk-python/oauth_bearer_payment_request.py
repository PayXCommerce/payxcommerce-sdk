from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "packages" / "python-sdk"))

from payxcommerce import BearerTokenAuth, Client, ClientCredentials, Config
from payxcommerce.exceptions import ApiException
from payxcommerce.webhooks import event_types


def print_sdk_error(error):
    print(f"PayXCommerce API error: {error}", file=sys.stderr)
    if getattr(error, "status_code", None):
        print(f"HTTP status: {error.status_code}", file=sys.stderr)
    if getattr(error, "payx_error_code", None):
        print(f"Error code: {error.payx_error_code}", file=sys.stderr)
    if getattr(error, "errors", None):
        print("Validation details:", file=sys.stderr)
        for field, messages in error.errors.items():
            if not isinstance(messages, list):
                messages = [messages]
            for message in messages:
                print(f" - {field}: {message}", file=sys.stderr)
    raise SystemExit(1)


try:
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
except ApiException as error:
    print_sdk_error(error)
