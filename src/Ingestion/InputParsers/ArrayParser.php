<?php

declare(strict_types=1);

namespace AltDTO\Ingestion\InputParsers;

use AltDTO\Contracts\ParserInterface;
use AltDTO\Exceptions\ParserException;

class ArrayParser implements ParserInterface
{
    public function parse(mixed $input): array
    {
        if (!is_array($input)) {
            throw new ParserException('Array parser expects an array payload.');
        }

        return $input;
    }

    public function parseMany(mixed $input): ?array
    {
        if (!is_iterable($input)) {
            return null;
        }

        $rows = [];
        foreach ($input as $row) {
            if (!is_array($row)) {
                return null;
            }

            $rows[] = $row;
        }

        return $rows;
    }
}

