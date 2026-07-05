<?php

declare(strict_types=1);

namespace PayXCommerce\Resources;

use PayXCommerce\Client;

final class Transactions
{
    public function __construct(private readonly Client $client)
    {
    }

    public function lookup(string $transactionReference): array
    {
        return $this->client->request('GET', '/transactions/' . rawurlencode($transactionReference));
    }
}

