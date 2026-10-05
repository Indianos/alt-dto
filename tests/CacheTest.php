<?php

declare(strict_types=1);

namespace AltDTO\Tests;

use AltDTO\Cache\CacheManager;
use AltDTO\Cache\GenericMetadataCacheAdapter;
use AltDTO\Cache\MemoryMetadataCache;
use AltDTO\Configuration\DTOConfig;
use AltDTO\Exceptions\MetadataException;
use AltDTO\Tests\Fixtures\FlatDTO;
use AltDTO\Tests\Support\InMemoryPsr16Cache;
use AltDTO\Transformation\Reflection\DocBlockParser;
use AltDTO\Transformation\Reflection\MetadataFactory;
use AltDTO\Transformation\Reflection\TypeResolver;
use DateInterval;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CacheTest extends TestCase
{
    // --- MemoryMetadataCache -------------------------------------------------

    public function testMemoryCacheGetOrComputeCachesResult(): void
    {
        $cache = new MemoryMetadataCache();
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return new stdClass();
        };

        $first = $cache->getOrCompute('key', $compute);
        $second = $cache->getOrCompute('key', $compute);

        self::assertSame($first, $second);
        self::assertSame(1, $calls);
    }

    public function testMemoryCacheDistinguishesCachedNullFromMiss(): void
    {
        $cache = new MemoryMetadataCache();
        $calls = 0;

        $first = $cache->getOrCompute('nullable', function () use (&$calls) {
            $calls++;

            return null;
        });
        $second = $cache->getOrCompute('nullable', function () use (&$calls) {
            $calls++;

            return 'unused';
        });

        self::assertNull($first);
        self::assertNull($second);
        self::assertSame(1, $calls, 'compute() must not run again for a cached null');
    }

    // --- GenericMetadataCacheAdapter ------------------------------------------

    public function testGenericAdapterDelegatesToCallable(): void
    {
        $store = [];
        $adapter = new GenericMetadataCacheAdapter(
            function (string $key, callable $compute) use (&$store): mixed {
                if (array_key_exists($key, $store)) {
                    return $store[$key];
                }

                return $store[$key] = $compute();
            }
        );

        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return new stdClass();
        };

        $first = $adapter->getOrCompute('key', $compute);
        $second = $adapter->getOrCompute('key', $compute);

        self::assertSame($first, $second);
        self::assertSame(1, $calls);
    }

    public function testGenericAdapterGetOrComputeDistinguishesCachedNull(): void
    {
        $store = [];
        $adapter = new GenericMetadataCacheAdapter(
            function (string $key, callable $compute) use (&$store): mixed {
                if (array_key_exists($key, $store)) {
                    return $store[$key];
                }

                return $store[$key] = $compute();
            }
        );

        $calls = 0;
        $first = $adapter->getOrCompute('nullable', function () use (&$calls) {
            $calls++;

            return null;
        });
        $second = $adapter->getOrCompute('nullable', function () use (&$calls) {
            $calls++;

            return 'unused';
        });

        self::assertNull($first);
        self::assertNull($second);
        self::assertSame(1, $calls);
    }

    // --- CacheManager ----------------------------------------------------------
    // CacheManager detects MetadataCacheInterface instances/class-strings,
    // PSR-16 caches, and plain callables, wrapping the latter two into a
    // GenericMetadataCacheAdapter.

    public function testCacheManagerResolvesPlainCallable(): void
    {
        $store = [];
        $cache = function (string $key, callable $compute) use (&$store): mixed {
            if (array_key_exists($key, $store)) {
                return $store[$key];
            }

            return $store[$key] = $compute();
        };

        $resolved = CacheManager::resolve(new DTOConfig(cache: $cache));

        self::assertInstanceOf(GenericMetadataCacheAdapter::class, $resolved);

        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return 'computed';
        };

        self::assertSame('computed', $resolved->getOrCompute('key', $compute));
        self::assertSame('computed', $resolved->getOrCompute('key', $compute));
        self::assertSame(1, $calls);
    }

    public function testCacheManagerResolvesArrayCallable(): void
    {
        $service = new class {
            /** @var array<string,mixed> */
            public array $store = [];

            public function handle(string $key, callable $compute): mixed
            {
                if (array_key_exists($key, $this->store)) {
                    return $this->store[$key];
                }

                return $this->store[$key] = $compute();
            }
        };

        $resolved = CacheManager::resolve(new DTOConfig(cache: [$service, 'handle']));

        self::assertInstanceOf(GenericMetadataCacheAdapter::class, $resolved);

        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return 'computed';
        };

        self::assertSame('computed', $resolved->getOrCompute('key', $compute));
        self::assertSame('computed', $resolved->getOrCompute('key', $compute));
        self::assertSame(1, $calls);
    }

    public function testCacheManagerThrowsForNonCallableNonCacheValue(): void
    {
        $this->expectException(MetadataException::class);
        CacheManager::resolve(new DTOConfig(cache: new stdClass()));
    }

    public function testCacheManagerThrowsWhenClassStringDoesNotImplementInterface(): void
    {
        $this->expectException(MetadataException::class);
        CacheManager::resolve(new DTOConfig(cache: stdClass::class));
    }

    public function testCacheManagerThrowsForStringThatIsNeitherClassNorCallable(): void
    {
        $this->expectException(MetadataException::class);
        CacheManager::resolve(new DTOConfig(cache: 'this-is-not-a-class-or-function'));
    }

    public function testCacheManagerResolvesPsr16CacheInstance(): void
    {
        $fixture = new InMemoryPsr16Cache();
        $resolved = CacheManager::resolve(new DTOConfig(cache: $fixture, cacheTtl: 90));

        self::assertInstanceOf(GenericMetadataCacheAdapter::class, $resolved);

        $resolved->getOrCompute('k', fn() => 'v');
        self::assertSame(90, $fixture->lastTtl);
    }

    public function testCacheManagerPsr16AdapterDistinguishesCachedNullFromMiss(): void
    {
        $fixture = new InMemoryPsr16Cache();
        $adapter = CacheManager::resolve(new DTOConfig(cache: $fixture));

        $calls = 0;
        $first = $adapter->getOrCompute('nullable', function () use (&$calls) {
            $calls++;

            return null;
        });
        $second = $adapter->getOrCompute('nullable', function () use (&$calls) {
            $calls++;

            return 'unused';
        });

        self::assertNull($first);
        self::assertNull($second);
        self::assertSame(1, $calls, 'compute() must not run again on a cache hit');
    }

    public function testCacheManagerPsr16AdapterGetOrComputeCachesResult(): void
    {
        $fixture = new InMemoryPsr16Cache();
        $adapter = CacheManager::resolve(new DTOConfig(cache: $fixture));

        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return 'computed';
        };

        $first = $adapter->getOrCompute('key', $compute);
        $second = $adapter->getOrCompute('key', $compute);

        self::assertSame('computed', $first);
        self::assertSame('computed', $second);
        self::assertSame(1, $calls, 'compute() must not run again on a cache hit');
    }

    public function testCacheManagerForwardsTtlToPsr16UnderlyingStore(): void
    {
        $fixture = new InMemoryPsr16Cache();
        $adapter = CacheManager::resolve(new DTOConfig(cache: $fixture, cacheTtl: 120));

        $adapter->getOrCompute('a', fn() => 'b');
        self::assertSame(120, $fixture->lastTtl);
    }

    public function testCacheManagerForwardsDateIntervalTtlToPsr16UnderlyingStore(): void
    {
        $fixture = new InMemoryPsr16Cache();
        $ttl = new DateInterval('PT1H');
        $adapter = CacheManager::resolve(new DTOConfig(cache: $fixture, cacheTtl: $ttl));

        $adapter->getOrCompute('a', fn() => 'b');
        self::assertSame($ttl, $fixture->lastTtl);
    }

    public function testCacheManagerIgnoresCacheTtlForNonPsr16Caches(): void
    {
        // cacheTtl is ignored by non-PSR-16 caches.
        $store = [];
        $cache = function (string $key, callable $compute) use (&$store): mixed {
            if (array_key_exists($key, $store)) {
                return $store[$key];
            }

            return $store[$key] = $compute();
        };

        $resolved = CacheManager::resolve(new DTOConfig(cache: $cache, cacheTtl: 999));

        self::assertSame('v', $resolved->getOrCompute('k', fn() => 'v'));
    }

    public function testCacheManagerReturnsSameSharedMemoryCacheAcrossCalls(): void
    {
        $first = CacheManager::resolve(new DTOConfig());
        $second = CacheManager::resolve(new DTOConfig());

        self::assertSame($first, $second);
    }

    // --- MetadataFactory integration --------------------------------------------

    public function testMetadataFactoryCachesBuiltMetadataAcrossCalls(): void
    {
        $factory = new MetadataFactory(
            cache: new MemoryMetadataCache(),
            docBlockParser: new DocBlockParser(),
            typeResolver: new TypeResolver()
        );

        $first = $factory->get(FlatDTO::class);
        $second = $factory->get(FlatDTO::class);

        self::assertSame($first, $second);
    }
}

