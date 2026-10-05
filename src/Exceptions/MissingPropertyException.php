<?php

declare(strict_types=1);

namespace AltDTO\Exceptions;

/**
 * Thrown when a required property is absent during hydration.
 */
class MissingPropertyException extends DTOException
{
    /**
     * @param class-string $className
     */
    public static function forProperty(string $className, string $propertyName): self
    {
        return new self(sprintf('Missing required property "%s" for DTO "%s".', $propertyName, $className));
    }
}
