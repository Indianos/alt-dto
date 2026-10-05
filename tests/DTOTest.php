<?php

declare(strict_types=1);

namespace AltDTO\Tests;

use AltDTO\Cache\MemoryMetadataCache;
use AltDTO\Configuration\DTOConfig;
use AltDTO\DTO;
use AltDTO\Exceptions\MetadataException;
use AltDTO\Exceptions\MissingPropertyException;
use AltDTO\Exceptions\ParserException;
use AltDTO\Exceptions\UnknownPropertyException;
use AltDTO\Tests\Fixtures\AddressDTO;
use AltDTO\Tests\Fixtures\CustomParserDTO;
use AltDTO\Tests\Fixtures\FlatDTO;
use AltDTO\Tests\Fixtures\NodeDTO;
use AltDTO\Tests\Fixtures\RoleDTO;
use AltDTO\Tests\Fixtures\Status;
use AltDTO\Tests\Fixtures\UserDTO;
use AltDTO\Transformation\Reflection\DocBlockParser;
use AltDTO\Transformation\Reflection\MetadataFactory;
use AltDTO\Transformation\Reflection\TypeResolver;
use PHPUnit\Framework\TestCase;

final class DTOTest extends TestCase
{
    protected function setUp(): void
    {
        DTO::configure(new DTOConfig());
        UserDTO::configure(new DTOConfig());
        CustomParserDTO::configure(new DTOConfig());
        NodeDTO::configure(new DTOConfig());
        FlatDTO::configure(new DTOConfig());
    }

    public function testScalarAndNestedHydration(): void
    {
        $dto = UserDTO::fromArray([
            'id' => '42',
            'name' => 'Alice',
            'createdAt' => '2026-07-14T10:00:00+00:00',
            'address' => ['city' => 'Riga'],
            'roles' => [
                ['name' => 'admin'],
                ['name' => 'editor'],
            ],
            'status' => 'active',
        ]);

        self::assertSame(42, $dto->id);
        self::assertSame('Alice', $dto->name);
        self::assertInstanceOf(\DateTimeImmutable::class, $dto->createdAt);
        self::assertInstanceOf(AddressDTO::class, $dto->address);
        self::assertSame('Riga', $dto->address->city);
        self::assertSame(2, $dto->roles->count());
        self::assertInstanceOf(RoleDTO::class, $dto->roles->first());
        self::assertSame(Status::Active, $dto->status);
    }

    public function testFromJsonAndSerializationRoundTrip(): void
    {
        $json = json_encode([
            'id' => 7,
            'name' => 'Bob',
            'createdAt' => null,
            'address' => ['city' => 'Paris'],
            'roles' => [['name' => 'viewer']],
            'status' => 'disabled',
        ], JSON_THROW_ON_ERROR);

        $dto = UserDTO::fromJson($json);
        $array = $dto->toArray();

        self::assertSame('Bob', $array['name']);
        self::assertSame('Paris', $array['address']['city']);
        self::assertSame('disabled', $array['status']);
        self::assertSame('[{"name":"viewer"}]', json_encode($array['roles'], JSON_THROW_ON_ERROR));
    }

    public function testAdditionalInputEntryPoints(): void
    {
        $arrayDto = UserDTO::fromArray([
            'id' => 11,
            'name' => 'Req',
            'createdAt' => null,
            'address' => ['city' => 'Rome'],
            'roles' => [['name' => 'r1']],
            'status' => 'active',
        ]);
        self::assertSame(11, $arrayDto->id);

        $xml = '<root><id>14</id><name>Xml</name><status>active</status></root>';
        $xmlDto = FlatDTO::fromXml($xml);
        self::assertSame('Xml', $xmlDto->name);

        $csv = "id,name,status\n15,Csv,active";
        $csvDto = FlatDTO::fromCsv($csv);
        self::assertSame(15, $csvDto->id);
    }

    public function testManyFromArrayHydratesCollection(): void
    {
        $users = UserDTO::manyFromArray([
            ['id' => 1, 'name' => 'A', 'createdAt' => null, 'address' => ['city' => 'X'], 'roles' => [], 'status' => 'active'],
            ['id' => 2, 'name' => 'B', 'createdAt' => null, 'address' => ['city' => 'Y'], 'roles' => [], 'status' => 'active'],
        ]);

        self::assertSame(2, $users->count());
        self::assertInstanceOf(UserDTO::class, $users->first());
        self::assertSame('B', $users->toList()[1]->name);
    }

    public function testManyFromCsvUsesDedicatedManyParser(): void
    {
        $csv = "id,name,status\n1,First,active\n2,Second,active";

        $dtos = FlatDTO::manyFromCsv($csv);

        self::assertSame(2, $dtos->count());
        self::assertSame('First', $dtos->toList()[0]->name);
        self::assertSame('Second', $dtos->toList()[1]->name);
    }

    public function testManyFromJsonFallsBackToListDetection(): void
    {
        $json = json_encode([
            ['id' => 1, 'name' => 'One', 'status' => 'active'],
            ['id' => 2, 'name' => 'Two', 'status' => 'active'],
        ], JSON_THROW_ON_ERROR);

        $dtos = FlatDTO::manyFromJson($json);

        self::assertSame(2, $dtos->count());
        self::assertSame('Two', $dtos->toList()[1]->name);
    }

