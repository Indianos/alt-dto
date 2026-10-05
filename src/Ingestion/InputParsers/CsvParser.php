<?php

declare(strict_types=1);

namespace AltDTO\Ingestion\InputParsers;

use AltDTO\Contracts\ParserInterface;
use AltDTO\Exceptions\ParserException;

final class CsvParser implements ParserInterface
{
    public function parse(mixed $input): array
    {
        if (!is_string($input)) {
            throw new ParserException('CSV parser expects a string payload.');
        }

        $lines = preg_split('/\R/', trim($input)) ?: [];
        if (count($lines) < 2) {
            throw new ParserException('CSV payload must include a header line and at least one row.');
        }

        $header = str_getcsv(array_shift($lines) ?: '', escape: '\\');
        $row = str_getcsv($lines[0] ?? '', escape: '\\');

        if ($header === [] || count($header) !== count($row)) {
            throw new ParserException('CSV payload has mismatched header and row column counts.');
        }

        /** @var array<string,mixed> $assoc */
        $assoc = array_combine($header, $row) ?: [];

        return $assoc;
    }

    /**
     * @param mixed $input
     * @return array|null
     */
    public function parseMany(mixed $input): ?array
    {
        if (!is_string($input)) {
            throw new ParserException('CSV parser expects a string payload.');
        }

        $lines = preg_split('/\R/', trim($input)) ?: [];
        if (count($lines) < 1) {
            throw new ParserException('CSV payload must include a header line.');
        }

        $header = str_getcsv((string) array_shift($lines), escape: '\\');
        if ($header === []) {
            throw new ParserException('CSV payload is missing a header line.');
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = str_getcsv($line, escape: '\\');
            if (count($header) !== count($row)) {
                throw new ParserException('CSV payload has mismatched header and row column counts.');
            }

            /** @var array<string,mixed> $assoc */
            $assoc = array_combine($header, $row) ?: [];
            $rows[] = $assoc;
        }

        return $rows;
    }
}
