<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce\Api;

use PayXCommerce\Auth\BearerTokenAuth;
use PayXCommerce\Auth\HmacAuth;
use PayXCommerce\Client;
use PayXCommerce\Config;
use PayXCommerce\OAuth\ClientCredentials;
use PayXCommerce\WooCommerce\Admin\Settings;

final class SdkFactory
{
    public function __construct(private $optionResolver)
    {
    }

    public function client(): Client
    {
        $baseUrl = $this->option('base_url', Settings::DEFAULT_BASE_URL);
        $authMethod = $this->option('auth_method', 'hmac');

        if ($authMethod === 'bearer') {
            return new Client(new Config(baseUrl: $baseUrl, auth: new BearerTokenAuth($this->accessToken())));
        }

        return new Client(new Config(baseUrl: $baseUrl, auth: new HmacAuth(
            publicKey: $this->option('public_key'),
            secretKey: $this->option('secret_key')
        )));
    }

    public function validateCredentials(): void
    {
        $this->client()->balance()->get();
    }

    private function accessToken(): string
    {
        $clientId = $this->option('client_id');
        $cacheKey = 'payxcommerce_bearer_token_' . md5($clientId);
        $cached = get_transient($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $oauth = new ClientCredentials(
            clientId: $clientId,
            clientSecret: $this->option('client_secret'),
            config: new Config(baseUrl: $this->option('base_url', Settings::DEFAULT_BASE_URL))
        );
        $response = $oauth->token(Settings::TOKEN_SCOPE);
        $token = (string) ($response['access_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('Token response did not include an access token.');
        }
        set_transient($cacheKey, $token, max(60, (int) ($response['expires_in'] ?? 3600) - 60));
        return $token;
    }

    private function option(string $key, string $default = ''): string
    {
        return (string) call_user_func($this->optionResolver, $key, $default);
    }
}
