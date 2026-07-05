<?php

declare(strict_types=1);

namespace PayXCommerce\Util;

final class Idempotency
{
    public static function generate(string $prefix = 'pxc'): string
    {
        return $prefix . '_' . bin2hex(random_bytes(16));
    }
}

