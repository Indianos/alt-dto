<?php

declare(strict_types=1);

namespace AltDTO\Tests\Support;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * Minimal in-memory PSR-16 cache used to exercise CacheManager's PSR-16
 * wrapping. Records the ttl passed to the last set() call and the number of
 * get() calls so tests can assert round-trip behavior.
 */
final class InMemoryPsr16Cache implements CacheInterface
{
    /** @var array<string,mixed> */
    public array $store = [];

    public null|int|DateInterval $lastTtl = null;

    public int $getCalls = 0;

    public function get(string $key, mixed $default = null): mixed
    {
        $this->getCalls++;

        return array_key_exists($key, $this->store) ? $this->store[$key] : $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->store[$key] = $value;
        $this->lastTtl = $ttl;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->store = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string)$key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }
}


