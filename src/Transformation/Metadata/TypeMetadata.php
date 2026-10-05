<?php

declare(strict_types=1);

namespace AltDTO\Transformation\Metadata;

/**
 * Immutable type information for a DTO property.
 */
final readonly class TypeMetadata implements \Stringable
{
    /**
     * @param list<string> $namedTypes
     * @param string|null $elementType the element type for array/iterable/list/Collection properties
     */
    public function __construct(
        public bool    $allowsNull,
        public array   $namedTypes,
        public ?string $docType,
        public ?string $elementType,
        public bool    $isList,
        public bool    $isCollection,
        public bool    $isMixed
    ) {
    }

    public function __toString(): string
    {
        $parts = $this->namedTypes;

        if ($parts === []) {
            $parts[] = 'mixed';
        }

        if ($this->allowsNull && !in_array('null', $parts, true)) {
            $parts[] = 'null';
        }

        return implode('|', $parts);
    }
}
