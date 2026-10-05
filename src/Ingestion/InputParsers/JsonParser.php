<?php

declare(strict_types=1);

namespace AltDTO\Ingestion\InputParsers;

use AltDTO\Contracts\ParserInterface;
use AltDTO\Exceptions\ParserException;

/**
 * Parses JSON payloads.
 */
final class JsonParser implements ParserInterface
{
    public function parse(mixed $input): array
    {
        if (!is_string($input)) {
            throw new ParserException('JSON parser expects a string payload.');
        }

        try {
            $decoded = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ParserException('Invalid JSON payload: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new ParserException('JSON payload must decode to an object/associative array.');
        }

        return $decoded;
    }

    public function parseMany(mixed $input): ?array
    {
        $decoded = $this->parse($input);

        return $this->isListOfRows($decoded) ? $decoded : null;
    }

    /**
     * @param array<int|string,mixed> $data
     */
    private function isListOfRows(array $data): bool
    {
        if (!array_is_list($data)) {
            return false;
        }

        foreach ($data as $item) {
            if (!is_array($item)) {
                return false;
            }
        }

        return true;
    }
}
