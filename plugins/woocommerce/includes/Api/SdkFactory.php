<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce\Api;

use PayXCommerce\Auth\BearerTokenAuth;
use PayXCommerce\Auth\HmacAuth;
use PayXCommerce\Client;
use PayXCommerce\Config;
use PayXCommerce\OAuth\ClientCredentials;
use PayXCommerce\Util\Environment;
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

        Environment::assertHmacCredentialMatches($this->option('public_key'), $this->option('environment', 'test'));

        return new Client(new Config(baseUrl: $baseUrl, auth: new HmacAuth(
            publicKey: $this->option('public_key'),
            secretKey: $this->option('secret_key')
        )));
    }

    public function validateCredentials(): void
    {
        $this->client()->balance()->get();
    }

    public function clearAccessTokenCache(): void
    {
        delete_transient($this->tokenCacheKey());
    }

    public function retryAfterSeconds(): int
    {
        return max(0, min(300, (int) get_transient($this->cooldownKey()) - time()));
    }

    public function deferRequests(int $seconds): void
    {
        $seconds = max(1, min(300, $seconds));
        $until = min(time() + 300, max(time() + $seconds, (int) get_transient($this->cooldownKey())));
        set_transient($this->cooldownKey(), $until, max(1, $until - time()));
    }

    private function cooldownKey(): string
    {
        return 'payxcommerce_retry_' . hash('sha256', implode('|', [
            $this->option('base_url', Settings::DEFAULT_BASE_URL), $this->option('environment', 'test'),
            $this->option('auth_method', 'hmac'), $this->option('public_key'), $this->option('client_id'),
        ]));
    }

    private function accessToken(): string
    {
        $cacheKey = $this->tokenCacheKey();
        $cached = get_transient($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $oauth = new ClientCredentials(
            clientId: $this->option('client_id'),
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

    private function tokenCacheKey(): string
    {
        return 'payxcommerce_bearer_token_' . md5(implode('|', [
            $this->option('base_url', Settings::DEFAULT_BASE_URL),
            $this->option('environment', 'test'),
            $this->option('client_id'),
            Settings::TOKEN_SCOPE,
        ]));
    }

    private function option(string $key, string $default = ''): string
    {
        return (string) call_user_func($this->optionResolver, $key, $default);
    }
}