    public function testManyFromJsonThrowsWhenPayloadIsNotAListOfRows(): void
    {
        $json = json_encode(['id' => 9, 'name' => 'Solo', 'status' => 'active'], JSON_THROW_ON_ERROR);

        $this->expectException(ParserException::class);
        FlatDTO::manyFromJson($json);
    }

    public function testFromAndManyFromAreAliasesOfArrayVariants(): void
    {
        $dto = UserDTO::from([
            'id' => 1,
            'name' => 'Alias',
            'createdAt' => null,
            'address' => ['city' => 'Oslo'],
            'roles' => [],
            'status' => 'active',
        ]);
        self::assertSame('Alias', $dto->name);

        $dtos = UserDTO::manyFrom([
            ['id' => 1, 'name' => 'A', 'createdAt' => null, 'address' => ['city' => 'X'], 'roles' => [], 'status' => 'active'],
        ]);
        self::assertSame(1, $dtos->count());
    }

    public function testMissingPropertiesThrowsWhenConfigured(): void
    {
        UserDTO::configure(new DTOConfig(throwOnMissingProperties: true));

        $this->expectException(MissingPropertyException::class);
        UserDTO::fromArray(['id' => 1]);
    }

    public function testUnknownPropertiesCanThrow(): void
    {
        UserDTO::configure(new DTOConfig(strictUnknownFields: true));

        $this->expectException(UnknownPropertyException::class);
        UserDTO::fromArray([
            'id' => 1,
            'name' => 'A',
            'createdAt' => null,
            'address' => ['city' => 'X'],
            'roles' => [],
            'status' => 'active',
            'extra' => 'forbidden',
        ]);
    }

    public function testCustomPropertyParserOverridesBuiltInParser(): void
    {
        $dto = CustomParserDTO::fromArray(['id' => '0x10']);

        self::assertSame(16, $dto->id);
    }

    public function testMetadataCachingReturnsSameInstance(): void
    {
        $factory = new MetadataFactory(new MemoryMetadataCache(), new DocBlockParser(), new TypeResolver());

        $first = $factory->get(UserDTO::class);
        $second = $factory->get(UserDTO::class);

        self::assertSame($first, $second);
    }

    public function testDocBlockParserUnderstandsGenericShapes(): void
    {
        $parser = new DocBlockParser();

        $list = $parser->parse('/** @var non-empty-list<UserDTO> */');
        self::assertFalse($list['isList']);
        self::assertNull($list['elementType']);

        $list = $parser->parse('/** @var list<UserDTO> */');
        self::assertTrue($list['isList']);
        self::assertSame('UserDTO', $list['elementType']);

        $map = $parser->parse('/** @var array<int,UserDTO>|null */');
        self::assertSame('UserDTO', $map['elementType']);

        $collection = $parser->parse('/** @var Collection<UserDTO> */');
        self::assertTrue($collection['isCollection']);
        self::assertSame('UserDTO', $collection['elementType']);
    }

    public function testCircularReferenceSerializationDoesNotLoopForever(): void
    {
        $first = NodeDTO::fromArray(['name' => 'a']);
        $second = NodeDTO::fromArray(['name' => 'b']);

        $first->next = $second;
        $second->next = $first;

        $array = $first->toArray();

        self::assertSame('a', $array['name']);
        self::assertSame('b', $array['next']['name']);

        // $first is reachable twice (root, and again via $second->next), so it
        // gets a stable "$id" the first time, and the repeat encounter is a
        // "$ref" pointing back at that same id instead of an infinite nest.
        self::assertArrayHasKey('$id', $array);
        self::assertArrayHasKey('$ref', $array['next']['next']);
        self::assertSame($array['$id'], $array['next']['next']['$ref']);

        // $second is only reachable once, so it's serialized as a plain node
        // without any "$id" noise.
        self::assertArrayNotHasKey('$id', $array['next']);
    }

    public function testCopyWithAndEquals(): void
    {
        $dto = UserDTO::fromArray([
            'id' => 1,
            'name' => 'Jane',
            'createdAt' => null,
            'address' => ['city' => 'Berlin'],
            'roles' => [['name' => 'qa']],
            'status' => 'active',
        ]);

        $copy = $dto->copy();
        $updated = $dto->with(['name' => 'Janet']);

        self::assertTrue($dto->equals($copy));
        self::assertFalse($dto->equals($updated));
        self::assertSame('Janet', $updated->name);
    }

    public function testCallableCacheIsUsedForMetadata(): void
    {
        $store = [];
        $cache = function (string $key, callable $compute) use (&$store): mixed {
            if (array_key_exists($key, $store)) {
                return $store[$key];
            }

            return $store[$key] = $compute();
        };

        FlatDTO::configure(new DTOConfig(cache: $cache));

        $dto = FlatDTO::fromArray(['id' => 1, 'name' => 'Duck']);

        self::assertSame('Duck', $dto->name);
        self::assertNotSame([], $store);

        FlatDTO::configure(new DTOConfig());
    }

    public function testInvalidCacheValueThrows(): void
    {
        $invalidCache = new class {};

        FlatDTO::configure(new DTOConfig(cache: $invalidCache));

        $this->expectException(MetadataException::class);
        FlatDTO::fromArray(['id' => 1, 'name' => 'Bad']);
    }
}
