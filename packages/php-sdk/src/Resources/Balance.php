<?php

declare(strict_types=1);

namespace PayXCommerce\Resources;

use PayXCommerce\Client;

final class Balance
{
    public function __construct(private readonly Client $client)
    {
    }

    public function get(): array
    {
        return $this->client->request('GET', '/balance');
    }
}

