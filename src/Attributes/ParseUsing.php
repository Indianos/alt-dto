<?php

declare(strict_types=1);

namespace AltDTO\Attributes;

use Attribute;

/**
 * Declares a property-level parser that overrides built-in parsing.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ParseUsing
{
    /**
     * @param class-string $inputParserClass
     */
    public function __construct(public string $inputParserClass)
    {
    }
}
