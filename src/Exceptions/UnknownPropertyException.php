<?php

declare(strict_types=1);

namespace AltDTO\Exceptions;

/**
 * Thrown when payload contains unknown properties in strict mode.
 */
class UnknownPropertyException extends DTOException
{
    /**
     * @param class-string $className
     */
    public static function forProperty(string $className, string $propertyName): self
    {
        return new self(sprintf('Unknown property "%s" for DTO "%s".', $propertyName, $className));
    }
}
