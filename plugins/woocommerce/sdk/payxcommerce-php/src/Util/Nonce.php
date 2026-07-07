<?php

declare(strict_types=1);

namespace PayXCommerce\Util;

final class Nonce
{
    public static function generate(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }
}

