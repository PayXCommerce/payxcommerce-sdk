from urllib.parse import quote


class Transactions:
    def __init__(self, client):
        self.client = client

    def lookup(self, transaction_reference: str):
        return self.client.request("GET", "/transactions/" + quote(transaction_reference, safe=""))
