<?php

declare(strict_types=1);

namespace AltDTO\Cache;

use AltDTO\Contracts\MetadataCacheInterface;

/**
 * In-memory metadata cache for process-local workloads.
 */
final class MemoryMetadataCache implements MetadataCacheInterface
{
    /**
     * @var array<string,mixed>
     */
    private array $values = [];

    public function getOrCompute(string $key, callable $compute): mixed
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        return $this->values[$key] = $compute();
    }
}
