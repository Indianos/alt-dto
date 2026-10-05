<?php

declare(strict_types=1);

namespace AltDTO\Tests\Support;

use AltDTO\Contracts\PropertyParserInterface;

final class HexIntParser implements PropertyParserInterface
{
    public function parse(mixed $value, ...$otherParams): mixed
    {
        if (is_string($value) && str_starts_with($value, '0x')) {
            return hexdec(substr($value, 2));
        }

        return $value;
    }
}
