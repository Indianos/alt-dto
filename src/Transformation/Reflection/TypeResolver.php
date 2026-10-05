<?php

declare(strict_types=1);

namespace AltDTO\Transformation\Reflection;

use AltDTO\Transformation\Metadata\TypeMetadata;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * Resolves combined reflection and docblock type metadata.
 */
final class TypeResolver
{
    /**
     * @param array<string,mixed> $docType
     */
    public function resolve(ReflectionProperty $property, array $docType): TypeMetadata
    {
        $reflectionType = $property->getType();

        $allowsNull = false;
        $types = [];

        if ($reflectionType !== null) {
            $allowsNull = $reflectionType->allowsNull();
            $types = $this->getTypes($reflectionType);
        }

        if ($types === []) {
            $types = ['mixed'];
        }

        $isMixed = in_array('mixed', $types, true);
        $isCollection = in_array('AltDTO\\Collection', $types, true) || (bool) ($docType['isCollection'] ?? false);
        $isList = (bool) ($docType['isList'] ?? false);

        return new TypeMetadata(
            allowsNull: $allowsNull,
            namedTypes: $types,
            docType: isset($docType['raw']) ? (string) $docType['raw'] : null,
            elementType: isset($docType['elementType']) ? (string) $docType['elementType'] : null,
            isList: $isList,
            isCollection: $isCollection,
            isMixed: $isMixed
        );
    }

    /**
     * @return list<string>
     */
    private function getTypes(ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName()];
        }

        if ($type instanceof ReflectionUnionType) {
            $named = [];
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof ReflectionNamedType) {
                    $named[] = $unionType->getName();
                    continue;
                }
            }

            return $named;
        }

        return [];
    }
}
