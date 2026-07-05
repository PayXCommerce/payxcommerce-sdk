class Balance:
    def __init__(self, client):
        self.client = client

    def get(self):
        return self.client.request("GET", "/balance")
