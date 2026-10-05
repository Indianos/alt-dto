<?php

declare(strict_types=1);

namespace AltDTO\Contracts;

/**
 * Input parser that transforms external payload into array data.
 */
interface ParserInterface
{
    /**
     * @template T of array<string|int,T|mixed>
     * @return array<string,T>
     */
    public function parse(mixed $input): array;

    /**
     * Parses the same input as a list of rows, one per hydrated object.
     * Return null if this payload does not represent multiple rows.
     *
     * @return list<array<string,mixed>>|null
     */
    public function parseMany(mixed $input): ?array;
}
