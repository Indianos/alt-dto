<?php

declare(strict_types=1);

namespace AltDTO\Tests\Fixtures;

use AltDTO\DTO;

final class NodeDTO extends DTO
{
    public string $name;

    public ?NodeDTO $next = null;
}
