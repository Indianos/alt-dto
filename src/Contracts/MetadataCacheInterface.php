<?php

declare(strict_types=1);

namespace AltDTO\Contracts;

/**
 * Cache contract for class metadata. A getOrCompute-shaped callable, or a
 * Psr\SimpleCache\CacheInterface (PSR-16) instance, may be used instead via
 * DTOConfig::$cache - CacheManager detects and wraps both. TTL is PSR-16-only
 * (DTOConfig::$cacheTtl); this contract has no ttl parameter.
 */
interface MetadataCacheInterface
{
    /**
     * Returns the cached value for $key, or computes, caches, and returns
     * $compute()'s result on a miss. A cached null must not be treated as a miss.
     *
     * @template V
     * @param callable():V $compute
     * @return V
     */
    public function getOrCompute(string $key, callable $compute): mixed;
}
