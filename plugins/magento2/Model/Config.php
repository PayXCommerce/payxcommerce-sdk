<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const PATH = 'payment/payxcommerce/';
    public const MODULE_VERSION = '0.3.6';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function value(string $key, ?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::PATH . $key, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function secret(string $key, ?int $storeId = null): string
    {
        $value = $this->value($key, $storeId);
        return $value !== '' ? (string) $this->encryptor->decrypt($value) : '';
    }

    public function isConfigured(?int $storeId = null): bool
    {
        if ($this->secret('webhook_secret', $storeId) === '') {
            return false;
        }

        if ($this->value('auth_method', $storeId) === 'bearer') {
            return $this->secret('client_id', $storeId) !== '' && $this->secret('client_secret', $storeId) !== '';
        }

        return $this->secret('public_key', $storeId) !== '' && $this->secret('secret_key', $storeId) !== '';
    }

    public function environment(?int $storeId = null): string
    {
        return strtolower($this->value('environment', $storeId)) === 'live' ? 'live' : 'test';
    }

    public function assertEnvironmentCredentials(?int $storeId = null): void
    {
        if ($this->value('auth_method', $storeId) === 'bearer') {
            return;
        }

        $environment = $this->environment($storeId);
        $publicKey = strtolower(trim($this->secret('public_key', $storeId)));
        $keyEnvironment = null;
        if (str_starts_with($publicKey, 'pk_test_')) {
            $keyEnvironment = 'test';
        } elseif (str_starts_with($publicKey, 'pk_live_')) {
            $keyEnvironment = 'live';
        }

        if ($keyEnvironment !== null && $keyEnvironment !== $environment) {
            throw new \RuntimeException(sprintf(
                'PayXCommerce %s mode requires %s API keys. Update the Magento payment environment or use matching credentials.',
                $environment === 'live' ? 'Live' : 'Test',
                $environment === 'live' ? 'live' : 'test'
            ));
        }
    }

    public function brandName(?int $storeId = null): string
    {
        return $this->value('brand_name', $storeId) ?: 'PayXCommerce';
    }

    public function publicText(string $key, string $default, ?int $storeId = null): string
    {
        return str_replace('{brand}', $this->brandName($storeId), $this->value($key, $storeId) ?: $default);
    }

    public function csv(string $key, ?int $storeId = null): array
    {
        return array_values(array_filter(array_map(static fn($value) => strtoupper(trim($value)), explode(',', $this->value($key, $storeId)))));
    }
}
