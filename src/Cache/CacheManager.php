<?php

declare(strict_types=1);

namespace AltDTO\Cache;

use AltDTO\Configuration\DTOConfig;
use AltDTO\Contracts\MetadataCacheInterface;
use AltDTO\Exceptions\MetadataException;
use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;

/**
 * Resolves DTOConfig::$cache into a MetadataCacheInterface. Accepts a
 * MetadataCacheInterface instance/class-string, a PSR-16 CacheInterface
 * instance, a getOrCompute-shaped callable, or null for the default
 * in-memory cache. DTOConfig::$cacheTtl only applies to PSR-16 caches.
 */
final class CacheManager
{
    private static ?MetadataCacheInterface $default = null;

    public static function resolve(DTOConfig $config): MetadataCacheInterface
    {
        $cache = $config->cache;

        if ($cache instanceof MetadataCacheInterface) {
            return $cache;
        }

        if (is_string($cache) && $cache !== '' && class_exists($cache)) {
            if (!is_subclass_of($cache, MetadataCacheInterface::class)) {
                throw new MetadataException(sprintf(
                    'Configured cache class "%s" must implement %s.',
                    $cache,
                    MetadataCacheInterface::class
                ));
            }

            /** @var class-string<MetadataCacheInterface> $cache */
            return new $cache();
        }

        if ($cache instanceof Psr16CacheInterface) {
            return new GenericMetadataCacheAdapter(
                static function (string $key, callable $compute) use ($cache, $config): mixed {
                    if ($cache->has($key)) {
                        return $cache->get($key);
                    }

                    $value = $compute();
                    $cache->set($key, $value, $config->cacheTtl);

                    return $value;
                }
            );
        }

        if (is_callable($cache)) {
            return new GenericMetadataCacheAdapter($cache(...));
        }

        if ($cache) {
            throw new MetadataException(sprintf(
                'Configured cache must be a %s instance/class-string, a %s (PSR-16) instance, '
                . 'or a callable(string $key, callable $compute): mixed; got %s.',
                MetadataCacheInterface::class,
                Psr16CacheInterface::class,
                get_debug_type($cache)
            ));
        }

        return self::$default ??= new MemoryMetadataCache();
    }
}
