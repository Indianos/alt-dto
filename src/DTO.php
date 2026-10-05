<?php

declare(strict_types=1);

namespace AltDTO;

use AltDTO\Cache\CacheManager;
use AltDTO\Configuration\DTOConfig;
use AltDTO\Exceptions\ParserException;
use AltDTO\Ingestion\InputParsers\ArrayParser;
use AltDTO\Ingestion\InputParsers\CsvParser;
use AltDTO\Ingestion\InputParsers\JsonParser;
use AltDTO\Ingestion\InputParsers\ObjectParser;
use AltDTO\Ingestion\InputParsers\XmlParser;
use AltDTO\Ingestion\ParserManager;
use AltDTO\Serialization\Serializer;
use AltDTO\Transformation\Hydration\Hydrator;
use AltDTO\Transformation\Reflection\DocBlockParser;
use AltDTO\Transformation\Reflection\MetadataFactory;
use AltDTO\Transformation\Reflection\TypeResolver;

/**
 * Abstract DTO with hydration and serialization utilities.
 *
 * Dynamically dispatched entry points, backed by the built-in input parser
 * registry (see inputParser()). Register/replace parsers via
 * setInputParser() to add more from*()/manyFrom*() methods.
 *
 * @method static static fromArray(array<string, mixed> $data)
 * @method static static fromJson(string $json)
 * @method static static fromXml(string $xml)
 * @method static static fromCsv(string $csv)
 * @method static Collection<static> manyFromArray(iterable<array<string, mixed>> $rows)
 * @method static Collection<static> manyFromJson(string $json)
 * @method static Collection<static> manyFromXml(string $xml)
 * @method static Collection<static> manyFromCsv(string $csv)
 */
abstract class DTO implements \JsonSerializable
{
    /**
     * Class-specific configuration, set via configure().
     *
     * @var array<class-string,DTOConfig>
     */
    private static array $config = [];

    private static ?ParserManager $inputParser = null;

    /**
     * @param DTOConfig $config
     */
    public static function configure(DTOConfig $config): void
    {
        self::$config[static::class] = $config;
    }

    public static function setInputParser(string $name, mixed $parser): ParserManager
    {
        return self::inputParser()->set($name, $parser);
    }

    public static function unsetInputParser(string $name): ParserManager
    {
        return self::inputParser()->unset($name);
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function from(array $data): static
    {
        /** @var static $dto */
        $dto = static::hydrator()->hydrate(static::class, $data);

        return $dto;
    }

    /**
     * @param iterable<array<string,mixed>> $rows
     * @return Collection<static>
     */
    public static function manyFrom(iterable $rows): Collection
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = static::from($row);
        }

        return new Collection($items, static::class);
    }

    /**
     * Dynamic static parser dispatch: fromJson/fromYaml/fromXxx (single object)
     * and manyFromJson/manyFromYaml/manyFromXxx (Collection of objects).
     */
    public static function __callStatic(string $name, array $arguments): static|Collection
    {
        if (str_starts_with($name, 'manyFrom')) {
            $parserName = strtolower(substr($name, 8));
            if ($parserName === '') {
                throw new ParserException('Invalid parser method name.');
            }

            return static::manyFromParser($parserName, ...$arguments);
        }

        if (str_starts_with($name, 'from')) {
            if ($arguments === []) {
                throw new ParserException(sprintf('Missing payload for parser method "%s".', $name));
            }

            $parserName = strtolower(substr($name, 4));
            if ($parserName === '') {
                throw new ParserException('Invalid parser method name.');
            }

            return static::fromParser($parserName, ...$arguments);
        }
        throw new ParserException(sprintf('Unsupported static method "%s".', $name));

    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $serializer = new Serializer(static::config());
        $pins = [];

        /** @var array<string,mixed> $normalized */
        $normalized = $serializer->normalize($this, $pins);

        return $normalized;
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | $flags);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function copy(): static
    {
        return static::from($this->toArray());
    }

    /**
     * @param array<string,mixed> $changes
     */
    public function with(array $changes): static
    {
        return static::from(array_replace($this->toArray(), $changes));
    }

    public function equals(object $other): bool
    {
        if (!$other instanceof static) {
            return false;
        }

        return $this->toArray() === $other->toArray();
    }

    /**
     * Takes general configuration and overrides it with class-specific configuration if available.
     */
    protected static function config(): DTOConfig
    {
        return static::$config[static::class] ?? static::$config[self::class] ?? new DTOConfig();
    }

    /**
     * Builds a fresh Hydrator on every call. This is intentionally not cached:
     * constructing a Hydrator/MetadataFactory is cheap, and the actually
     * expensive work (reflection-based class metadata parsing) is already
     * memoized separately by CacheManager's shared metadata cache.
     */
    private static function hydrator(): Hydrator
    {
        $config = static::config();
        $metadataFactory = new MetadataFactory(
            cache         : CacheManager::resolve($config),
            docBlockParser: new DocBlockParser(),
            typeResolver  : new TypeResolver()
        );

        return new Hydrator(metadataFactory: $metadataFactory, config: $config);
    }

    private static function fromParser(string $parserName, mixed ...$arguments): static
    {
        $data = self::inputParser()->invokeByName($parserName, ...$arguments);
        if (!is_array($data)) {
            throw new ParserException(sprintf('Input parser "%s" must return an array payload.', $parserName));
        }

        return static::from($data);
    }

    /**
     * @return Collection<static>
     */
    private static function manyFromParser(string $parserName, mixed ...$arguments): Collection
    {
        $rows = self::inputParser()->invokeManyByName($parserName, ...$arguments);

        return static::manyFrom($rows);
    }

    private static function inputParser(): ParserManager
    {
        if (self::$inputParser instanceof ParserManager) {
            return self::$inputParser;
        }

        $manager = new ParserManager();
        $manager->set('array', ArrayParser::class);
        $manager->set('object', ObjectParser::class);
        $manager->set('csv', CsvParser::class);
        extension_loaded('json') && $manager->set('json', JsonParser::class);
        extension_loaded('xml') && $manager->set('xml', XmlParser::class);

        return self::$inputParser = $manager;
    }
}
