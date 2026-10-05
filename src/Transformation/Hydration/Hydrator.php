<?php

declare(strict_types=1);

namespace AltDTO\Transformation\Hydration;

use AltDTO\Collection;
use AltDTO\Configuration\DTOConfig;
use AltDTO\Contracts\PropertyParserInterface;
use AltDTO\Exceptions\InvalidPropertyTypeException;
use AltDTO\Exceptions\MetadataException;
use AltDTO\Exceptions\MissingPropertyException;
use AltDTO\Exceptions\UnknownPropertyException;
use AltDTO\Transformation\Metadata\ClassMetadata;
use AltDTO\Transformation\Metadata\PropertyMetadata;
use AltDTO\Transformation\Metadata\TypeMetadata;
use AltDTO\Transformation\Reflection\MetadataFactory;
use ReflectionClass;

/**
 * Hydrates DTO objects from normalized array payloads.
 *
 * Property-level conversion is a single, direct dispatch (no parser plugin
 * chain): array/collection properties are coerced structurally, and every
 * other property tries each of its declared named types in order via
 * coerceNamedType() until one actually produces a value of that type -
 * falling back to a plain array cast when nothing matches. A per-property
 * #[ParseUsing(...)] attribute always takes precedence.
 */
final class Hydrator
{
    /**
     * @var array<class-string,PropertyParserInterface>
     */
    private array $customParsers = [];

