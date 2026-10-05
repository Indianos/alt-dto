<?php

declare(strict_types=1);

namespace AltDTO\Exceptions;

/**
 * Thrown when a value cannot be converted to an expected property type.
 */
class InvalidPropertyTypeException extends DTOException
{
    /**
     * @param class-string $className
     */
    public static function forProperty(string $className, string $propertyName, string $expectedType, mixed $actualValue): self
    {
        $actualType = get_debug_type($actualValue);

        return new self(sprintf(
            'Invalid type for property "%s::%s". Expected "%s", got "%s".',
            $className,
            $propertyName,
            $expectedType,
            $actualType
        ));
    }
}
