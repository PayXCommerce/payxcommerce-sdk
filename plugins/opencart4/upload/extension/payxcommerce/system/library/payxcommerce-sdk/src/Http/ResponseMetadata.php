<?php

declare(strict_types=1);

namespace PayXCommerce\Http;

/** Strict, bounded response metadata. Never returns cookie/auth/location headers. */
final class ResponseMetadata
{
    public const MAX_RETRY_SECONDS = 300;
    public const HEADER_NAMES = ['retry-after','x-ratelimit-limit','x-ratelimit-remaining','x-ratelimit-reset','x-request-id','x-error-group-id'];

    public static function headers(array $headers): array
    {
        $safe = [];$seen = [];
        foreach ($headers as $name => $value) {
            $name = strtolower(trim((string) $name));
            if (!in_array($name, self::HEADER_NAMES, true)) continue;
            if (isset($seen[$name])) {unset($safe[$name]);continue;}
            $seen[$name] = true;
            if (!is_string($value)) continue;
            $value = trim((string) $value);
            if (strlen($value) > 128 || preg_match('/[\x00-\x1f\x7f]/', $value)) continue;
            if ($name === 'retry-after' && self::retryAfter($value) !== null) $safe[$name] = $value;
            elseif (in_array($name, ['x-ratelimit-limit','x-ratelimit-remaining','x-ratelimit-reset'], true)
                && preg_match('/^\d{1,12}$/D', $value)) $safe[$name] = $value;
            elseif (in_array($name, ['x-request-id','x-error-group-id'], true)
                && preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $value)) $safe[$name] = $value;
        }
        return $safe;
    }

    public static function retryAfter(?string $value, ?int $now = null): ?int
    {
        if ($value === null) return null;
        $value = trim($value);
        if (strlen($value) > 128) return null;
        if (preg_match('/^\d+$/D', $value)) {
            // Bound before casting; arbitrary-length integers cannot overflow.
            $digits = ltrim($value, '0');
            return strlen($digits) > 3 ? self::MAX_RETRY_SECONDS : min(self::MAX_RETRY_SECONDS, (int) $digits);
        }
        $date = \DateTimeImmutable::createFromFormat('!D, d M Y H:i:s \G\M\T', $value, new \DateTimeZone('UTC'));
        if (!$date || $date->format('D, d M Y H:i:s \G\M\T') !== $value) return null;
        $delta = $date->getTimestamp() - ($now ?? time());
        return $delta < 0 ? null : min(self::MAX_RETRY_SECONDS, $delta);
    }
}
