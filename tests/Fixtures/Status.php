<?php

declare(strict_types=1);

namespace AltDTO\Tests\Fixtures;

enum Status: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
