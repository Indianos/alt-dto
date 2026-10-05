<?php

declare(strict_types=1);

namespace AltDTO\Transformation\Metadata;

/**
 * Reflection metadata for one DTO class.
 */
final readonly class ClassMetadata
{
    /**
     * @param class-string $className
     * @param array<string,PropertyMetadata> $properties
     */
    public function __construct(
        public string $className,
        public array  $properties
    ) {
    }
}
