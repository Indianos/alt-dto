<?php

declare(strict_types=1);

namespace AltDTO\Tests\Fixtures;

use AltDTO\DTO;

final class FlatDTO extends DTO
{
    public int $id;

    public string $name;

    public ?string $status = null;
}
