<?php

declare(strict_types=1);

namespace AltDTO\Transformation\Reflection;

use AltDTO\Reflection\docblocks;

/**
 * Parses @var docblocks into reusable type hints.
 *
 * Supports list<T>, array<T>, array<K,T> (key type is ignored), iterable<T>,
 * T[] and Collection<T>. Array shapes (array{...}) and "non-empty-*"
 * variants are intentionally not parsed for element types.
 */
final class DocBlockParser
{
    /**
     * @var array<string,array<string,mixed>>
     */
    private array $cache = [];

    /**
     * @return array<string,mixed>
     */
    public function parse(?string $docComment): array
    {
        if ($docComment === null || $docComment === '') {
            return [];
        }

        if (isset($this->cache[$docComment])) {
            return $this->cache[$docComment];
        }

        $varType = $this->extractVarType($docComment);
        if ($varType === null) {
            return $this->cache[$docComment] = [];
        }

        $parsed = [
            'raw' => $varType,
            'isList' => false,
            'isCollection' => false,
            'elementType' => null,
        ];

        $normalized = str_replace(' ', '', $varType);
        if (str_contains($normalized, '|null')) {
            $normalized = str_replace('|null', '', $normalized);
            $parsed['raw'] = str_replace('|null', '', $parsed['raw']);
        }

        if (preg_match('/^list<(.+)>$/i', $normalized, $m) === 1) {
            $parsed['isList'] = true;
            $parsed['elementType'] = $m[1];
        } elseif (preg_match('/^(?:array|iterable)<(.+)>$/i', $normalized, $m) === 1) {
            // For array<K,V>, only the value type (after the last comma) is kept.
            $parts = explode(',', $m[1]);
            $parsed['elementType'] = trim((string) end($parts));
        } elseif (str_ends_with($normalized, '[]')) {
            $parsed['elementType'] = substr($normalized, 0, -2);
        }

        if (preg_match('/^Collection<(.+)>$/i', $normalized, $m) === 1) {
            $parsed['isCollection'] = true;
            $parsed['elementType'] = $m[1];
        }

        return $this->cache[$docComment] = $parsed;
    }

    private function extractVarType(string $docComment): ?string
    {
        if (preg_match('/@var\s+([^\s\*]+)/', $docComment, $m) !== 1) {
            return null;
        }

        return trim($m[1]);
    }
}
