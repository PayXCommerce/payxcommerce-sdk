<?php

declare(strict_types=1);

namespace PayXCommerce\Util;

final class Redactor
{
    public static function text(string $message): string
    {
        return preg_replace('/(secret|token|signature|authorization|password|key|client_secret|secret_key|webhook_secret)([^\s:=]*)?([:=]\s*)?([A-Za-z0-9_\-.!@$%^&*+\/=]+)/i', '$1$2$3[redacted]', $message) ?: $message;
    }

    public static function context(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match('/secret|token|signature|authorization|password|key/i', (string) $key)) {
                $context[$key] = '[redacted]';
            } elseif (is_string($value)) {
                $context[$key] = self::text($value);
            } elseif (is_array($value)) {
                $context[$key] = self::context($value);
            }
        }

        return $context;
    }
}
