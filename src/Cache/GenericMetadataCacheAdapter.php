<?php

declare(strict_types=1);

namespace AltDTO\Cache;

use AltDTO\Contracts\MetadataCacheInterface;
use Closure;

/**
 * MetadataCacheInterface implementation that delegates to a single
 * getOrCompute-shaped callable, resolved by CacheManager.
 */
final readonly class GenericMetadataCacheAdapter implements MetadataCacheInterface
{
    /**
     * @param Closure(string, callable): mixed $getOrCompute
     */
    public function __construct(private Closure $getOrCompute)
    {
    }

    public function getOrCompute(string $key, callable $compute): mixed
    {
        return ($this->getOrCompute)($key, $compute);
    }
}

