<?php

declare(strict_types=1);

namespace PayXCommerce\Util;

final class Environment
{
    public static function normalize(?string $environment): string
    {
        return strtolower((string) $environment) === 'live' ? 'live' : 'test';
    }

    public static function isTest(?string $environment): bool
    {
        return self::normalize($environment) !== 'live';
    }

    public static function credentialEnvironment(?string $publicKey): ?string
    {
        $publicKey = strtolower(trim((string) $publicKey));

        if (str_starts_with($publicKey, 'pk_test_')) {
            return 'test';
        }

        if (str_starts_with($publicKey, 'pk_live_')) {
            return 'live';
        }

        return null;
    }

    public static function assertHmacCredentialMatches(?string $publicKey, ?string $environment): void
    {
        $expected = self::normalize($environment);
        $actual = self::credentialEnvironment($publicKey);

        if ($actual !== null && $actual !== $expected) {
            throw new \InvalidArgumentException(sprintf(
                'PayXCommerce %s mode requires %s API keys. Update the plugin environment or use matching credentials.',
                $expected === 'live' ? 'Live' : 'Test',
                $expected === 'live' ? 'live' : 'test'
            ));
        }
    }
}
