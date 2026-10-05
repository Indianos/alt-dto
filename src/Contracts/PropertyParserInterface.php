<?php

declare(strict_types=1);

namespace AltDTO\Contracts;

use AltDTO\Transformation\Hydration\Hydrator;
use AltDTO\Transformation\Metadata\PropertyMetadata;

/**
 * Custom per-property parser, used via #[ParseUsing(...)] to override the
 * hydrator's built-in type-based conversion for a single property.
 */
interface PropertyParserInterface
{
    public function parse(mixed $value, PropertyMetadata $property, Hydrator $hydrator): mixed;
}


