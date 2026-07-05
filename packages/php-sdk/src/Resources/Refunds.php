<?php

declare(strict_types=1);

namespace PayXCommerce\Resources;

use PayXCommerce\Client;

final class Refunds
{
    public function __construct(private readonly Client $client)
    {
    }

    public function create(array $payload, ?string $idempotencyKey = null): array
    {
        $headers = $idempotencyKey ? ['Idempotency-Key' => $idempotencyKey] : [];
        return $this->client->request('POST', '/refunds', $payload, $headers);
    }
}