    public function __construct(
        private readonly MetadataFactory $metadataFactory,
        private readonly DTOConfig $config,
    ) {
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param array<string,mixed> $data
     * @return T
     */
    public function hydrate(string $className, array $data): object
    {
        $metadata = $this->metadataFactory->get($className);
        $instance = $this->instantiate($className);

        $this->validateUnknownProperties($metadata, $data);

        foreach ($metadata->properties as $property) {
            $name = $property->name;
            if (!array_key_exists($name, $data)) {
                if ($property->required && $this->config->throwOnMissingProperties) {
                    throw MissingPropertyException::forProperty($className, $name);
                }
                continue;
            }

            $raw = $data[$name];
            $value = $this->parsePropertyValue($className, $property, $raw);

            if (!$property->isPublic) {
                throw new MetadataException(sprintf('Non-public property "%s::%s" is not supported.', $className, $name));
            }

            try {
                $instance->{$name} = $value;
            } catch (\TypeError $error) {
                throw InvalidPropertyTypeException::forProperty($className, $name, (string)$property->type, $value);
            }
        }

        return $instance;
    }

    public function parsePropertyValue(string $className, PropertyMetadata $property, mixed $value): mixed
    {
        if ($value === null) {
            if ($property->type->allowsNull || $this->config->allowNullCasting) {
                return null;
            }

            throw InvalidPropertyTypeException::forProperty($className, $property->name, (string)$property->type, $value);
        }

        if ($property->customParser !== null) {
            return $this->customParser($property->customParser)->parse($value, $property, $this);
        }

        $type = $property->type;

        if ($type->isCollection || in_array(Collection::class, $type->namedTypes, true)) {
            return $this->toCollection($value, $type);
        }

        if ($type->isList || in_array('array', $type->namedTypes, true) || in_array('iterable', $type->namedTypes, true)) {
            return $this->toArrayValue($value, $type);
        }

        // Try each declared named type in order; the first one that actually
        // produces a value of that type wins.
        foreach ($type->namedTypes as $typeName) {
            $coerced = $this->coerceNamedType($typeName, $value);
            if ($this->valueMatchesType($typeName, $coerced)) {
                return $coerced;
            }
        }

        return $value;
    }

    public function castScalar(string $typeName, mixed $value): mixed
    {
        if (!$this->config->allowTypeCasting) {
            return $value;
        }

        return match ($typeName) {
            'int' => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'string' => is_scalar($value) || $value instanceof \Stringable ? (string) $value : $value,
            'bool' => is_scalar($value) ? filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $value : $value,
            default => $value,
        };
    }

    public function coerceNamedType(string $typeName, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($typeName === 'mixed') {
            return $value;
        }

        if (in_array($typeName, ['int', 'float', 'string', 'bool'], true)) {
            return $this->castScalar($typeName, $value);
        }

        if ($typeName === 'array') {
            return is_array($value) ? $value : (array) $value;
        }

        if ($typeName === 'iterable') {
            return is_iterable($value) ? $value : [$value];
        }

        if (enum_exists($typeName)) {
            if ($value instanceof $typeName) {
                return $value;
            }

            if (is_subclass_of($typeName, \BackedEnum::class) && (is_string($value) || is_int($value))) {
                return $typeName::from($value);
            }

            if (is_string($value)) {
                /** @var class-string<\UnitEnum> $typeName */
                foreach ($typeName::cases() as $case) {
                    if ($case->name === $value) {
                        return $case;
                    }
                }
            }
        }

        if (is_a($typeName, \DateTimeImmutable::class, true)) {
            return $value instanceof \DateTimeImmutable ? $value : new \DateTimeImmutable((string) $value, $this->timezone());
        }

        if (is_a($typeName, \DateTime::class, true)) {
            return $value instanceof \DateTime ? $value : new \DateTime((string) $value, $this->timezone());
        }

        if ($typeName === \DateInterval::class) {
            return $value instanceof \DateInterval ? $value : new \DateInterval((string) $value);
        }

        if ($typeName === \DateTimeZone::class) {
            return $value instanceof \DateTimeZone ? $value : new \DateTimeZone((string) $value);
        }

        if (is_a($typeName, \AltDTO\DTO::class, true)) {
            if ($value instanceof $typeName) {
                return $value;
            }
            if (is_object($value)) {
                $value = (array) $value;
            }
            if (is_array($value)) {
                /** @var object $dto */
                $dto = $this->hydrate($typeName, $value);
                return $dto;
            }
        }

        if (class_exists($typeName)) {
            if ($value instanceof $typeName) {
                return $value;
            }

            if (is_string($value) && method_exists($typeName, 'fromString')) {
                return $typeName::fromString($value);
            }

            if ((is_string($value) || is_int($value)) && method_exists($typeName, 'from')) {
                return $typeName::from($value);
            }

            $ref = new ReflectionClass($typeName);
            $ctor = $ref->getConstructor();
            if ($ctor !== null && $ctor->getNumberOfRequiredParameters() === 1) {
                return $ref->newInstance($value);
            }
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function validateUnknownProperties(ClassMetadata $metadata, array $data): void
    {
        if (!$this->config->strictUnknownFields) {
            return;
        }

        foreach ($data as $key => $_) {
            if (!is_string($key)) {
                continue;
            }
            if (!isset($metadata->properties[$key])) {
                throw UnknownPropertyException::forProperty($metadata->className, $key);
            }
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    private function instantiate(string $className): object
    {
        $reflection = new ReflectionClass($className);

        return $reflection->newInstanceWithoutConstructor();
    }

    private function timezone(): ?\DateTimeZone
    {
        if ($this->config->timezone === null || $this->config->timezone === '') {
            return null;
        }

        return new \DateTimeZone($this->config->timezone);
    }

    /**
     * @param class-string<PropertyParserInterface> $class
     */
    private function customParser(string $class): PropertyParserInterface
    {
        return $this->customParsers[$class] ??= new $class();
    }

    private function toArrayValue(mixed $value, TypeMetadata $type): mixed
    {
        if (!is_array($value) && !$value instanceof \Traversable) {
            return $value;
        }

        $array = is_array($value) ? $value : iterator_to_array($value);
        $elementType = $type->elementType;

        if ($elementType !== null && $elementType !== '' && $elementType !== 'mixed') {
            $array = array_map(fn($item) => $this->coerceNamedType($elementType, $item), $array);
        }

        return $type->isList ? array_values($array) : $array;
    }

    private function toCollection(mixed $value, TypeMetadata $type): Collection
    {
        if ($value instanceof Collection) {
            return $value;
        }

        if (!is_array($value) && !$value instanceof \Traversable) {
            return new Collection([]);
        }

        $array = is_array($value) ? $value : iterator_to_array($value);
        $elementType = $type->elementType;

        if ($elementType !== null && $elementType !== '' && $elementType !== 'mixed') {
            foreach ($array as $key => $item) {
                $array[$key] = $this->coerceNamedType($elementType, $item);
            }
        }

        return new Collection($array, $elementType);
    }

    private function valueMatchesType(string $typeName, mixed $value): bool
    {
        return match ($typeName) {
            'mixed' => true,
            'int' => is_int($value),
            'float' => is_float($value),
            'string' => is_string($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            'iterable' => is_iterable($value),
            default => is_object($value) && is_a($value, $typeName),
        };
    }
}
