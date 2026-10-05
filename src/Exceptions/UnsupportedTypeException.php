<?php

declare(strict_types=1);

namespace AltDTO\Exceptions;

/**
 * Thrown when a declared property type is unsupported by the hydrator.
 */
class UnsupportedTypeException extends DTOException
{
    /**
     * @param class-string $className
     */
    public static function forProperty(string $className, string $propertyName, string $type): self
    {
        return new self(sprintf(
            'Unsupported type "%s" for property "%s::%s".',
            $type,
            $className,
            $propertyName
        ));
    }
}
