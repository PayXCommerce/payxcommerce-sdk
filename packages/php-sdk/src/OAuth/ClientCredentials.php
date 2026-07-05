<?php

declare(strict_types=1);

namespace PayXCommerce\OAuth;

use PayXCommerce\Client;
use PayXCommerce\Config;
use PayXCommerce\Http\HttpClientInterface;

final class ClientCredentials
{
    private ?string $accessToken = null;
    private ?int $expiresAt = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly Config $config = new Config(),
        private readonly ?HttpClientInterface $http = null,
    ) {
    }

    public function token(?string $scope = null): array
    {
        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ];

        if ($scope !== null && $scope !== '') {
            $payload['scope'] = $scope;
        }

        $client = new Client(new Config($this->config->baseUrl, null, $this->config->timeoutSeconds, $this->config->debug, $this->config->apiHeaderPrefix), $this->http);
        $response = $client->request('POST', '/oauth/token', $payload);

        $this->accessToken = (string) ($response['access_token'] ?? '');
        $this->expiresAt = time() + (int) ($response['expires_in'] ?? 3600);

        return $response;
    }

    public function accessToken(?string $scope = null): string
    {
        if (!$this->accessToken || !$this->expiresAt || $this->expiresAt <= time() + 60) {
            $this->token($scope);
        }

        return (string) $this->accessToken;
    }

    public function revoke(string $token): array
    {
        $client = new Client(new Config($this->config->baseUrl, null, $this->config->timeoutSeconds, $this->config->debug, $this->config->apiHeaderPrefix), $this->http);

        return $client->request('POST', '/oauth/revoke', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'token' => $token,
        ]);
    }
}

