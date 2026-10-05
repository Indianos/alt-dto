<?php

declare(strict_types=1);

namespace AltDTO;

use AltDTO\Contracts\CollectionInterface;
use ArrayIterator;
use Traversable;

/**
 * @template T
 * @implements CollectionInterface<T>
 */
class Collection implements CollectionInterface
{
    /**
     * @param array<int|string,T> $items
     * @param class-string|null $elementType
     */
    public function __construct(
        private array $items = [],
        private readonly ?string $elementType = null
    ) {
    }

    /**
     * @param array<int|string,T> $items
     * @param class-string|null $elementType
     * @return static<T>
     */
    public static function from(array $items, ?string $elementType = null): static
    {
        return new static($items, $elementType);
    }

    /**
     * @return Traversable<int|string,T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return array<int|string,T>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @return list<T>
     */
    public function toList(): array
    {
        return array_values($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function first(mixed $default = null): mixed
    {
        foreach ($this->items as $item) {
            return $item;
        }

        return $default;
    }

    public function last(mixed $default = null): mixed
    {
        if ($this->items === []) {
            return $default;
        }

        $items = array_values($this->items);

        return $items[count($items) - 1] ?? $default;
    }

    public function contains(mixed $needle): bool
    {
        foreach ($this->items as $item) {
            if ($item === $needle) {
                return true;
            }
        }

        return false;
    }

    public function map(callable $callback): static
    {
        $mapped = [];
        foreach ($this->items as $key => $item) {
            $mapped[$key] = $callback($item, $key);
        }

        return new static($mapped, $this->elementType);
    }

    public function filter(callable $callback): static
    {
        $filtered = [];
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === true) {
                $filtered[$key] = $item;
            }
        }

        return new static($filtered, $this->elementType);
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $carry = $initial;
        foreach ($this->items as $key => $item) {
            $carry = $callback($carry, $item, $key);
        }

        return $carry;
    }

    public function values(): static
    {
        return new static(array_values($this->items), $this->elementType);
    }

    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    public function merge(iterable $items): static
    {
        $result = $this->items;
        foreach ($items as $key => $item) {
            $result[$key] = $item;
        }

        return new static($result, $this->elementType);
    }

    public function push(mixed $item): static
    {
        $result = $this->items;
        $result[] = $item;

        return new static($result, $this->elementType);
    }

    public function prepend(mixed $item): static
    {
        $result = $this->items;
        array_unshift($result, $item);

        return new static($result, $this->elementType);
    }

    public function append(iterable $items): static
    {
        $result = $this->items;
        foreach ($items as $item) {
            $result[] = $item;
        }

        return new static($result, $this->elementType);
    }

    public function sort(?callable $comparator = null): static
    {
        $result = $this->items;
        if ($comparator === null) {
            asort($result);
        } else {
            uasort($result, $comparator);
        }

        return new static($result, $this->elementType);
    }

    public function sortBy(callable $selector, int $direction = SORT_ASC): static
    {
        $result = $this->items;
        uasort(
            $result,
            static function (mixed $a, mixed $b) use ($selector, $direction): int {
                $left = $selector($a);
                $right = $selector($b);
                $cmp = $left <=> $right;

                return $direction === SORT_DESC ? -$cmp : $cmp;
            }
        );

        return new static($result, $this->elementType);
    }

    public function groupBy(callable $selector): static
    {
        $groups = [];
        foreach ($this->items as $item) {
            $key = (string) $selector($item);
            $groups[$key] ??= [];
            $groups[$key][] = $item;
        }

        $wrapped = [];
        foreach ($groups as $key => $groupItems) {
            $wrapped[$key] = new static($groupItems, $this->elementType);
        }

        return new static($wrapped);
    }

    public function keyBy(callable $selector): static
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[(string) $selector($item)] = $item;
        }

        return new static($result, $this->elementType);
    }

    public function pluck(string $field): static
    {
        $result = [];
        foreach ($this->items as $key => $item) {
            if (is_array($item)) {
                $result[$key] = $item[$field] ?? null;
                continue;
            }

            if (is_object($item) && isset($item->{$field})) {
                $result[$key] = $item->{$field};
                continue;
            }

            $result[$key] = null;
        }

        return new static($result);
    }

    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            $callback($item, $key);
        }

        return $this;
    }

    /**
     * @return array<int|string,mixed>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->items as $key => $item) {
            $result[$key] = $this->normalize($item);
        }

        return $result;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
            return;
        }

        $this->items[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    public function elementType(): ?string
    {
        return $this->elementType;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->toArray();
        }

        if ($value instanceof \JsonSerializable) {
            return $value->jsonSerialize();
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof \UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }

        return $value;
    }
}
