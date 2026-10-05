<?php

declare(strict_types=1);

namespace AltDTO\Transformation\Metadata;

/**
 * Reflection metadata for one DTO property.
 */
final readonly class PropertyMetadata
{
    /**
     * @param class-string|null $customParser
     */
    public function __construct(
        public string       $name,
        public TypeMetadata $type,
        public bool         $required,
        public bool         $hasDefault,
        public mixed        $defaultValue,
        public ?string      $customParser,
        public bool         $isPublic
    ) {
    }
}
