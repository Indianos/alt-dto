<?php

declare(strict_types=1);

namespace AltDTO\Tests\Fixtures;

use AltDTO\Attributes\ParseUsing;
use AltDTO\DTO;
use AltDTO\Tests\Support\HexIntParser;

final class CustomParserDTO extends DTO
{
    #[ParseUsing(HexIntParser::class)]
    public int $id;
}
