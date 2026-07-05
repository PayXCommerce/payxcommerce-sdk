<?php

declare(strict_types=1);

namespace PayXCommerce;

use PayXCommerce\Auth\AuthInterface;
use PayXCommerce\Http\CurlHttpClient;
use PayXCommerce\Http\HttpClientInterface;
use PayXCommerce\Resources\Balance;
use PayXCommerce\Resources\PaymentRequests;
use PayXCommerce\Resources\Refunds;
use PayXCommerce\Resources\Transactions;

final class Client
{
    private HttpClientInterface $http;

    public function __construct(
        private readonly Config $config,
        ?HttpClientInterface $http = null
    ) {
        $this->http = $http ?: new CurlHttpClient($config);
    }

    public function request(string $method, string $path, ?array $payload = null, array $headers = [], ?AuthInterface $auth = null): array
    {
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new \InvalidArgumentException('Unable to encode request payload as JSON.');
        }

        $headers = array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $headers);

        $auth = $auth ?: $this->config->auth;
        if ($auth) {
            $headers = $auth->headers($method, $path, $body, $headers, $this->config);
        }

        return $this->http->send($method, $this->config->endpoint($path), $headers, $body);
    }

    public function paymentRequests(): PaymentRequests
    {
        return new PaymentRequests($this);
    }

    public function balance(): Balance
    {
        return new Balance($this);
    }

    public function transactions(): Transactions
    {
        return new Transactions($this);
    }

    public function refunds(): Refunds
    {
        return new Refunds($this);
    }
}

