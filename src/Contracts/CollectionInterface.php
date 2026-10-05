<?php

declare(strict_types=1);

namespace AltDTO\Contracts;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @template T
 * @extends IteratorAggregate<int|string,T>
 * @extends ArrayAccess<int|string,T>
 */
interface CollectionInterface extends IteratorAggregate, Countable, ArrayAccess, JsonSerializable
{
    /**
     * @return Traversable<int|string,T>
     */
    public function getIterator(): Traversable;

    /**
     * @return array<int|string,T>
     */
    public function all(): array;

    /**
     * @return list<T>
     */
    public function toList(): array;

    public function first(mixed $default = null): mixed;

    public function last(mixed $default = null): mixed;

    public function contains(mixed $needle): bool;

    /**
     * @param callable(T, int|string):mixed $callback
     * @return static
     */
    public function map(callable $callback): static;

    /**
     * @param callable(T, int|string):bool $callback
     * @return static
     */
    public function filter(callable $callback): static;

    /**
     * @param callable(mixed,T,int|string):mixed $callback
     */
    public function reduce(callable $callback, mixed $initial = null): mixed;
}
