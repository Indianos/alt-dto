# AltDTO

AltDTO is a framework-agnostic library for hydrating and serializing strongly typed DTOs in PHP.

It focuses on:

- predictable hydration
- reusable reflection metadata
- always-on, memoized metadata caching (pluggable via `AltDTO\Contracts\MetadataCacheInterface`)
- input parsers for `fromXxx(...)` and `manyFromXxx(...)`
- direct, type-driven property coercion during hydration

## Installation

```bash
composer require Indianos/alt-dto
```

## Quick Start

```php
<?php

use AltDTO\Collection;
use AltDTO\Configuration\DTOConfig;
use AltDTO\DTO;

final class AddressDTO extends DTO
{
    public string $city;
}

final class RoleDTO extends DTO
{
    public string $name;
}

final class UserDTO extends DTO
{
    public int $id;
    public string $name;
    public ?DateTimeImmutable $createdAt = null;

    /** @var AddressDTO */
    public AddressDTO $address;

    /** @var list<RoleDTO> */
    public Collection $roles;
}

DTO::configure(new DTOConfig());

$user = UserDTO::from([
    'id' => '42',
    'name' => 'Alice',
    'createdAt' => '2026-07-14T10:00:00+00:00',
    'address' => ['city' => 'Riga'],
    'roles' => [
        ['name' => 'admin'],
        ['name' => 'editor'],
    ],
]);

$array = $user->toArray();
$json = $user->toJson();
```

## Configuration

Configuration is resolved in this order:

1. class-specific config `SpecificDTO::configure(...)`
2. global config from `DTO::configure(...)`
3. defaults `new DTOConfig()`

### Caching

Class metadata (reflected properties, types, docblock info) is always cached, so it's computed once per class. `AltDTO\Contracts\MetadataCacheInterface` is the contract used internally, and it's intentionally minimal – just one method:

```php
interface MetadataCacheInterface
{
    public function getOrCompute(string $key, callable $compute): mixed;
}
```

`getOrCompute()` returns the cached value, or computes, caches, and returns `$compute()`'s result on a miss (a cached `null` counts as a hit, not a miss).

`DTOConfig::$cache` accepts:
- a `MetadataCacheInterface` instance or class-string
- a `Psr\SimpleCache\CacheInterface` (PSR-16) instance
- a plain callable shaped like `getOrCompute(string $key, callable $compute): mixed`
- `null`/unset for the default shared in-memory cache

`DTOConfig::$cacheTtl` sets a default TTL when PSR-16 instance is used.

```php
DTO::configure(new DTOConfig(cache: $psr16Cache, cacheTtl: 3600));

// or with a plain callable:
$store = [];
DTO::configure(new DTOConfig(
    cache: fn (string $key, callable $compute): mixed => $compute(),
));
```


## Input Hydration

Use the `fromXxx(...)` family to hydrate a single DTO instance and `manyFromXxx(...)` to hydrate a `Collection` of DTOs.

- `fromArray(array<string,mixed> $data): static` / `from(...)`
- `manyFromArray(iterable<array<string,mixed>> $rows): Collection<static>` / `manyFrom(...)`
- `fromJson(...)`, `fromXml(...)`, `fromCsv(...)`
- `manyFromJson(...)`, `manyFromXml(...)`, `manyFromCsv(...)`

Built-in input parsers are available for `array`, `json`, `xml`, and `csv`.
You can also register your own parser as either:

- a class-string or instance implementing `AltDTO\Contracts\ParserInterface`
- a PHP callable for single-object hydration

```php
use AltDTO\Contracts\ParserInterface;

final class IniParser implements ParserInterface
{
    public function parse(mixed $input): array
    {
        return [];
    }

    public function parseMany(mixed $input): ?array
    {
        return null;
    }
}

DTO::setInputParser('iniFile', IniParser::class);
DTO::setInputParser('iniFile', new IniParser());
DTO::setInputParser('iniFile', fn ($raw, ...$other) => []);
```

When calling `manyFromXxx(...)`, AltDTO uses `parseMany()` on the registered parser.

- `parseMany()` must return `list<array<string,mixed>>` or `null`
- returning `null` causes a `ParserException`
- plain callables do not support multi-object hydration

## Property Coercion

Properties are coerced directly by the hydrator.

For a single property, you can override hydration with a custom parser:

```php
use AltDTO\Attributes\ParseUsing;
use AltDTO\Contracts\PropertyParserInterface;

final class HexIntParser implements PropertyParserInterface
{
    public function parse(mixed $value, PropertyMetadata $property, Hydrator $hydrator): mixed
    {
        return is_string($value) && str_starts_with($value, '0x') ? hexdec(substr($value, 2)) : $value;
    }
}

final class SomeDTO extends DTO
{
    #[ParseUsing(HexIntParser::class)]
    public int $id;
}
```

## Type Handling

Supported:

- scalar types (`int`, `float`, `string`, `bool`)
- `array`, `iterable`, `mixed`
- nullable and union types
- enums/backed enums
- `DateTime`, `DateTimeImmutable`, `DateInterval`, `DateTimeZone`
- nested DTOs
- `Collection` and array element conversion via docblocks
- one-argument value objects and `fromString(...)`/`from(...)` style classes

## DocBlock Metadata

Complex patterns supported:
- `UserDTO[]`
- `array<UserDTO>`
- `array<int,UserDTO>`
- `list<UserDTO>`
- `Collection<UserDTO>`

Array shapes (`array{...}`) and `non-empty-*` variants are treated as plain arrays.

## Circular Reference Safety

```php
$a = NodeDTO::fromArray(['name' => 'a']);
$b = NodeDTO::fromArray(['name' => 'b']);
$a->next = $b;
$b->next = $a;

$a->toArray();
// [
//     '$id' => 1,
//     'name' => 'a',
//     'next' => ['name' => 'b', 'next' => ['$ref' => 1]],
// ]
```

This is a loop-breaker, not a reference-preserving format: the emitted `$id`/`$ref` pair is only unique per session.

## Testing

```bash
docker run --rm -v "$PWD:/app" -w /app composer composer test
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for a full Docker-based local dev workflow.
