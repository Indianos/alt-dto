<?php

declare(strict_types=1);

namespace AltDTO\Transformation\Reflection;

use AltDTO\Attributes\ParseUsing;
use AltDTO\Contracts\MetadataCacheInterface;
use AltDTO\Transformation\Metadata\ClassMetadata;
use AltDTO\Transformation\Metadata\PropertyMetadata;
use ReflectionClass;
use ReflectionProperty;

/**
 * Builds class metadata once and stores it in a configurable cache.
 */
final readonly class MetadataFactory
{
    public function __construct(
        private MetadataCacheInterface $cache,
        private DocBlockParser         $docBlockParser,
        private TypeResolver           $typeResolver
    ) {
    }

    /**
     * @param class-string $className
     */
    public function get(string $className): ClassMetadata
    {
        $key = 'class-meta:' . $className;

        return $this->cache->getOrCompute($key, fn(): ClassMetadata => $this->build($className));
    }

    /**
     * @param class-string $className
     */
    private function build(string $className): ClassMetadata
    {
        $reflection = new ReflectionClass($className);

        $properties = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $docType = $this->docBlockParser->parse($property->getDocComment() ?: null);
            $docType = $this->qualifyDocTypeClasses($docType, $reflection->getNamespaceName());
            $type = $this->typeResolver->resolve($property, $docType);

            $attr = $property->getAttributes(ParseUsing::class)[0] ?? null;
            $customParser = null;
            if ($attr !== null) {
                /** @var ParseUsing $instance */
                $instance = $attr->newInstance();
                $customParser = $instance->inputParserClass;
            }

            $name = $property->getName();
            $hasDefault = $property->hasDefaultValue();

            $properties[$name] = new PropertyMetadata(
                name: $name,
                type: $type,
                required: !$hasDefault,
                hasDefault: $hasDefault,
                defaultValue: $hasDefault ? $property->getDefaultValue() : null,
                customParser: $customParser,
                isPublic: $property->isPublic()
            );
        }

        return new ClassMetadata($className, $properties);
    }

    /**
     * Resolves a bare class name found in a docblock array/collection element
     * type (e.g. "RoleDTO") against the declaring class's namespace when the
     * short name isn't already a known builtin/pseudo-type or class.
     *
     * @param array<string,mixed> $docType
     * @return array<string,mixed>
     */
    private function qualifyDocTypeClasses(array $docType, string $namespace): array
    {
        if (isset($docType['elementType']) && is_string($docType['elementType'])) {
            $docType['elementType'] = $this->qualifyClassName($docType['elementType'], $namespace);
        }

        return $docType;
    }

    private function qualifyClassName(string $typeName, string $namespace): string
    {
        static $pseudoTypes = ['mixed', 'int', 'float', 'string', 'bool', 'array', 'iterable', 'object', 'callable', 'resource', 'null', 'true', 'false', 'self', 'static', 'parent'];

        if ($typeName === '' || str_contains($typeName, '\\') || in_array(strtolower($typeName), $pseudoTypes, true)) {
            return $typeName;
        }

        if (class_exists($typeName) || interface_exists($typeName) || enum_exists($typeName)) {
            return $typeName;
        }

        if ($namespace !== '') {
            $candidate = $namespace . '\\' . $typeName;
            if (class_exists($candidate) || interface_exists($candidate) || enum_exists($candidate)) {
                return $candidate;
            }
        }

        return $typeName;
    }
}
